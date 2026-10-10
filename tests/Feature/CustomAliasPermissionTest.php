<?php

use App\Models\Package;
use App\Models\PackageAssignment;
use App\Models\Subscription;
use App\Models\User;

test('a super admin always may use custom aliases', function () {
    expect(User::factory()->superAdmin()->create()->allowsCustomAlias())->toBeTrue();
});

test('an active assignment snapshots the permission', function () {
    $user = User::factory()->create();
    $package = Package::factory()->withCustomAlias()->create();
    PackageAssignment::factory()->for($user)->for($package)->create();

    $package->update(['allows_custom_alias' => false]);

    expect($user->allowsCustomAlias())->toBeTrue();
});

test('without an assignment the default package decides', function () {
    $user = User::factory()->create();

    Package::where('is_default', true)->firstOrFail()->update(['allows_custom_alias' => true]);

    expect($user->allowsCustomAlias())->toBeTrue();
});

test('without an assignment and a default package it is denied', function () {
    Package::where('is_default', true)->update(['is_default' => false]);

    expect(User::factory()->create()->allowsCustomAlias())->toBeFalse();
});

test('a stripe payment copies the permission to the assignment', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_test']);
    Package::factory()->withCustomAlias()->create(['price_monthly' => 1000, 'stripe_price_id_monthly' => 'price_monthly']);
    Subscription::create(['user_id' => $user->id, 'type' => 'default', 'stripe_id' => 'sub_1', 'stripe_status' => 'active', 'stripe_price' => 'price_monthly', 'quantity' => 1]);

    $this->postJson(route('cashier.webhook'), [
        'id' => 'evt_1', 'object' => 'event', 'type' => 'invoice.payment_succeeded',
        'data' => ['object' => [
            'object' => 'invoice', 'customer' => 'cus_test', 'subscription' => 'sub_1', 'amount_paid' => 1000,
            'lines' => ['data' => [['price' => ['id' => 'price_monthly'], 'period' => ['start' => now()->timestamp, 'end' => now()->addMonth()->timestamp]]]],
        ]],
    ])->assertOk();

    expect($user->packageAssignment()->firstOrFail()->allows_custom_alias)->toBeTrue();
});

test('super admin stores a package with the permission, defaulting to false', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    $this->post(route('admin.packages.store'), ['name' => 'Pro', 'link_limit' => 10, 'domain_limit' => 0, 'allows_custom_alias' => true]);
    $this->post(route('admin.packages.store'), ['name' => 'Basic', 'link_limit' => 10, 'domain_limit' => 0]);

    expect(Package::where('name', 'Pro')->firstOrFail()->allows_custom_alias)->toBeTrue()
        ->and(Package::where('name', 'Basic')->firstOrFail()->allows_custom_alias)->toBeFalse();
});
