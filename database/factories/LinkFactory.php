<?php

namespace Database\Factories;

use App\Models\Link;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Link>
 */
class LinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->optional()->sentence(3),
            'original_url' => 'https://'.fake()->domainName().'/'.fake()->slug(),
            'code' => Str::random(6),
            'is_active' => true,
            'expires_at' => null,
        ];
    }

    /**
     * Indicate that the link is inactive.
     */
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /**
     * Indicate that the link was disabled by an admin.
     */
    public function disabledByAdmin(): static
    {
        return $this->state(['disabled_by_admin_at' => now()]);
    }

    /**
     * Indicate that the link has already expired.
     */
    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }
}
