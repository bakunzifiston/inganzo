<?php

namespace App\Models;

use App\Enums\ArtistStatus;
use App\Enums\FinishedProductStatus;
use Database\Factories\FinishedProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A complete physical product (handmade, manufactured or in stock) sold by an artist from their
 * own inventory. Unlike a printed design it needs no artwork, print placement or DPI check.
 * Money is stored in minor units; the effective price of a variant is resolved by
 * FinishedProductVariant::currentPrice().
 */
#[Fillable([
    'category_id', 'title', 'description', 'currency', 'price_amount', 'sale_price_amount', 'sku',
    'is_handmade', 'weight_grams', 'processing_days', 'ships_from', 'shipping_note', 'low_stock_threshold',
])]
class FinishedProduct extends Model
{
    /** @use HasFactory<FinishedProductFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => FinishedProductStatus::class,
            'price_amount' => 'integer',
            'sale_price_amount' => 'integer',
            'price_from_amount' => 'integer',
            'is_handmade' => 'boolean',
            'weight_grams' => 'integer',
            'processing_days' => 'integer',
            'low_stock_threshold' => 'integer',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<ArtistProfile, $this>
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(ArtistProfile::class, 'artist_profile_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return HasMany<FinishedProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(FinishedProductVariant::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<FinishedProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(FinishedProductImage::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Visible in the marketplace: published, the artist is approved and something can be bought.
     *
     * @param  Builder<FinishedProduct>  $query
     */
    public function scopeListed(Builder $query): void
    {
        $query->where('finished_products.status', FinishedProductStatus::Active)
            ->whereHas('artist', fn (Builder $q) => $q->where('status', ArtistStatus::Approved))
            ->whereHas('variants', fn (Builder $q) => $q->where('is_active', true));
    }

    public function isListed(): bool
    {
        return $this->status === FinishedProductStatus::Active
            && $this->artist?->status === ArtistStatus::Approved
            && $this->variants->contains(fn (FinishedProductVariant $v) => $v->is_active);
    }

    public function totalStock(): int
    {
        return (int) $this->variants->where('is_active', true)->sum('stock_quantity');
    }

    public function isInStock(): bool
    {
        return $this->totalStock() > 0;
    }

    public function isLowStock(): bool
    {
        $stock = $this->totalStock();

        return $stock > 0 && $stock <= $this->low_stock_threshold;
    }

    public function coverImage(): ?FinishedProductImage
    {
        return $this->images->first();
    }

    /**
     * Lowest price a customer would currently pay, cached for listings and price sorting.
     */
    public function refreshPriceFrom(): void
    {
        $this->loadMissing('variants');
        $price = $this->variants->where('is_active', true)->map(fn (FinishedProductVariant $v) => $v->currentPrice($this))->min();

        $this->forceFill(['price_from_amount' => $price ?? $this->sale_price_amount ?? $this->price_amount])->save();
    }

    /**
     * Whether any current price is a reduced price (shown with the original struck through).
     */
    public function isOnSale(): bool
    {
        return $this->variants->where('is_active', true)->contains(fn (FinishedProductVariant $v) => $v->isOnSale($this));
    }
}
