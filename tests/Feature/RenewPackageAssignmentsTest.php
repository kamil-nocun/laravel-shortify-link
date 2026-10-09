<?php

use App\Enums\BillingPeriod;
use App\Models\Package;
use App\Models\PackageAssignment;
use App\Models\User;

beforeEach(function () {
    $this->travelTo(now()->startOfSecond());
    $this->package = Package::factory()->withPrices()->create(['link_limit' => 5]);
    $this->user = User::factory()->create();
});

function expiredAssignment(User $user, Package $package, BillingPeriod $period = BillingPeriod::Monthly, int $monthsAgo = 0): PackageAssignment
{
    $expires = now()->subDay()->subMonths($monthsAgo);

    return PackageAssignment::factory()->for($user)->for($package)->create([
        'billing_period' => $period,
        'starts_at' => $expires->copy()->subMonth(),
        'expires_at' => $expires,
        'price' => 1000,
    ]);
}

test('a recurring assignment renews with the current package terms', function () {
    $assignment = expiredAssignment($this->user, $this->package);
    $oldExpiry = $assignment->expires_at;
    $this->package->update(['link_limit' => 8, 'price_monthly' => 1200]);

    $this->artisan('packages:renew')->assertSuccessful();

    $assignment->refresh();
    expect($assignment->starts_at->equalTo($oldExpiry))->toBeTrue()
        ->and($assignment->expires_at->isFuture())->toBeTrue()
        ->and($assignment->link_limit)->toBe(8)
        ->and($assignment->price)->toBe(1200)
        ->and($this->user->linkLimit())->toBe(8);
});

test('assignments that have not expired are untouched', function () {
    $assignment = PackageAssignment::factory()->for($this->user)->for($this->package)->create();
    $before = $assignment->fresh()->toArray();

    $this->artisan('packages:renew')->assertSuccessful();

    expect($assignment->fresh()->toArray())->toBe($before);
});

test('one time assignments never renew', function () {
    $assignment = expiredAssignment($this->user, $this->package, BillingPeriod::OneTime);
    $before = $assignment->fresh()->toArray();

    $this->artisan('packages:renew')->assertSuccessful();

    expect($assignment->fresh()->toArray())->toBe($before);
});

test('a withdrawn package ends the assignment and the user falls back to the default', function () {
    expiredAssignment($this->user, $this->package);
    $this->package->delete();

    $this->artisan('packages:renew')->assertSuccessful();

    expect($this->user->packageAssignment()->exists())->toBeFalse()
        ->and($this->user->linkLimit())->toBe(10);
});

test('a price removed from the package ends the assignment', function () {
    expiredAssignment($this->user, $this->package);
    $this->package->update(['price_monthly' => null]);

    $this->artisan('packages:renew')->assertSuccessful();

    expect($this->user->packageAssignment()->exists())->toBeFalse();
});

test('missed periods are caught up and a second run changes nothing', function () {
    $assignment = expiredAssignment($this->user, $this->package, BillingPeriod::Monthly, 3);

    $this->artisan('packages:renew')->assertSuccessful();
    $after = $assignment->fresh();
    expect($after->expires_at->isFuture())->toBeTrue()
        ->and($after->starts_at->isPast())->toBeTrue()
        ->and($after->starts_at->diffInMonths($after->expires_at))->toEqualWithDelta(1, 0.1);

    $this->artisan('packages:renew')->assertSuccessful();
    expect($assignment->fresh()->toArray())->toBe($after->toArray());
});
