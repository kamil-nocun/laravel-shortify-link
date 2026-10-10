<?php

use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;

test('super admin stores a package with a domain limit', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->post(route('admin.packages.store'), ['name' => 'Pro', 'link_limit' => 10, 'domain_limit' => 3])
        ->assertRedirect(route('admin.packages.index'));

    expect(Package::where('name', 'Pro')->firstOrFail()->domain_limit)->toBe(3);
});

test('super admin updates the domain limit', function () {
    $package = Package::factory()->create(['domain_limit' => 1]);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->put(route('admin.packages.update', $package), ['name' => $package->name, 'link_limit' => 10, 'domain_limit' => 7])
        ->assertRedirect();

    expect($package->fresh()->domain_limit)->toBe(7);
});

test('a regular admin cannot create or update packages', function () {
    $admin = User::factory()->admin()->create();
    $package = Package::factory()->create(['domain_limit' => 1]);

    $this->actingAs($admin)->post(route('admin.packages.store'), ['name' => 'X', 'link_limit' => 1, 'domain_limit' => 9])->assertForbidden();
    $this->actingAs($admin)->put(route('admin.packages.update', $package), ['name' => 'X', 'link_limit' => 1, 'domain_limit' => 9])->assertForbidden();

    expect($package->fresh()->domain_limit)->toBe(1)
        ->and(Package::where('name', 'X')->exists())->toBeFalse();
});

test('domain_limit is required and must be a non-negative integer', function (mixed $value) {
    $payload = ['name' => 'Pro', 'link_limit' => 10] + ($value === null ? [] : ['domain_limit' => $value]);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->post(route('admin.packages.store'), $payload)
        ->assertSessionHasErrors('domain_limit');
})->with([null, -1, 'abc', 1.5]);

test('a purchased package snapshots its domain limit', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_test']);
    Package::factory()->create(['domain_limit' => 4, 'price_monthly' => 1000, 'stripe_price_id_monthly' => 'price_monthly']);
    Subscription::create(['user_id' => $user->id, 'type' => 'default', 'stripe_id' => 'sub_1', 'stripe_status' => 'active', 'stripe_price' => 'price_monthly', 'quantity' => 1]);

    $this->postJson(route('cashier.webhook'), [
        'id' => 'evt_1', 'object' => 'event', 'type' => 'invoice.payment_succeeded',
        'data' => ['object' => [
            'object' => 'invoice', 'customer' => 'cus_test', 'subscription' => 'sub_1', 'amount_paid' => 1000,
            'lines' => ['data' => [['price' => ['id' => 'price_monthly'], 'period' => ['start' => now()->timestamp, 'end' => now()->addMonth()->timestamp]]]],
        ]],
    ])->assertOk();

    expect($user->packageAssignment()->firstOrFail()->domain_limit)->toBe(4);
});
