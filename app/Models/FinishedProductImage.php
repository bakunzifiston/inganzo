<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A product photo. Files live on the private artwork disk and are served by
 * FinishedProductMediaController, which only exposes photos of listed products to the public.
 */
#[Fillable(['disk', 'path', 'preview_path', 'thumbnail_path', 'width', 'height', 'sha256', 'position'])]
class FinishedProductImage extends Model
{
    /**
     * @return BelongsTo<FinishedProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(FinishedProduct::class, 'finished_product_id');
    }

    public function previewUrl(): string
    {
        return route('media.finished', ['image' => $this->id, 'variant' => 'preview', 'v' => substr($this->sha256, 0, 8)]);
    }

    public function thumbnailUrl(): string
    {
        return route('media.finished', ['image' => $this->id, 'variant' => 'thumbnail', 'v' => substr($this->sha256, 0, 8)]);
    }
}
