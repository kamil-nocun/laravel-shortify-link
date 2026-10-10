<?php

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'link_limit' => fake()->numberBetween(1, 100),
            'domain_limit' => 0,
            'price_one_time' => null,
            'price_monthly' => null,
            'price_quarterly' => null,
            'price_yearly' => null,
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }

    public function withPrices(): static
    {
        return $this->state([
            'price_one_time' => 4900,
            'price_monthly' => 1000,
            'price_quarterly' => 2700,
            'price_yearly' => 9900,
        ]);
    }
}
