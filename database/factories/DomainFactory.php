<?php

namespace Database\Factories;

use App\Models\Domain;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Domain>
 */
class DomainFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'host' => Str::lower(fake()->unique()->domainName()),
            'verification_token' => Str::random(40),
            'verified_at' => null,
            'disabled_by_admin_at' => null,
            'disabled_by' => null,
        ];
    }

    public function verified(): static
    {
        return $this->state(['verified_at' => now()]);
    }

    public function disabledByAdmin(): static
    {
        return $this->state(fn () => [
            'disabled_by_admin_at' => now(),
            'disabled_by' => User::factory()->superAdmin(),
        ]);
    }
}
