<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'stripe_invoice_id' => 'in_'.fake()->unique()->bothify('????########'),
            'package_id' => null,
            'billing_period' => null,
            'status' => PaymentStatus::Open,
            'amount' => 1900,
            'currency' => 'pln',
            'invoice_url' => 'https://invoice.stripe.test/i/'.fake()->lexify('????????'),
            'issued_at' => now(),
            'paid_at' => null,
            'stripe_event_at' => now(),
        ];
    }

    public function paid(): static
    {
        return $this->state(['status' => PaymentStatus::Paid, 'paid_at' => now()]);
    }

    public function open(): static
    {
        return $this->state(['status' => PaymentStatus::Open, 'paid_at' => null]);
    }
}
