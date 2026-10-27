<?php

namespace Database\Factories;

use App\Enums\DiscountType;
use App\Models\DiscountCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DiscountCode>
 */
class DiscountCodeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => Str::upper(Str::random(8)),
            'type' => DiscountType::Percent,
            'percent_bps' => 1000,
            'currency' => 'RWF',
            'min_subtotal_amount' => 0,
            'max_per_customer' => 1,
            'is_active' => true,
        ];
    }

    public function fixed(int $amount): static
    {
        return $this->state(fn () => ['type' => DiscountType::Fixed, 'percent_bps' => null, 'amount' => $amount]);
    }
}
