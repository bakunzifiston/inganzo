<?php

namespace App\Models;

use App\Enums\PrintCheckStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How a design is placed inside one print area. Offsets and scale are fractions of the
 * print area so they are independent of mockup image size. Rotation is in 90° steps.
 */
#[Fillable([
    'artist_product_id', 'print_area_id', 'artwork_version_id', 'offset_x', 'offset_y', 'scale', 'rotation',
    'effective_dpi', 'print_check_status', 'print_check_message',
])]
class ArtistProductPlacement extends Model
{
    protected function casts(): array
    {
        return [
            'offset_x' => 'float',
            'offset_y' => 'float',
            'scale' => 'float',
            'rotation' => 'integer',
            'effective_dpi' => 'integer',
            'print_check_status' => PrintCheckStatus::class,
        ];
    }

    /**
     * @return BelongsTo<ArtistProduct, $this>
     */
    public function artistProduct(): BelongsTo
    {
        return $this->belongsTo(ArtistProduct::class);
    }

    /**
     * @return BelongsTo<PrintArea, $this>
     */
    public function printArea(): BelongsTo
    {
        return $this->belongsTo(PrintArea::class);
    }

    /**
     * @return BelongsTo<ArtworkVersion, $this>
     */
    public function artworkVersion(): BelongsTo
    {
        return $this->belongsTo(ArtworkVersion::class);
    }
}
