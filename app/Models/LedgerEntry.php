<?php

namespace App\Models;

use App\Enums\LedgerAccount;
use App\Enums\LedgerBucket;
use App\Enums\LedgerEntryType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only ledger row. Written only through App\Services\Finance\Ledger.
 */
#[Fillable([
    'entry_key', 'account_type', 'account_id', 'bucket', 'type', 'amount', 'currency', 'order_id', 'order_item_id',
    'refund_id', 'payout_request_id', 'production_job_id', 'description', 'created_by', 'created_at',
])]
#[WithoutTimestamps]
class LedgerEntry extends Model
{
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Ledger entries are immutable. Post an adjustment instead.'));
        static::deleting(fn () => throw new LogicException('Ledger entries are immutable. Post an adjustment instead.'));
    }

    protected function casts(): array
    {
        return [
            'account_type' => LedgerAccount::class,
            'bucket' => LedgerBucket::class,
            'type' => LedgerEntryType::class,
            'amount' => 'integer',
            'created_at' => 'datetime',
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
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * @return BelongsTo<PayoutRequest, $this>
     */
    public function payoutRequest(): BelongsTo
    {
        return $this->belongsTo(PayoutRequest::class);
    }
}
