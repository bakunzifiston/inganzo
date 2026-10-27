<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An image an artist uploaded into the Design Studio (private disk, served to its owner only).
 */
#[Fillable(['artist_profile_id', 'disk', 'path', 'original_filename', 'mime_type', 'width', 'height', 'file_size', 'sha256'])]
class DesignAsset extends Model
{
    protected function casts(): array
    {
        return ['width' => 'integer', 'height' => 'integer', 'file_size' => 'integer'];
    }

    /**
     * @return BelongsTo<ArtistProfile, $this>
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(ArtistProfile::class, 'artist_profile_id');
    }

    public function url(): string
    {
        return route('studio.design-assets.show', $this);
    }
}
