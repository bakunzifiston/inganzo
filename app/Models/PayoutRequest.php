<?php

namespace App\Models;

use App\Enums\PayoutMethod;
use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'reference', 'artist_profile_id', 'payout_account_id', 'amount', 'currency', 'status', 'method', 'account_display',
    'admin_note', 'reviewed_by', 'reviewed_at', 'paid_at',
])]
class PayoutRequest extends Model
{
    protected function casts(): array
    {
        return [
            'status' => PayoutStatus::class,
            'method' => PayoutMethod::class,
            'amount' => 'integer',
            'reviewed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ArtistProfile, $this>
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(ArtistProfile::class, 'artist_profile_id');
    }

    /**
     * @return BelongsTo<PayoutAccount, $this>
     */
    public function payoutAccount(): BelongsTo
    {
        return $this->belongsTo(PayoutAccount::class);
    }

    /**
     * @return HasMany<PayoutTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(PayoutTransaction::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [PayoutStatus::Requested, PayoutStatus::Approved], true);
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }
}
