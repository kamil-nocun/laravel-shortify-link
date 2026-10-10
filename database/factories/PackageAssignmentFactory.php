<?php

namespace Database\Factories;

use App\Enums\BillingPeriod;
use App\Models\Package;
use App\Models\PackageAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PackageAssignment>
 */
class PackageAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'package_id' => Package::factory()->withPrices(),
            'billing_period' => BillingPeriod::Monthly,
            'price' => 1000,
            'link_limit' => fn (array $attributes) => Package::withTrashed()->findOrFail($attributes['package_id'])->link_limit,
            'domain_limit' => fn (array $attributes) => Package::withTrashed()->findOrFail($attributes['package_id'])->domain_limit,
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
        ];
    }

    public function expired(): static
    {
        return $this->state(['starts_at' => now()->subMonths(2), 'expires_at' => now()->subMonth()]);
    }
}
