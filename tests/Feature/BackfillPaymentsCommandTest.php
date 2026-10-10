<?php

use App\Enums\PaymentStatus;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;

beforeEach(function () {
    $this->package = Package::factory()->create(['price_monthly' => 1900, 'stripe_price_id_monthly' => 'price_bf']);
    $this->user = User::factory()->create(['stripe_id' => 'cus_bf']);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function backfillInvoice(string $id, array $overrides = []): array
{
    return array_merge([
        'id' => $id,
        'customer' => 'cus_bf',
        'status' => 'paid',
        'currency' => 'pln',
        'total' => 1900,
        'created' => now()->subDays(3)->timestamp,
        'hosted_invoice_url' => "https://invoice.stripe.test/i/{$id}",
        'status_transitions' => ['paid_at' => now()->subDays(2)->timestamp],
        'lines' => ['data' => [['price' => ['id' => 'price_bf']]]],
    ], $overrides);
}

test('backfill stores the paid and open invoices of a customer', function () {
    $this->billing->invoices[$this->user->id] = [
        backfillInvoice('in_paid'),
        backfillInvoice('in_open', ['status' => 'open', 'status_transitions' => ['paid_at' => null]]),
    ];

    $this->artisan('payments:backfill')->expectsOutputToContain('2')->assertExitCode(0);

    expect(Payment::pluck('status', 'stripe_invoice_id')->all())->toBe([
        'in_paid' => PaymentStatus::Paid,
        'in_open' => PaymentStatus::Open,
    ]);
});

test('running the backfill twice does not duplicate payments', function () {
    $this->billing->invoices[$this->user->id] = [backfillInvoice('in_once')];

    $this->artisan('payments:backfill');
    $this->artisan('payments:backfill');

    expect(Payment::count())->toBe(1);
});

test('users without a stripe id and zero-total invoices are skipped', function () {
    $noStripe = User::factory()->create(['stripe_id' => null]);
    $this->billing->invoices[$noStripe->id] = [backfillInvoice('in_ghost')];
    $this->billing->invoices[$this->user->id] = [backfillInvoice('in_zero', ['total' => 0])];

    $this->artisan('payments:backfill')->assertExitCode(0);

    expect(Payment::count())->toBe(0);
});
