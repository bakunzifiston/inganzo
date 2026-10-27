<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code', 'description', 'type', 'percent_bps', 'amount', 'currency', 'min_subtotal_amount',
    'max_redemptions', 'max_per_customer', 'starts_at', 'ends_at', 'is_active', 'created_by',
])]
class DiscountCode extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
            'percent_bps' => 'integer',
            'amount' => 'integer',
            'min_subtotal_amount' => 'integer',
            'max_redemptions' => 'integer',
            'max_per_customer' => 'integer',
            'redemptions_count' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<DiscountRedemption, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(DiscountRedemption::class);
    }

    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = mb_strtoupper(trim($value));
    }

    public function valueLabel(): string
    {
        return $this->type === DiscountType::Percent
            ? rtrim(rtrim(number_format($this->percent_bps / 100, 2), '0'), '.').'%'
            : Money::format($this->amount, $this->currency);
    }
}
