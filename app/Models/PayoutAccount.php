<?php

namespace App\Models;

use App\Enums\PayoutMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Artist payout destination. The account number is encrypted at rest and only the
 * last four digits are shown outside of payout processing.
 */
#[Fillable(['artist_profile_id', 'method', 'account_name', 'account_number', 'account_last4', 'bank_name', 'country_id', 'is_default'])]
#[Hidden(['account_number'])]
class PayoutAccount extends Model
{
    protected function casts(): array
    {
        return [
            'method' => PayoutMethod::class,
            'account_number' => 'encrypted',
            'is_default' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ArtistProfile, $this>
     */
    public function artistProfile(): BelongsTo
    {
        return $this->belongsTo(ArtistProfile::class);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function maskedDisplay(): string
    {
        return $this->method->label().' · '.$this->account_name.' · ••••'.$this->account_last4;
    }
}
