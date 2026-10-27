<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Preview image for a blank. area_* fields locate the print area on the image as fractions (0–1).
 */
#[Fillable(['product_id', 'print_area_id', 'product_color_id', 'view', 'image_path', 'area_x', 'area_y', 'area_width', 'area_height', 'position'])]
class ProductMockup extends Model
{
    protected function casts(): array
    {
        return [
            'area_x' => 'float',
            'area_y' => 'float',
            'area_width' => 'float',
            'area_height' => 'float',
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
     * @return BelongsTo<PrintArea, $this>
     */
    public function printArea(): BelongsTo
    {
        return $this->belongsTo(PrintArea::class);
    }

    /**
     * @return BelongsTo<ProductColor, $this>
     */
    public function color(): BelongsTo
    {
        return $this->belongsTo(ProductColor::class, 'product_color_id');
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }
}
