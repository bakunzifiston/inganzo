<?php

namespace Database\Factories;

use App\Models\FinishedProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FinishedProductVariant>
 */
class FinishedProductVariantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sku' => 'FP-'.Str::upper(Str::random(8)),
            'stock_quantity' => 5,
            'is_active' => true,
        ];
    }
}
