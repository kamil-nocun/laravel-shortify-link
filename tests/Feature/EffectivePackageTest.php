<?php

use App\Models\Link;
use App\Models\Package;
use App\Models\PackageAssignment;
use App\Models\User;

test('super admin has no link limit', function () {
    $admin = User::factory()->superAdmin()->create();

    expect($admin->package())->toBeNull()
        ->and($admin->linkLimit())->toBeNull()
        ->and($admin->canCreateLink())->toBeTrue();
});

test('active assignment gives the assigned package limit', function () {
    $user = User::factory()->create();
    $package = Package::factory()->create(['link_limit' => 50]);
    PackageAssignment::factory()->for($user)->for($package)->create();

    expect($user->package()->is($package))->toBeTrue()
        ->and($user->linkLimit())->toBe(50);
});

test('expired assignment falls back to the default package', function () {
    $user = User::factory()->create();
    PackageAssignment::factory()->for($user)->expired()->create();

    expect($user->package()->is_default)->toBeTrue()
        ->and($user->linkLimit())->toBe(10);
});

test('assignment expiring right now is treated as inactive', function () {
    $this->travelTo(now());
    $user = User::factory()->create();
    PackageAssignment::factory()->for($user)->create(['expires_at' => now()]);

    expect($user->package()->is_default)->toBeTrue();
});

test('user without assignment uses the default package', function () {
    expect(User::factory()->create()->linkLimit())->toBe(10);
});

test('without any default package the limit is zero', function () {
    Package::query()->delete();
    $user = User::factory()->create();

    expect($user->package())->toBeNull()
        ->and($user->linkLimit())->toBe(0)
        ->and($user->canCreateLink())->toBeFalse();
});

test('creating links is allowed only below the limit', function () {
    $user = User::factory()->create();
    $package = Package::factory()->create(['link_limit' => 2]);
    PackageAssignment::factory()->for($user)->for($package)->create();

    Link::factory()->for($user)->create();
    expect($user->canCreateLink())->toBeTrue();

    Link::factory()->for($user)->create();
    expect($user->canCreateLink())->toBeFalse();
});
