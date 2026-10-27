<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['cart_id', 'artist_product_id', 'product_variant_id', 'finished_product_variant_id', 'quantity'])]
class CartItem extends Model
{
    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * @return BelongsTo<ArtistProduct, $this>
     */
    public function artistProduct(): BelongsTo
    {
        return $this->belongsTo(ArtistProduct::class);
    }

    /**
     * @return BelongsTo<FinishedProductVariant, $this>
     */
    public function finishedVariant(): BelongsTo
    {
        return $this->belongsTo(FinishedProductVariant::class, 'finished_product_variant_id');
    }

    public function isFinished(): bool
    {
        return $this->finished_product_variant_id !== null;
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
