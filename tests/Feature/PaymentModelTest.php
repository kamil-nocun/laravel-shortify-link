<?php

use App\Enums\BillingPeriod;
use App\Enums\PaymentStatus;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

test('the factory creates a payment with a uuid key', function () {
    $payment = Payment::factory()->create();

    expect(Str::isUuid($payment->id))->toBeTrue()
        ->and($payment->status)->toBe(PaymentStatus::Open);
});

test('the stripe invoice id is unique', function () {
    Payment::factory()->create(['stripe_invoice_id' => 'in_1']);

    Payment::factory()->create(['stripe_invoice_id' => 'in_1']);
})->throws(UniqueConstraintViolationException::class);

test('deleting a user deletes their payments', function () {
    $user = User::factory()->create();
    Payment::factory()->for($user)->create();

    $user->delete();

    expect(Payment::count())->toBe(0);
});

test('force deleting a package keeps the payment without a package', function () {
    $package = Package::factory()->create();
    $payment = Payment::factory()->create(['package_id' => $package->id]);

    $package->forceDelete();

    expect($payment->refresh()->package_id)->toBeNull();
});

test('status and period are cast to enums and the user relation lists payments', function () {
    $user = User::factory()->create();
    $payment = Payment::factory()->for($user)->paid()->create(['billing_period' => 'monthly']);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and($payment->billing_period)->toBe(BillingPeriod::Monthly)
        ->and($payment->paid_at)->not->toBeNull()
        ->and($user->payments()->count())->toBe(1);
});
