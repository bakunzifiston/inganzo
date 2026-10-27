<?php

namespace App\Services\Artwork;

use App\Enums\MarkupType;
use App\Enums\PrintCheckStatus;
use App\Models\ArtistProduct;
use App\Models\Artwork;
use App\Models\ArtworkVersion;
use App\Models\Product;
use App\Services\Pricing\PriceCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates and updates artist products: placement per print area, enabled colours and markup.
 */
class ArtistProductConfigurator
{
    public function __construct(
        private PlacementValidator $placements,
        private PriceCalculator $pricing,
    ) {}

    /**
     * @param  array{
     *     is_enabled: bool,
     *     colors: list<int>,
     *     default_color_id?: int|null,
     *     markup_type: string,
     *     markup_bps?: int|null,
     *     markup_amount?: int|null,
     *     placements: array<int, array{offset_x: float, offset_y: float, scale: float, rotation?: int}>
     * }  $data  placements keyed by print_area_id
     */
    public function save(Artwork $artwork, Product $product, array $data): ArtistProduct
    {
        $version = $artwork->currentVersion;

        if ($version === null || ! $version->isReady()) {
            throw ValidationException::withMessages(['artwork' => __('Your artwork is still processing. Try again in a moment.')]);
        }

        $product->loadMissing(['printAreas', 'colors', 'variants']);
        $markupType = MarkupType::from($data['markup_type']);
        $this->pricing->assertMarkupAllowed($product, $markupType, $data['markup_bps'] ?? null, $data['markup_amount'] ?? null);

        $activeColorIds = $product->colors->where('is_active', true)->pluck('id')->all();
        $colors = array_values(array_intersect(array_map('intval', $data['colors']), $activeColorIds));

        if ($data['is_enabled'] && $colors === []) {
            throw ValidationException::withMessages(['colors' => __('Choose at least one colour.')]);
        }

        $areaIds = $product->printAreas->pluck('id')->all();
        $placementInput = array_intersect_key($data['placements'], array_flip($areaIds));

        if ($placementInput === []) {
            throw ValidationException::withMessages(['placements' => __('Place your design on at least one print area.')]);
        }

        foreach ($product->printAreas->where('is_required', true) as $required) {
            if (! isset($placementInput[$required->id])) {
                throw ValidationException::withMessages(['placements' => __('The :area print area is required.', ['area' => $required->label])]);
            }
        }

        return DB::transaction(function () use ($artwork, $product, $data, $markupType, $colors, $placementInput, $version) {
            $artistProduct = ArtistProduct::query()->firstOrNew(['artwork_id' => $artwork->id, 'product_id' => $product->id]);
            $artistProduct->fill([
                'is_enabled' => $data['is_enabled'],
                'markup_type' => $markupType,
                'markup_bps' => $markupType === MarkupType::Percent ? $data['markup_bps'] : null,
                'markup_amount' => $markupType === MarkupType::Fixed ? $data['markup_amount'] : null,
                'default_color_id' => in_array((int) ($data['default_color_id'] ?? 0), $colors, true) ? (int) $data['default_color_id'] : ($colors[0] ?? null),
            ]);
            $artistProduct->save();
            $artistProduct->colors()->sync($colors);

            $artistProduct->placements()->whereNotIn('print_area_id', array_keys($placementInput))->delete();

            foreach ($placementInput as $areaId => $placement) {
                $area = $product->printAreas->firstWhere('id', $areaId);
                $result = $this->placements->validate(
                    $version,
                    $area,
                    (float) $placement['offset_x'],
                    (float) $placement['offset_y'],
                    (float) $placement['scale'],
                    (int) ($placement['rotation'] ?? 0),
                );

                $artistProduct->placements()->updateOrCreate(['print_area_id' => $areaId], [
                    'artwork_version_id' => $version->id,
                    'offset_x' => $result->offsetX,
                    'offset_y' => $result->offsetY,
                    'scale' => $result->scale,
                    'rotation' => $result->rotation,
                    'effective_dpi' => $result->effectiveDpi,
                    'print_check_status' => $result->status,
                    'print_check_message' => $result->message,
                ]);
            }

            $this->refreshStatus($artistProduct);

            return $artistProduct->fresh(['placements', 'colors']);
        });
    }

    /**
     * Offer the design on every active product with a default placement. Products where
     * the artwork cannot be printed at sufficient resolution are created disabled.
     *
     * @return int number of products enabled
     */
    public function enableDefaults(Artwork $artwork): int
    {
        $version = $artwork->currentVersion;
        if ($version === null || ! $version->isReady()) {
            return 0;
        }

        $enabled = 0;
        $products = Product::query()->active()->with(['printAreas', 'colors', 'variants'])->get();

        foreach ($products as $product) {
            if ($artwork->artistProducts()->where('product_id', $product->id)->exists() || ! $area = $product->primaryPrintArea()) {
                continue;
            }

            $placement = $this->placements->defaultPlacement($version, $area);
            $rule = $this->pricing->ruleFor($product);
            $printable = $placement->status !== PrintCheckStatus::Fail;

            $this->save($artwork, $product, [
                'is_enabled' => $printable,
                'colors' => $product->colors->where('is_active', true)->pluck('id')->all(),
                'markup_type' => MarkupType::Percent->value,
                'markup_bps' => $rule->artist_markup_default_bps,
                'placements' => [$area->id => [
                    'offset_x' => $placement->offsetX,
                    'offset_y' => $placement->offsetY,
                    'scale' => $placement->scale,
                    'rotation' => 0,
                ]],
            ]);

            $enabled += $printable ? 1 : 0;
        }

        return $enabled;
    }

    /**
     * After a new file is processed, move every placement to it and re-run print checks.
     */
    public function rebaseOnVersion(Artwork $artwork, ArtworkVersion $version): void
    {
        $artwork->artistProducts()->with(['placements.printArea', 'product.variants', 'colors'])->get()
            ->each(function (ArtistProduct $artistProduct) use ($version) {
                foreach ($artistProduct->placements as $placement) {
                    $result = $this->placements->validate($version, $placement->printArea, $placement->offset_x, $placement->offset_y, $placement->scale, $placement->rotation);
                    $placement->update([
                        'artwork_version_id' => $version->id,
                        'offset_x' => $result->offsetX,
                        'offset_y' => $result->offsetY,
                        'scale' => $result->scale,
                        'effective_dpi' => $result->effectiveDpi,
                        'print_check_status' => $result->status,
                        'print_check_message' => $result->message,
                    ]);
                }
                $this->refreshStatus($artistProduct);
            });
    }

    /**
     * The product's print status is the worst status among its placements.
     */
    public function refreshStatus(ArtistProduct $artistProduct): void
    {
        $statuses = $artistProduct->placements()->pluck('print_check_status')->map(
            fn ($status) => $status instanceof PrintCheckStatus ? $status : PrintCheckStatus::from($status),
        );

        $status = match (true) {
            $statuses->isEmpty() => PrintCheckStatus::Pending,
            $statuses->contains(PrintCheckStatus::Fail) => PrintCheckStatus::Fail,
            $statuses->contains(PrintCheckStatus::Pending) => PrintCheckStatus::Pending,
            $statuses->contains(PrintCheckStatus::Warn) => PrintCheckStatus::Warn,
            default => PrintCheckStatus::Pass,
        };

        $artistProduct->forceFill(['print_check_status' => $status])->save();
        $this->pricing->refreshCachedPrice($artistProduct->fresh());
    }
}
