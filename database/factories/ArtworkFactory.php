<?php

namespace Database\Factories;

use App\Enums\ArtworkStatus;
use App\Enums\ArtworkVersionStatus;
use App\Models\ArtistProfile;
use App\Models\Artwork;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Artwork>
 */
class ArtworkFactory extends Factory
{
    public function definition(): array
    {
        $title = Str::title(fake()->words(3, true));

        return [
            'artist_profile_id' => ArtistProfile::factory(),
            'category_id' => fn () => Category::query()->inRandomOrder()->value('id'),
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(5)),
            'description' => fake()->sentence(),
            'status' => ArtworkStatus::Draft,
            'rights_confirmed_at' => now(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => ArtworkStatus::Approved, 'published_at' => now(), 'submitted_at' => now()]);
    }

    public function pendingReview(): static
    {
        return $this->state(fn () => ['status' => ArtworkStatus::PendingReview, 'submitted_at' => now()]);
    }

    /**
     * Attach a processed version record (no file on disk) with the given pixel size.
     */
    public function withVersion(int $width = 3000, int $height = 3000): static
    {
        return $this->afterCreating(function (Artwork $artwork) use ($width, $height) {
            $version = $artwork->versions()->create([
                'version_number' => 1,
                'disk' => config('marketplace.artwork_disk'),
                'original_path' => "artworks/{$artwork->id}/v1/original.png",
                'preview_path' => "artworks/{$artwork->id}/v1/preview.webp",
                'thumbnail_path' => "artworks/{$artwork->id}/v1/thumbnail.webp",
                'original_filename' => 'design.png',
                'mime_type' => 'image/png',
                'width' => $width,
                'height' => $height,
                'file_size' => 1024,
                'has_alpha' => true,
                'sha256' => hash('sha256', (string) $artwork->id),
                'status' => ArtworkVersionStatus::Ready,
                'processed_at' => now(),
            ]);
            $artwork->forceFill(['current_version_id' => $version->id])->save();
        });
    }
}
