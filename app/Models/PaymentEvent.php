<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log of provider interactions: initiation requests, callbacks/webhooks and status checks.
 * dedupe_key makes repeated deliveries of the same callback a no-op.
 */
#[Fillable(['payment_id', 'provider', 'type', 'dedupe_key', 'signature_valid', 'payload', 'result', 'ip_address', 'created_at'])]
#[WithoutTimestamps]
class PaymentEvent extends Model
{
    protected function casts(): array
    {
        return ['payload' => 'array', 'signature_valid' => 'boolean', 'created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
