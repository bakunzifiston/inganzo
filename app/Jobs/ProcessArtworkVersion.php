<?php

namespace App\Jobs;

use App\Enums\ArtworkVersionStatus;
use App\Models\ArtworkVersion;
use App\Services\Artwork\ArtistProductConfigurator;
use App\Services\Artwork\ImageProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Generates web previews for an uploaded artwork version and re-validates
 * existing product placements against the new file.
 */
class ProcessArtworkVersion implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public int $versionId) {}

    public function handle(ImageProcessor $processor, ArtistProductConfigurator $configurator): void
    {
        $version = ArtworkVersion::query()->find($this->versionId);

        if ($version === null || $version->status === ArtworkVersionStatus::Ready) {
            return;
        }

        $result = $processor->process($version);

        $version->forceFill([
            'preview_path' => $result['preview_path'],
            'thumbnail_path' => $result['thumbnail_path'],
            'has_alpha' => $result['has_alpha'],
            'status' => ArtworkVersionStatus::Ready,
            'error' => null,
            'processed_at' => now(),
        ])->save();

        $artwork = $version->artwork;
        if ($artwork->current_version_id !== $version->id) {
            return;
        }

        // A first upload is offered on every product; later versions keep the artist's placements.
        $artwork->artistProducts()->exists()
            ? $configurator->rebaseOnVersion($artwork, $version)
            : $configurator->enableDefaults($artwork->fresh());
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Artwork processing failed', ['version' => $this->versionId, 'error' => $exception?->getMessage()]);

        ArtworkVersion::query()->whereKey($this->versionId)->update([
            'status' => ArtworkVersionStatus::Failed,
            'error' => mb_substr((string) $exception?->getMessage(), 0, 500),
        ]);
    }
}
