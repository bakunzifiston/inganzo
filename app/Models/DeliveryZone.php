<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['country_id', 'name', 'fee_amount', 'free_over_amount', 'min_days', 'max_days', 'is_active', 'position'])]
class DeliveryZone extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'fee_amount' => 'integer',
            'free_over_amount' => 'integer',
            'min_days' => 'integer',
            'max_days' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return HasMany<District, $this>
     */
    public function districts(): HasMany
    {
        return $this->hasMany(District::class);
    }

    public function feeFor(int $subtotal): int
    {
        if ($this->free_over_amount !== null && $subtotal >= $this->free_over_amount) {
            return 0;
        }

        return $this->fee_amount;
    }

    public function estimateLabel(): string
    {
        return $this->min_days === $this->max_days
            ? trans_choice(':count business day|:count business days', $this->max_days, ['count' => $this->max_days])
            : __(':min–:max business days', ['min' => $this->min_days, 'max' => $this->max_days]);
    }
}
