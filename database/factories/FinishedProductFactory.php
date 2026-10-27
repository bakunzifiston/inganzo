<?php

namespace Database\Factories;

use App\Enums\FinishedProductStatus;
use App\Models\ArtistProfile;
use App\Models\FinishedProduct;
use App\Models\FinishedProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FinishedProduct>
 */
class FinishedProductFactory extends Factory
{
    public function definition(): array
    {
        $title = Str::title(fake()->words(3, true));

        return [
            'artist_profile_id' => ArtistProfile::factory(),
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(5)),
            'title' => $title,
            'description' => fake()->sentence(),
            'status' => FinishedProductStatus::Draft,
            'currency' => 'RWF',
            'price_amount' => 15000,
            'price_from_amount' => 15000,
            'is_handmade' => true,
            'processing_days' => 2,
            'low_stock_threshold' => 3,
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => FinishedProductStatus::Active, 'published_at' => now()]);
    }

    /**
     * Adds variants after creation: each is [color, size, stock] with optional price overrides.
     *
     * @param  list<array{0: string|null, 1: string|null, 2: int, 3?: int|null, 4?: int|null}>  $options
     */
    public function withVariants(array $options = [[null, null, 5]]): static
    {
        return $this->afterCreating(function (FinishedProduct $product) use ($options) {
            foreach ($options as $position => $option) {
                FinishedProductVariant::factory()->for($product, 'product')->create([
                    'color_name' => $option[0],
                    'color_hex' => $option[0] ? '#8a5a2b' : null,
                    'size_label' => $option[1],
                    'stock_quantity' => $option[2],
                    'price_amount' => $option[3] ?? null,
                    'sale_price_amount' => $option[4] ?? null,
                    'position' => $position,
                ]);
            }
            $product->load('variants')->refreshPriceFrom();
        });
    }
}
