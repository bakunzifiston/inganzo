<?php

namespace App\Models;

use App\Enums\OrderItemStatus;
use App\Enums\SellingType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Purchased line. Prices, the financial split and the print specification are frozen here
 * at checkout so later catalogue or pricing changes never alter historical orders.
 */
#[Fillable([
    'order_id', 'selling_type', 'finished_product_id', 'finished_product_variant_id', 'stock_released_at', 'artist_product_id', 'artwork_id', 'artwork_version_id', 'artist_profile_id', 'product_id',
    'product_variant_id', 'pricing_rule_id', 'title', 'product_name', 'color_name', 'color_hex', 'size_label', 'sku',
    'quantity', 'unit_price_amount', 'unit_base_cost_amount', 'unit_artist_earning_amount',
    'unit_platform_margin_amount', 'line_total_amount', 'discount_amount', 'refunded_amount',
    'fulfillment_status', 'placement_snapshot', 'delivered_at',
])]
class OrderItem extends Model
{
    protected function casts(): array
    {
        return [
            'selling_type' => SellingType::class,
            'stock_released_at' => 'datetime',
            'fulfillment_status' => OrderItemStatus::class,
            'placement_snapshot' => 'array',
            'quantity' => 'integer',
            'unit_price_amount' => 'integer',
            'unit_base_cost_amount' => 'integer',
            'unit_artist_earning_amount' => 'integer',
            'unit_platform_margin_amount' => 'integer',
            'line_total_amount' => 'integer',
            'discount_amount' => 'integer',
            'refunded_amount' => 'integer',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<ArtistProduct, $this>
     */
    public function artistProduct(): BelongsTo
    {
        return $this->belongsTo(ArtistProduct::class);
    }

    /**
     * @return BelongsTo<Artwork, $this>
     */
    public function artwork(): BelongsTo
    {
        return $this->belongsTo(Artwork::class);
    }

    /**
     * @return BelongsTo<ArtworkVersion, $this>
     */
    public function artworkVersion(): BelongsTo
    {
        return $this->belongsTo(ArtworkVersion::class);
    }

    /**
     * @return BelongsTo<ArtistProfile, $this>
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(ArtistProfile::class, 'artist_profile_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return HasMany<ProductionJob, $this>
     */
    public function productionJobs(): HasMany
    {
        return $this->hasMany(ProductionJob::class)->latest('id');
    }

    /**
     * @return HasOne<ProductionJob, $this>
     */
    public function activeJob(): HasOne
    {
        return $this->hasOne(ProductionJob::class)->whereNotIn('status', ['failed', 'cancelled'])->latestOfMany();
    }

    /**
     * @return HasMany<ShipmentItem, $this>
     */
    public function shipmentItems(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    /**
     * @return HasOne<Review, $this>
     */
    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    /**
     * @return BelongsTo<FinishedProduct, $this>
     */
    public function finishedProduct(): BelongsTo
    {
        return $this->belongsTo(FinishedProduct::class);
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
        return $this->selling_type === SellingType::Finished;
    }

    public function artistEarningTotal(): int
    {
        return $this->unit_artist_earning_amount * $this->quantity;
    }

    public function netLineAmount(): int
    {
        return $this->line_total_amount - $this->discount_amount;
    }

    public function variantLabel(): string
    {
        return collect([$this->color_name, $this->size_label])->filter()->implode(' · ');
    }
}
