<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'product_color_id', 'product_size_id', 'sku', 'extra_cost_amount', 'is_active'])]
class ProductVariant extends Model
{
    protected function casts(): array
    {
        return ['extra_cost_amount' => 'integer', 'is_active' => 'boolean'];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductColor, $this>
     */
    public function color(): BelongsTo
    {
        return $this->belongsTo(ProductColor::class, 'product_color_id');
    }

    /**
     * @return BelongsTo<ProductSize, $this>
     */
    public function size(): BelongsTo
    {
        return $this->belongsTo(ProductSize::class, 'product_size_id');
    }

    public function baseCost(): int
    {
        return $this->product->base_cost_amount + $this->extra_cost_amount;
    }

    public function isPurchasable(): bool
    {
        return $this->is_active
            && $this->product->is_active
            && $this->color->is_active
            && ($this->size === null || $this->size->is_active);
    }
}
