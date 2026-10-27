<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\PrintingPartner;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PrintingPartner>
 */
class PrintingPartnerFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company().' Print';

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'email' => fake()->unique()->companyEmail(),
            'phone' => '+250788'.fake()->numerify('######'),
            'country_id' => fn () => Country::query()->where('iso2', 'RW')->value('id'),
            'city' => 'Kigali',
            'daily_capacity' => 50,
            'priority' => 100,
            'is_active' => true,
        ];
    }

    /**
     * Offer every active product and serve all of the partner's country.
     */
    public function servingEverything(): static
    {
        return $this->afterCreating(function (PrintingPartner $partner) {
            foreach (Product::query()->get() as $product) {
                $partner->services()->create([
                    'product_id' => $product->id,
                    'unit_cost_amount' => (int) round($product->base_cost_amount * 0.9),
                    'production_days' => $product->production_days,
                    'is_active' => true,
                ]);
            }
            $partner->serviceAreas()->create(['country_id' => $partner->country_id, 'delivery_zone_id' => null]);
        });
    }
}
