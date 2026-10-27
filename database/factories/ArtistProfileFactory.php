<?php

namespace Database\Factories;

use App\Enums\ArtistStatus;
use App\Enums\RoleName;
use App\Models\ArtistProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ArtistProfile>
 */
class ArtistProfileFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->firstName().' '.fake()->randomElement(['Studio', 'Designs', 'Art', 'Prints']);

        return [
            'user_id' => User::factory()->withRole(RoleName::Artist),
            'display_name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'bio' => fake()->sentence(12),
            'status' => ArtistStatus::Approved,
            'approved_at' => now(),
            'agreement_accepted_at' => now(),
            'agreement_version' => config('marketplace.agreement_version'),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => ArtistStatus::Pending, 'approved_at' => null]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => ArtistStatus::Suspended]);
    }
}
