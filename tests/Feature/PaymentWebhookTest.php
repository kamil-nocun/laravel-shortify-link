<?php

use App\Enums\BillingPeriod;
use App\Enums\PaymentStatus;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->travelTo(now()->startOfSecond());
    $this->user = User::factory()->create(['stripe_id' => 'cus_pay']);
    $this->package = Package::factory()->create([
        'price_monthly' => 1900,
        'stripe_price_id_monthly' => 'price_pay_monthly',
    ]);
});

/**
 * @param  array<string, mixed>  $invoice
 * @return array<string, mixed>
 */
function invoiceEvent(string $type, array $invoice = [], ?int $created = null, ?string $eventId = null): array
{
    return [
        'id' => $eventId ?? 'evt_'.Str::random(10),
        'object' => 'event',
        'type' => $type,
        'created' => $created ?? now()->timestamp,
        'data' => ['object' => array_merge([
            'object' => 'invoice',
            'id' => 'in_1',
            'customer' => 'cus_pay',
            'status' => 'open',
            'currency' => 'pln',
            'total' => 1900,
            'amount_paid' => 1900,
            'created' => now()->timestamp,
            'hosted_invoice_url' => 'https://invoice.stripe.test/i/in_1',
            'status_transitions' => ['paid_at' => null],
            'lines' => ['data' => [['price' => ['id' => 'price_pay_monthly'], 'period' => ['start' => now()->timestamp, 'end' => now()->addMonth()->timestamp]]]],
        ], $invoice)],
    ];
}

test('a finalized invoice is recorded as open with its package and link', function () {
    $this->postJson(route('cashier.webhook'), invoiceEvent('invoice.finalized'))->assertOk();

    $payment = Payment::sole();
    expect($payment->user_id)->toBe($this->user->id)
        ->and($payment->status)->toBe(PaymentStatus::Open)
        ->and($payment->package_id)->toBe($this->package->id)
        ->and($payment->billing_period)->toBe(BillingPeriod::Monthly)
        ->and($payment->amount)->toBe(1900)
        ->and($payment->currency)->toBe('pln')
        ->and($payment->invoice_url)->toBe('https://invoice.stripe.test/i/in_1');
});

test('a later payment_succeeded marks the invoice paid', function () {
    $this->postJson(route('cashier.webhook'), invoiceEvent('invoice.finalized'));
    $this->postJson(route('cashier.webhook'), invoiceEvent('invoice.payment_succeeded', ['status' => 'paid', 'status_transitions' => ['paid_at' => now()->timestamp]], now()->timestamp + 5));

    $payment = Payment::sole();
    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->paid_at->timestamp)->toBe(now()->timestamp);
});

test('a failed payment, a voided and an uncollectible invoice get their status', function (string $type, array $invoice, PaymentStatus $status) {
    $this->postJson(route('cashier.webhook'), invoiceEvent($type, $invoice))->assertOk();

    expect(Payment::sole()->status)->toBe($status);
})->with([
    'failed' => ['invoice.payment_failed', ['status' => 'open'], PaymentStatus::PaymentFailed],
    'void' => ['invoice.voided', ['status' => 'void'], PaymentStatus::Void],
    'uncollectible' => ['invoice.marked_uncollectible', ['status' => 'uncollectible'], PaymentStatus::Uncollectible],
]);

test('an older event never rolls the status back', function () {
    $this->postJson(route('cashier.webhook'), invoiceEvent('invoice.payment_succeeded', ['status' => 'paid', 'status_transitions' => ['paid_at' => now()->timestamp]], now()->timestamp + 10));
    $this->postJson(route('cashier.webhook'), invoiceEvent('invoice.finalized', [], now()->timestamp))->assertOk();

    expect(Payment::sole()->status)->toBe(PaymentStatus::Paid);
});

test('drafts and zero-total invoices are not recorded', function (array $invoice) {
    $this->postJson(route('cashier.webhook'), invoiceEvent('invoice.finalized', $invoice))->assertOk();

    expect(Payment::count())->toBe(0);
})->with([
    'draft' => [['status' => 'draft']],
    'zero total' => [['total' => 0]],
    'negative total' => [['total' => -500]],
]);

test('an unknown customer is acknowledged without a record', function () {
    $this->postJson(route('cashier.webhook'), invoiceEvent('invoice.finalized', ['customer' => 'cus_unknown']))->assertOk();

    expect(Payment::count())->toBe(0);
});

test('the same event delivered twice gives one payment', function () {
    $event = invoiceEvent('invoice.finalized');

    $this->postJson(route('cashier.webhook'), $event)->assertOk();
    $this->postJson(route('cashier.webhook'), $event)->assertOk();

    expect(Payment::count())->toBe(1);
});
