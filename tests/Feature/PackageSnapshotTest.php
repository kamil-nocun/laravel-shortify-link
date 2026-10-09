<?php

use App\Models\Package;
use App\Models\PackageAssignment;
use App\Models\User;

test('editing a package does not change the limit of an active assignment', function () {
    $user = User::factory()->create();
    $package = Package::factory()->create(['link_limit' => 20]);
    PackageAssignment::factory()->for($user)->for($package)->create();

    $package->update(['link_limit' => 5]);

    expect($user->linkLimit())->toBe(20);
});

test('assigning a package stores its limit as a snapshot', function () {
    $super = User::factory()->superAdmin()->create();
    $client = User::factory()->create();
    $package = Package::factory()->withPrices()->create(['link_limit' => 42]);

    $this->actingAs($super)->put(route('admin.users.package.update', $client), [
        'package_id' => $package->id, 'billing_period' => 'monthly',
    ])->assertRedirect();

    expect($client->packageAssignment()->first()->link_limit)->toBe(42);
});

test('editing the default package applies immediately to users without assignment', function () {
    $user = User::factory()->create();

    Package::where('is_default', true)->firstOrFail()->update(['link_limit' => 3]);

    expect($user->linkLimit())->toBe(3);
});

test('a withdrawn package keeps serving its assignment until it ends', function () {
    $user = User::factory()->create();
    $package = Package::factory()->create(['link_limit' => 20]);
    PackageAssignment::factory()->for($user)->for($package)->create();

    $package->delete();

    expect($user->linkLimit())->toBe(20)
        ->and($user->packageAssignment()->first()->package->trashed())->toBeTrue();
});
