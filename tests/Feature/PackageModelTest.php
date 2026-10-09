<?php

use App\Enums\BillingPeriod;
use App\Models\Package;
use App\Models\PackageAssignment;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

test('cyclic billing periods compute expiry without month overflow', function (BillingPeriod $period, string $expected) {
    expect($period->expiresAt(Carbon::parse('2026-01-31'))->toDateString())->toBe($expected);
})->with([
    [BillingPeriod::Monthly, '2026-02-28'],
    [BillingPeriod::Quarterly, '2026-04-30'],
    [BillingPeriod::Yearly, '2027-01-31'],
]);

test('one time billing period defaults to one calendar month', function () {
    expect(BillingPeriod::OneTime->expiresAt(Carbon::parse('2026-01-31'))->toDateString())->toBe('2026-02-28');
});

test('billing periods map to package price columns', function () {
    expect(BillingPeriod::OneTime->priceColumn())->toBe('price_one_time')
        ->and(BillingPeriod::Monthly->priceColumn())->toBe('price_monthly')
        ->and(BillingPeriod::Quarterly->priceColumn())->toBe('price_quarterly')
        ->and(BillingPeriod::Yearly->priceColumn())->toBe('price_yearly');
});

test('package lists only periods that have a price', function () {
    $package = Package::factory()->create(['price_monthly' => 1000, 'price_yearly' => 10000]);

    expect($package->availablePeriods())->toBe([BillingPeriod::Monthly, BillingPeriod::Yearly])
        ->and($package->priceFor(BillingPeriod::Monthly))->toBe(1000)
        ->and($package->priceFor(BillingPeriod::Quarterly))->toBeNull();
});

test('assignment is inactive when it expires now or earlier', function () {
    $this->travelTo(now());

    expect(PackageAssignment::factory()->make(['expires_at' => now()])->isActive())->toBeFalse()
        ->and(PackageAssignment::factory()->make(['expires_at' => now()->addDay()])->isActive())->toBeTrue()
        ->and(PackageAssignment::factory()->expired()->make()->isActive())->toBeFalse();
});

test('migration seeds a default free package', function () {
    expect(Package::where('is_default', true)->count())->toBe(1)
        ->and(Package::where('is_default', true)->first()->link_limit)->toBe(10);
});

test('database rejects a second default package', function () {
    expect(fn () => Package::factory()->default()->create())->toThrow(UniqueConstraintViolationException::class);
});

test('any number of non default packages is allowed', function () {
    Package::factory()->count(3)->create();

    expect(Package::where('is_default', false)->count())->toBe(3);
});
