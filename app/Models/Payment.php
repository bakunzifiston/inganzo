<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One attempt to collect an order's total through a provider.
 */
#[Fillable([
    'order_id', 'provider', 'status', 'amount', 'currency', 'payer_phone', 'idempotency_key', 'provider_reference',
    'provider_status', 'failure_reason', 'fee_amount', 'meta', 'initiated_at', 'confirmed_at', 'failed_at', 'expires_at',
])]
class Payment extends Model
{
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'integer',
            'fee_amount' => 'integer',
            'meta' => 'array',
            'initiated_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'failed_at' => 'datetime',
            'expires_at' => 'datetime',
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
     * @return HasMany<PaymentEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class)->latest('id');
    }

    public function isFinal(): bool
    {
        return $this->status !== PaymentStatus::Pending;
    }
}
