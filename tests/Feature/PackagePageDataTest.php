<?php

use App\Billing\PackageBilling;
use App\Billing\PackagePageData;
use App\Models\Domain;
use App\Models\Package;
use App\Models\PackageAssignment;
use App\Models\User;

function pageDataWithoutStripe(): PackagePageData
{
    return new PackagePageData(Mockery::mock(PackageBilling::class));
}

test('current returns the package block without calling Stripe', function () {
    $user = User::factory()->create();
    $package = Package::factory()->create(['link_limit' => 50, 'domain_limit' => 3]);
    PackageAssignment::factory()->for($user)->for($package)->create();
    Domain::factory()->for($user)->count(2)->create();

    $current = pageDataWithoutStripe()->current($user);

    expect($current['package_name'])->toBe($package->name)
        ->and($current['link_limit'])->toBe(50)
        ->and($current['domain_limit'])->toBe(3)
        ->and($current['domains_used'])->toBe(2);
});

test('current falls back to the default package for a user without assignment', function () {
    $user = User::factory()->create();

    $current = pageDataWithoutStripe()->current($user);

    expect($current['package_name'])->toBe(Package::where('is_default', true)->value('name'))
        ->and($current['billing_period'])->toBeNull()
        ->and($current['expires_at'])->toBeNull();
});
