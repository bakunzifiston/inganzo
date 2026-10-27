<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Physical printable region of a blank. Dimensions are millimetres.
 */
#[Fillable(['product_id', 'key', 'label', 'width_mm', 'height_mm', 'min_dpi', 'recommended_dpi', 'is_required', 'position'])]
class PrintArea extends Model
{
    protected function casts(): array
    {
        return [
            'width_mm' => 'integer',
            'height_mm' => 'integer',
            'min_dpi' => 'integer',
            'recommended_dpi' => 'integer',
            'is_required' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Height ÷ width of the physical area.
     */
    public function aspectRatio(): float
    {
        return $this->height_mm / $this->width_mm;
    }

    /**
     * Pixels needed across the full width at the recommended DPI.
     */
    public function recommendedPixelWidth(): int
    {
        return (int) ceil($this->width_mm / 25.4 * $this->recommended_dpi);
    }
}
