<?php

namespace App\Models;

use Database\Factories\FinishedProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One purchasable option (colour and/or size) of a finished product, with its own SKU and stock.
 * stock_quantity is only ever changed through App\Services\Inventory\StockManager.
 */
#[Fillable(['sku', 'color_name', 'color_hex', 'size_label', 'price_amount', 'sale_price_amount', 'is_active', 'position'])]
class FinishedProductVariant extends Model
{
    /** @use HasFactory<FinishedProductVariantFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price_amount' => 'integer',
            'sale_price_amount' => 'integer',
            'stock_quantity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<FinishedProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(FinishedProduct::class, 'finished_product_id');
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * The price a customer pays now: the sale price when one is set, otherwise the regular price,
     * each falling back from the variant to the product.
     */
    public function currentPrice(?FinishedProduct $product = null): int
    {
        $product ??= $this->product;
        $regular = $this->price_amount ?? $product->price_amount;
        $sale = $this->sale_price_amount ?? ($this->price_amount === null ? $product->sale_price_amount : null);

        return $sale !== null && $sale < $regular ? $sale : $regular;
    }

    public function regularPrice(?FinishedProduct $product = null): int
    {
        return $this->price_amount ?? ($product ?? $this->product)->price_amount;
    }

    public function isOnSale(?FinishedProduct $product = null): bool
    {
        return $this->currentPrice($product) < $this->regularPrice($product);
    }

    public function isPurchasable(): bool
    {
        return $this->is_active && $this->stock_quantity > 0;
    }

    public function label(): string
    {
        return collect([$this->color_name, $this->size_label])->filter()->implode(' · ');
    }
}
