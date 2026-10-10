<?php

use App\Models\Domain;
use App\Models\Package;
use App\Models\PackageAssignment;
use App\Models\User;

function userWithDomainLimit(int $limit): User
{
    $user = User::factory()->create();
    $package = Package::factory()->create(['domain_limit' => $limit]);
    PackageAssignment::factory()->for($user)->for($package)->create();

    return $user;
}

test('Free user has a domain limit of zero', function () {
    Package::where('is_default', true)->update(['domain_limit' => 0]);
    $user = User::factory()->create();

    expect($user->domainLimit())->toBe(0)
        ->and($user->canAddDomain())->toBeFalse();
});

test('assignment snapshot wins over later package edits', function () {
    $user = userWithDomainLimit(3);
    $user->activeAssignment()->package->update(['domain_limit' => 10]);

    expect($user->domainLimit())->toBe(3);
});

test('user can add domains up to the limit', function () {
    $user = userWithDomainLimit(2);

    expect($user->canAddDomain())->toBeTrue();
    Domain::factory()->for($user)->create();
    expect($user->canAddDomain())->toBeTrue();
    Domain::factory()->for($user)->create();
    expect($user->canAddDomain())->toBeFalse();
});

test('super admin has no domain limit', function () {
    $admin = User::factory()->superAdmin()->create();

    expect($admin->domainLimit())->toBeNull()
        ->and($admin->canAddDomain())->toBeTrue();
});

test('lowering the limit keeps existing domains', function () {
    $user = userWithDomainLimit(1);
    Domain::factory()->count(2)->for($user)->create();

    expect($user->domains()->count())->toBe(2)
        ->and($user->canAddDomain())->toBeFalse();
});

test("a user's domain scope excludes unverified, disabled and blocked-owner domains", function () {
    $active = Domain::factory()->verified()->create();
    Domain::factory()->create();
    Domain::factory()->verified()->disabledByAdmin()->create();
    Domain::factory()->verified()->for(User::factory()->blocked())->create();

    expect(Domain::active()->pluck('id')->all())->toBe([$active->id])
        ->and($active->isActive())->toBeTrue()
        ->and($active->isVerified())->toBeTrue();
});
