<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Platform margin and artist-markup limits. See docs/PRICING.md for the formula.
 * The most specific active rule wins: product > product type > global.
 */
#[Fillable([
    'name', 'product_type_id', 'product_id', 'currency', 'platform_margin_bps', 'platform_fixed_amount',
    'artist_markup_min_bps', 'artist_markup_max_bps', 'artist_markup_default_bps', 'artist_fixed_max_amount',
    'rounding_increment', 'is_active', 'starts_at', 'ends_at', 'created_by', 'updated_by',
])]
class PricingRule extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'platform_margin_bps' => 'integer',
            'platform_fixed_amount' => 'integer',
            'artist_markup_min_bps' => 'integer',
            'artist_markup_max_bps' => 'integer',
            'artist_markup_default_bps' => 'integer',
            'artist_fixed_max_amount' => 'integer',
            'rounding_increment' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
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
     * @return BelongsTo<ProductType, $this>
     */
    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    /**
     * @param  Builder<PricingRule>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function scopeLabel(): string
    {
        return match (true) {
            $this->product_id !== null => __('Product: :name', ['name' => $this->product?->translate('name')]),
            $this->product_type_id !== null => __('Product type: :name', ['name' => $this->productType?->translate('name')]),
            default => __('All products'),
        };
    }

    public function allowsFixedMarkup(): bool
    {
        return $this->artist_fixed_max_amount !== null;
    }
}
