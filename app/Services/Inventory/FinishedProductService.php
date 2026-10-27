<?php

namespace App\Services\Inventory;

use App\Enums\ArtistStatus;
use App\Enums\FinishedProductStatus;
use App\Models\ArtistProfile;
use App\Models\FinishedProduct;
use App\Models\FinishedProductImage;
use App\Models\FinishedProductVariant;
use App\Models\User;
use App\Services\Artwork\ArtworkUploader;
use App\Services\AuditLogger;
use App\Services\Settings;
use GdImage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates and maintains an artist's ready-made products: details, variants with their own stock
 * and SKU, product photos, and the publishing workflow (draft → review → on sale).
 *
 * Input must already be validated by StoreFinishedProductRequest; this class enforces the rules
 * that need the database (SKU uniqueness, order history, image count) and never trusts the browser
 * for status, stock reservations or ownership.
 */
class FinishedProductService
{
    public const MAX_IMAGES = 8;

    public function __construct(private Settings $settings, private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(ArtistProfile $artist, array $data): FinishedProduct
    {
        return DB::transaction(function () use ($artist, $data) {
            $product = new FinishedProduct($this->attributes($data));
            $product->artist_profile_id = $artist->id;
            $product->slug = $this->uniqueSlug($data['title']);
            $product->status = FinishedProductStatus::Draft;
            $product->currency = config('marketplace.default_currency');
            $product->save();

            $this->syncVariants($product, $data['variants']);
            $product->load('variants')->refreshPriceFrom();

            return $product;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(FinishedProduct $product, array $data): FinishedProduct
    {
        return DB::transaction(function () use ($product, $data) {
            $product = FinishedProduct::query()->lockForUpdate()->findOrFail($product->id);

            $wording = [$product->title, $product->description];
            $product->fill($this->attributes($data));
            $reviewable = $wording !== [$product->title, $product->description];
            $product->save();

            $this->syncVariants($product, $data['variants']);
            $product->load('variants')->refreshPriceFrom();

            // Price and stock changes are routine; new wording on a live listing is re-reviewed.
            if ($reviewable && $product->status === FinishedProductStatus::Active && $this->requiresApproval()) {
                $product->forceFill(['status' => FinishedProductStatus::PendingReview])->save();
            }

            return $product;
        });
    }

    /**
     * Put a finished listing on sale (or into review). Needs at least one photo and one option.
     *
     * @throws ValidationException
     */
    public function publish(FinishedProduct $product): FinishedProduct
    {
        $product->load(['images', 'variants', 'artist']);
        $problems = [];

        if (! in_array($product->status, [FinishedProductStatus::Draft, FinishedProductStatus::Rejected, FinishedProductStatus::Archived], true)) {
            $problems[] = __('This product is already published or in review.');
        }
        if (in_array($product->artist->status, [ArtistStatus::Suspended, ArtistStatus::Rejected], true)) {
            $problems[] = __('Your artist account cannot publish products right now.');
        }
        if ($product->images->isEmpty()) {
            $problems[] = __('Add at least one photo of the product.');
        }
        if (! $product->variants->contains(fn (FinishedProductVariant $v) => $v->is_active)) {
            $problems[] = __('Add at least one available option.');
        }
        if ($problems !== []) {
            throw ValidationException::withMessages(['publish' => $problems]);
        }

        $approval = $this->requiresApproval();
        $product->forceFill([
            'status' => $approval ? FinishedProductStatus::PendingReview : FinishedProductStatus::Active,
            'published_at' => $approval ? $product->published_at : ($product->published_at ?? now()),
            'moderation_note' => null,
        ])->save();

        return $product;
    }

    public function archive(FinishedProduct $product): void
    {
        $product->forceFill(['status' => FinishedProductStatus::Archived])->save();
    }

    /**
     * Super admin / moderator decision.
     *
     * @param  'approve'|'reject'|'suspend'|'reinstate'  $decision
     */
    public function moderate(FinishedProduct $product, string $decision, User $reviewer, ?string $note): void
    {
        $status = match ($decision) {
            'approve', 'reinstate' => FinishedProductStatus::Active,
            'reject' => FinishedProductStatus::Rejected,
            'suspend' => FinishedProductStatus::Suspended,
        };

        if (in_array($decision, ['reject', 'suspend'], true) && blank($note)) {
            throw ValidationException::withMessages(['note' => __('Explain your decision so the artist can respond.')]);
        }

        $product->forceFill([
            'status' => $status,
            'moderation_note' => $note,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'published_at' => $status === FinishedProductStatus::Active ? ($product->published_at ?? now()) : $product->published_at,
        ])->save();

        $this->audit->log("finished_product.{$decision}", $product, ['note' => $note], $reviewer);
    }

    /**
     * Artist stock adjustment for one variant.
     */
    public function setStock(FinishedProductVariant $variant, int $quantity, StockManager $stock): void
    {
        $stock->set($variant, $quantity);
    }

    /**
     * @param  array<int, UploadedFile>  $files
     *
     * @throws ValidationException
     */
    public function addImages(FinishedProduct $product, array $files): void
    {
        if ($product->images()->count() + count($files) > self::MAX_IMAGES) {
            throw ValidationException::withMessages(['images' => __('A product can have at most :max photos.', ['max' => self::MAX_IMAGES])]);
        }

        foreach ($files as $file) {
            $this->storeImage($product, $file);
        }
    }

    /**
     * @param  list<int>  $imageIds
     */
    public function removeImages(FinishedProduct $product, array $imageIds): void
    {
        foreach ($product->images()->whereIn('id', $imageIds)->get() as $image) {
            Storage::disk($image->disk)->delete([$image->path, $image->preview_path, $image->thumbnail_path]);
            $image->delete();
        }
        $this->renumberImages($product);
    }

    public function makeCover(FinishedProduct $product, int $imageId): void
    {
        $ids = $product->images()->pluck('id')->all();
        if (! in_array($imageId, $ids, true)) {
            return;
        }

        $order = [$imageId, ...array_values(array_diff($ids, [$imageId]))];
        foreach ($order as $position => $id) {
            FinishedProductImage::query()->whereKey($id)->update(['position' => $position]);
        }
    }

    public function requiresApproval(): bool
    {
        return $this->settings->boolean('moderation.require_approval');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'title' => trim($data['title']),
            'description' => $data['description'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'price_amount' => (int) $data['price_amount'],
            'sale_price_amount' => filled($data['sale_price_amount'] ?? null) ? (int) $data['sale_price_amount'] : null,
            'sku' => filled($data['sku'] ?? null) ? mb_strtoupper(trim($data['sku'])) : null,
            'is_handmade' => (bool) ($data['is_handmade'] ?? false),
            'weight_grams' => filled($data['weight_grams'] ?? null) ? (int) $data['weight_grams'] : null,
            'processing_days' => (int) ($data['processing_days'] ?? 2),
            'ships_from' => filled($data['ships_from'] ?? null) ? trim($data['ships_from']) : null,
            'shipping_note' => filled($data['shipping_note'] ?? null) ? trim($data['shipping_note']) : null,
            'low_stock_threshold' => (int) ($data['low_stock_threshold'] ?? 3),
        ];
    }

    /**
     * Create, update and retire variants. Variants that appear in orders are deactivated instead of
     * deleted so order history keeps its references.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncVariants(FinishedProduct $product, array $rows): void
    {
        $existing = $product->variants()->get()->keyBy('id');
        $kept = [];

        foreach (array_values($rows) as $position => $row) {
            $variant = filled($row['id'] ?? null) ? $existing->get((int) $row['id']) : null;
            if (filled($row['id'] ?? null) && $variant === null) {
                throw ValidationException::withMessages(['variants' => __('One of the options does not belong to this product.')]);
            }
            // A row without an id that repeats an existing colour and size updates that option.
            $variant ??= $existing->first(fn (FinishedProductVariant $v) => ! in_array($v->id, $kept, true)
                && mb_strtolower((string) $v->color_name).'|'.mb_strtolower((string) $v->size_label) === mb_strtolower(trim((string) ($row['color_name'] ?? ''))).'|'.mb_strtolower(trim((string) ($row['size_label'] ?? ''))));

            $variant ??= new FinishedProductVariant;
            $variant->fill([
                'color_name' => filled($row['color_name'] ?? null) ? trim($row['color_name']) : null,
                'color_hex' => filled($row['color_name'] ?? null) && filled($row['color_hex'] ?? null) ? mb_strtolower($row['color_hex']) : null,
                'size_label' => filled($row['size_label'] ?? null) ? trim($row['size_label']) : null,
                'price_amount' => filled($row['price_amount'] ?? null) ? (int) $row['price_amount'] : null,
                'sale_price_amount' => filled($row['sale_price_amount'] ?? null) ? (int) $row['sale_price_amount'] : null,
                'is_active' => (bool) ($row['is_active'] ?? true),
                'position' => $position,
            ]);
            $variant->sku = filled($row['sku'] ?? null) ? mb_strtoupper(trim($row['sku'])) : ($variant->sku ?? $this->generateSku($product));
            $variant->stock_quantity = (int) $row['stock_quantity'];
            $variant->finished_product_id = $product->id;

            try {
                $variant->save();
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['variants' => __('The SKU ":sku" or this colour and size combination is already in use.', ['sku' => $variant->sku])]);
            }

            $kept[] = $variant->id;
        }

        foreach ($existing->except($kept) as $stale) {
            $stale->orderItems()->exists()
                ? $stale->forceFill(['is_active' => false])->save()
                : $stale->delete();
        }
    }

    private function generateSku(FinishedProduct $product): string
    {
        $base = $product->sku ?: 'FP'.$product->id;

        do {
            $sku = mb_strtoupper($base.'-'.Str::random(4));
        } while (FinishedProductVariant::query()->where('sku', $sku)->exists());

        return $sku;
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title) ?: 'product', 140, '');

        do {
            $slug = $base.'-'.Str::lower(Str::random(5));
        } while (FinishedProduct::query()->where('slug', $slug)->exists());

        return $slug;
    }

    private function renumberImages(FinishedProduct $product): void
    {
        $product->images()->get()->each(fn (FinishedProductImage $image, int $position) => $image->update(['position' => $position]));
    }

    /**
     * Product photos are validated by content, stored privately and resized to web derivatives.
     *
     * @throws ValidationException
     */
    private function storeImage(FinishedProduct $product, UploadedFile $file): FinishedProductImage
    {
        $path = $file->getRealPath();
        $mime = $path ? (new \finfo(FILEINFO_MIME_TYPE))->file($path) : false;
        $info = is_string($mime) && array_key_exists($mime, ArtworkUploader::ALLOWED_MIME_TYPES) ? @getimagesize($path) : false;

        if ($info === false || $info['mime'] !== $mime) {
            throw ValidationException::withMessages(['images' => __('Upload PNG, JPEG or WebP photos.')]);
        }
        [$width, $height] = $info;
        if (min($width, $height) < 400 || max($width, $height) > config('marketplace.artwork.max_pixels')) {
            throw ValidationException::withMessages(['images' => __('Photos must be at least 400 px and at most :max px on each side.', ['max' => config('marketplace.artwork.max_pixels')])]);
        }

        $previousLimit = ini_get('memory_limit');
        ini_set('memory_limit', config('marketplace.artwork.processing_memory'));

        try {
            $source = @imagecreatefromstring((string) file_get_contents($path));
            if (! $source instanceof GdImage) {
                throw ValidationException::withMessages(['images' => __('The file could not be read as an image. It may be damaged.')]);
            }

            $disk = config('marketplace.artwork_disk');
            $directory = "finished-products/{$product->id}/".Str::uuid();
            $original = $file->storeAs($directory, 'original.'.ArtworkUploader::ALLOWED_MIME_TYPES[$mime], ['disk' => $disk]);
            Storage::disk($disk)->put("{$directory}/preview.webp", $this->webp($source, 1400, 86));
            Storage::disk($disk)->put("{$directory}/thumbnail.webp", $this->webp($source, 480, 80));
            imagedestroy($source);
        } finally {
            ini_set('memory_limit', (string) $previousLimit);
        }

        return $product->images()->create([
            'disk' => $disk,
            'path' => $original,
            'preview_path' => "{$directory}/preview.webp",
            'thumbnail_path' => "{$directory}/thumbnail.webp",
            'width' => $width,
            'height' => $height,
            'sha256' => hash_file('sha256', $path),
            'position' => (int) $product->images()->max('position') + 1,
        ]);
    }

    private function webp(GdImage $source, int $maxSide, int $quality): string
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $ratio = min(1, $maxSide / max($width, $height));
        $targetWidth = max(1, (int) round($width * $ratio));
        $targetHeight = max(1, (int) round($height * $ratio));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagewebp($target, null, $quality);
        $data = (string) ob_get_clean();
        imagedestroy($target);

        return $data;
    }
}
