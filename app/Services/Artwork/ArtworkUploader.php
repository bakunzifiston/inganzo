<?php

namespace App\Services\Artwork;

use App\Enums\ArtworkStatus;
use App\Enums\ArtworkVersionStatus;
use App\Jobs\ProcessArtworkVersion;
use App\Models\ArtistProfile;
use App\Models\Artwork;
use App\Models\ArtworkVersion;
use App\Models\Tag;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores original uploads on the private artwork disk and queues derivative processing.
 * File type is determined from content (finfo + getimagesize), never from the extension.
 */
class ArtworkUploader
{
    public const ALLOWED_MIME_TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    /**
     * @param  array{title: string, description?: string|null, category_id?: int|null, tags?: list<string>, is_mature?: bool}  $attributes
     */
    public function create(ArtistProfile $artist, array $attributes, UploadedFile $file): Artwork
    {
        $this->inspect($file);

        return DB::transaction(function () use ($artist, $attributes, $file) {
            $artwork = $this->createBlank($artist, $attributes);
            $this->addVersion($artwork, $file);

            return $artwork;
        });
    }

    /**
     * A draft artwork without a file yet (Design Studio designs get their file on export).
     *
     * @param  array{title: string, description?: string|null, category_id?: int|null, tags?: list<string>, is_mature?: bool}  $attributes
     */
    public function createBlank(ArtistProfile $artist, array $attributes): Artwork
    {
        return DB::transaction(function () use ($artist, $attributes) {
            $artwork = new Artwork([
                'title' => $attributes['title'],
                'description' => $attributes['description'] ?? null,
                'category_id' => $attributes['category_id'] ?? null,
                'is_mature' => $attributes['is_mature'] ?? false,
                'rights_confirmed_at' => now(),
            ]);
            $artwork->artist_profile_id = $artist->id;
            $artwork->slug = $this->uniqueSlug($attributes['title']);
            $artwork->status = ArtworkStatus::Draft;
            $artwork->save();

            $artwork->tags()->sync(Tag::idsFor($attributes['tags'] ?? []));

            return $artwork;
        });
    }

    /**
     * Replace the source file. Published artwork returns to draft and must be re-reviewed.
     *
     * @param  array{source_dpi_limit?: int|null, design_dpi?: int|null}  $studio  resolution facts of a Design Studio export
     */
    public function addVersion(Artwork $artwork, UploadedFile $file, array $studio = []): ArtworkVersion
    {
        [$mime, $width, $height] = $this->inspect($file);

        return DB::transaction(function () use ($artwork, $file, $mime, $width, $height, $studio) {
            // Lock the parent row to serialise concurrent uploads (PostgreSQL forbids FOR UPDATE on aggregates).
            Artwork::query()->whereKey($artwork->id)->lockForUpdate()->first();
            $number = (int) $artwork->versions()->max('version_number') + 1;
            $disk = config('marketplace.artwork_disk');
            $extension = self::ALLOWED_MIME_TYPES[$mime];
            $directory = "artworks/{$artwork->id}/v{$number}";
            $path = $file->storeAs($directory, "original.{$extension}", ['disk' => $disk]);

            $version = $artwork->versions()->create([
                'version_number' => $number,
                'disk' => $disk,
                'original_path' => $path,
                'original_filename' => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime_type' => $mime,
                'width' => $width,
                'height' => $height,
                'file_size' => $file->getSize(),
                'sha256' => hash_file('sha256', $file->getRealPath()),
                'status' => ArtworkVersionStatus::Processing,
                'source_dpi_limit' => $studio['source_dpi_limit'] ?? null,
                'design_dpi' => $studio['design_dpi'] ?? null,
            ]);

            $artwork->current_version_id = $version->id;
            if ($artwork->status === ArtworkStatus::Approved || $artwork->status === ArtworkStatus::PendingReview) {
                $artwork->status = ArtworkStatus::Draft;
            }
            $artwork->save();

            ProcessArtworkVersion::dispatch($version->id)->afterCommit();

            return $version;
        });
    }

    /**
     * @return array{0: string, 1: int, 2: int}
     *
     * @throws ValidationException
     */
    public function inspect(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        $mime = $path ? (new \finfo(FILEINFO_MIME_TYPE))->file($path) : false;

        if (! is_string($mime) || ! array_key_exists($mime, self::ALLOWED_MIME_TYPES)) {
            throw ValidationException::withMessages(['file' => __('Upload a PNG, JPEG or WebP image.')]);
        }

        $size = @getimagesize($path);
        if ($size === false || $size['mime'] !== $mime) {
            throw ValidationException::withMessages(['file' => __('The file could not be read as an image. It may be damaged.')]);
        }

        [$width, $height] = $size;
        $limits = config('marketplace.artwork');

        if ($width < $limits['min_pixels'] || $height < $limits['min_pixels']) {
            throw ValidationException::withMessages(['file' => __('This image is too small to print sharply (:w × :h px). Upload at least :min px on each side — 3000 px or more is best for T-shirts and hoodies. Images saved from websites or social media are usually too small; export the original file from your design app instead.', [
                'min' => $limits['min_pixels'], 'w' => $width, 'h' => $height,
            ])]);
        }

        if ($width > $limits['max_pixels'] || $height > $limits['max_pixels']) {
            throw ValidationException::withMessages(['file' => __('Artwork must be at most :max pixels on each side.', ['max' => $limits['max_pixels']])]);
        }

        return [$mime, $width, $height];
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'design';
        $base = Str::limit($base, 140, '');

        do {
            $slug = $base.'-'.Str::lower(Str::random(5));
        } while (Artwork::query()->where('slug', $slug)->exists());

        return $slug;
    }
}
