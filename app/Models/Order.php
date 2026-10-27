<?php

namespace App\Models;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'number', 'user_id', 'customer_name', 'email', 'phone', 'currency', 'country_id', 'delivery_zone_id', 'locale',
    'status', 'payment_status', 'fulfillment_status', 'payment_method', 'subtotal_amount', 'discount_amount',
    'delivery_amount', 'tax_amount', 'tax_rate_bps', 'total_amount', 'refunded_amount', 'discount_code_id',
    'tracking_token', 'customer_note', 'placed_at', 'paid_at', 'completed_at', 'cancelled_at', 'cancel_reason',
])]
class Order extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => OrderPaymentStatus::class,
            'fulfillment_status' => FulfillmentStatus::class,
            'subtotal_amount' => 'integer',
            'discount_amount' => 'integer',
            'delivery_amount' => 'integer',
            'tax_amount' => 'integer',
            'total_amount' => 'integer',
            'refunded_amount' => 'integer',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    /**
     * @return HasOne<OrderAddress, $this>
     */
    public function address(): HasOne
    {
        return $this->hasOne(OrderAddress::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('id');
    }

    /**
     * @return HasOne<Payment, $this>
     */
    public function successfulPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->where('status', 'succeeded');
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class)->latest('id');
    }

    /**
     * @return HasMany<Shipment, $this>
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class)->latest('id');
    }

    /**
     * @return BelongsTo<DeliveryZone, $this>
     */
    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class);
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo<DiscountCode, $this>
     */
    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class);
    }

    /**
     * Orders that count as sales: paid (possibly partially refunded), never pending, failed,
     * cancelled or fully refunded.
     *
     * @param  Builder<Order>  $query
     */
    public function scopeCountableSales(Builder $query): void
    {
        $query->whereIn('orders.payment_status', [OrderPaymentStatus::Paid->value, OrderPaymentStatus::PartiallyRefunded->value]);
    }

    public function isPaid(): bool
    {
        return in_array($this->payment_status, [OrderPaymentStatus::Paid, OrderPaymentStatus::PartiallyRefunded], true);
    }

    public function isAwaitingPayment(): bool
    {
        return $this->status === OrderStatus::PendingPayment
            && in_array($this->payment_status, [OrderPaymentStatus::Unpaid, OrderPaymentStatus::Pending, OrderPaymentStatus::Failed], true);
    }

    public function refundableAmount(): int
    {
        return $this->isPaid() ? $this->total_amount - $this->refunded_amount : 0;
    }

    public function netAmount(): int
    {
        return $this->total_amount - $this->refunded_amount;
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }
}
