<?php

namespace App\Models;

use App\Enums\ArtworkVersionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable uploaded file and its derived assets. Files live on a private disk and are
 * served through MediaController so unpublished artwork is never publicly reachable.
 */
#[Fillable([
    'artwork_id', 'version_number', 'disk', 'original_path', 'preview_path', 'thumbnail_path',
    'original_filename', 'mime_type', 'width', 'height', 'file_size', 'has_alpha', 'sha256',
    'status', 'error', 'processed_at', 'source_dpi_limit', 'design_dpi',
])]
class ArtworkVersion extends Model
{
    protected function casts(): array
    {
        return [
            'status' => ArtworkVersionStatus::class,
            'has_alpha' => 'boolean',
            'source_dpi_limit' => 'integer',
            'design_dpi' => 'integer',
            'processed_at' => 'datetime',
            'width' => 'integer',
            'height' => 'integer',
            'file_size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Artwork, $this>
     */
    public function artwork(): BelongsTo
    {
        return $this->belongsTo(Artwork::class);
    }

    public function isReady(): bool
    {
        return $this->status === ArtworkVersionStatus::Ready;
    }

    public function previewUrl(): string
    {
        return route('media.artwork', ['version' => $this->id, 'variant' => 'preview', 'v' => $this->sha256 ? substr($this->sha256, 0, 8) : null]);
    }

    public function thumbnailUrl(): string
    {
        return route('media.artwork', ['version' => $this->id, 'variant' => 'thumbnail', 'v' => $this->sha256 ? substr($this->sha256, 0, 8) : null]);
    }

    public function aspectRatio(): float
    {
        return $this->width && $this->height ? $this->height / $this->width : 1.0;
    }
}
