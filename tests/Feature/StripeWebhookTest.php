<?php

use App\Enums\BillingPeriod;
use App\Models\Package;
use App\Models\PackageAssignment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->travelTo(now()->startOfSecond());
    $this->user = User::factory()->create(['stripe_id' => 'cus_test']);
    $this->package = Package::factory()->create([
        'link_limit' => 40,
        'price_monthly' => 1000,
        'price_one_time' => 4900,
        'stripe_price_id_monthly' => 'price_monthly',
        'stripe_price_id_one_time' => 'price_one_time',
    ]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function stripeEvent(string $type, array $object, ?string $id = null): array
{
    return ['id' => $id ?? 'evt_'.Str::random(10), 'object' => 'event', 'type' => $type, 'data' => ['object' => $object]];
}

/**
 * @return array<string, mixed>
 */
function invoicePaid(string $priceId, int $amount, int $start, int $end, ?string $subscription = null, string $customer = 'cus_test', ?string $eventId = null): array
{
    return stripeEvent('invoice.payment_succeeded', [
        'object' => 'invoice',
        'customer' => $customer,
        'subscription' => $subscription,
        'amount_paid' => $amount,
        'lines' => ['data' => [['price' => ['id' => $priceId], 'period' => ['start' => $start, 'end' => $end]]]],
    ], $eventId);
}

test('a paid invoice creates the assignment with a snapshot', function () {
    $subscription = Subscription::create(['user_id' => $this->user->id, 'type' => 'default', 'stripe_id' => 'sub_1', 'stripe_status' => 'active', 'stripe_price' => 'price_monthly', 'quantity' => 1]);

    $this->postJson(route('cashier.webhook'), invoicePaid('price_monthly', 1000, now()->timestamp, now()->addMonth()->timestamp, 'sub_1'))->assertOk();

    $assignment = $this->user->packageAssignment()->firstOrFail();
    expect($assignment->package_id)->toBe($this->package->id)
        ->and($assignment->billing_period)->toBe(BillingPeriod::Monthly)
        ->and($assignment->price)->toBe(1000)
        ->and($assignment->link_limit)->toBe(40)
        ->and($assignment->expires_at->timestamp)->toBe(now()->addMonth()->timestamp)
        ->and($assignment->subscription_id)->toBe($subscription->id)
        ->and($this->user->linkLimit())->toBe(40);
});

test('the newer invoice payload shape is understood', function () {
    $event = stripeEvent('invoice.payment_succeeded', [
        'object' => 'invoice',
        'customer' => 'cus_test',
        'amount_paid' => 1000,
        'parent' => ['subscription_details' => ['subscription' => 'sub_new']],
        'lines' => ['data' => [['pricing' => ['price_details' => ['price' => 'price_monthly']], 'period' => ['start' => now()->timestamp, 'end' => now()->addMonth()->timestamp]]]],
    ]);

    $this->postJson(route('cashier.webhook'), $event)->assertOk();

    expect($this->user->packageAssignment()->firstOrFail()->package_id)->toBe($this->package->id);
});

test('a renewal moves the period and refreshes the limit', function () {
    $this->postJson(route('cashier.webhook'), invoicePaid('price_monthly', 1000, now()->timestamp, now()->addMonth()->timestamp));
    $this->package->update(['link_limit' => 60]);
    $this->travelTo(now()->addMonth());

    $this->postJson(route('cashier.webhook'), invoicePaid('price_monthly', 1000, now()->timestamp, now()->addMonth()->timestamp));

    $assignment = $this->user->packageAssignment()->firstOrFail();
    expect(PackageAssignment::where('user_id', $this->user->id)->count())->toBe(1)
        ->and($assignment->link_limit)->toBe(60)
        ->and($assignment->expires_at->timestamp)->toBe(now()->addMonth()->timestamp);
});

test('the same event is applied only once', function () {
    $event = invoicePaid('price_monthly', 1000, now()->timestamp, now()->addMonth()->timestamp, null, 'cus_test', 'evt_same');
    $this->postJson(route('cashier.webhook'), $event)->assertOk();
    $this->package->update(['link_limit' => 99]);

    $this->postJson(route('cashier.webhook'), $event)->assertOk();

    expect($this->user->packageAssignment()->firstOrFail()->link_limit)->toBe(40);
});

test('a one time payment lasts one calendar month and is not renewed', function () {
    $this->postJson(route('cashier.webhook'), invoicePaid('price_one_time', 4900, now()->timestamp, now()->timestamp));

    $assignment = $this->user->packageAssignment()->firstOrFail();
    expect($assignment->billing_period)->toBe(BillingPeriod::OneTime)
        ->and($assignment->price)->toBe(4900)
        ->and($assignment->expires_at->equalTo(now()->addMonthNoOverflow()))->toBeTrue();
});

test('a deleted subscription ends the assignment', function () {
    $subscription = Subscription::create(['user_id' => $this->user->id, 'type' => 'default', 'stripe_id' => 'sub_9', 'stripe_status' => 'active', 'stripe_price' => 'price_monthly', 'quantity' => 1]);
    PackageAssignment::factory()->for($this->user)->for($this->package)->create(['subscription_id' => $subscription->id]);

    $this->postJson(route('cashier.webhook'), stripeEvent('customer.subscription.deleted', ['id' => 'sub_9', 'customer' => 'cus_test', 'status' => 'canceled']))->assertOk();

    expect($this->user->activeAssignment())->toBeNull()
        ->and(PackageAssignment::where('user_id', $this->user->id)->count())->toBe(1)
        ->and($this->user->linkLimit())->toBe(10);
});

test('events for unknown customers or prices change nothing and still answer 200', function () {
    $this->postJson(route('cashier.webhook'), invoicePaid('price_monthly', 1000, now()->timestamp, now()->addMonth()->timestamp, null, 'cus_unknown'))->assertOk();
    $this->postJson(route('cashier.webhook'), invoicePaid('price_unknown', 1000, now()->timestamp, now()->addMonth()->timestamp))->assertOk();

    expect(PackageAssignment::count())->toBe(0);
});

test('a webhook with a bad signature is rejected', function () {
    config(['cashier.webhook.secret' => 'whsec_test']);
    $payload = json_encode(invoicePaid('price_monthly', 1000, now()->timestamp, now()->addMonth()->timestamp));

    $this->call('POST', route('cashier.webhook'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => 't='.time().',v1=bad', 'CONTENT_TYPE' => 'application/json'], $payload)->assertForbidden();

    expect(PackageAssignment::count())->toBe(0);
});

test('a webhook with a valid signature is accepted', function () {
    config(['cashier.webhook.secret' => 'whsec_test']);
    $payload = json_encode(invoicePaid('price_monthly', 1000, now()->timestamp, now()->addMonth()->timestamp));
    $timestamp = time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test');

    $this->call('POST', route('cashier.webhook'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}", 'CONTENT_TYPE' => 'application/json'], $payload)->assertOk();

    expect(PackageAssignment::count())->toBe(1);
});

test('a package bought after cancelling starts when the current one ends', function () {
    $current = PackageAssignment::factory()->for($this->user)->for($this->package)->create(['billing_period' => 'one_time', 'starts_at' => now()->subDays(10), 'expires_at' => now()->addDays(20), 'cancelled_at' => now()]);

    $this->postJson(route('cashier.webhook'), invoicePaid('price_one_time', 4900, now()->timestamp, now()->timestamp))->assertOk();

    $queued = $this->user->queuedAssignment();
    expect($queued->starts_at->timestamp)->toBe($current->expires_at->timestamp)
        ->and($queued->expires_at->timestamp)->toBe($current->expires_at->copy()->addMonthNoOverflow()->timestamp)
        ->and($this->user->activeAssignment()->id)->toBe($current->id);

    $this->travelTo($current->expires_at->copy()->addMinute());
    expect($this->user->fresh()->activeAssignment()->id)->toBe($queued->id);
});
