<?php

use App\Enums\BillingPeriod;
use App\Models\Package;
use App\Models\PackageAssignment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->super = User::factory()->superAdmin()->create();
    $this->client = User::factory()->create();
    $this->package = Package::factory()->withPrices()->create(['link_limit' => 50]);
    $this->travelTo(now()->startOfSecond());
});

test('cyclic periods store computed expiry and price snapshot', function (BillingPeriod $period, int $price) {
    $this->actingAs($this->super)->put(route('admin.users.package.update', $this->client), [
        'package_id' => $this->package->id, 'billing_period' => $period->value,
    ])->assertRedirect();

    $assignment = $this->client->packageAssignment()->firstOrFail();
    expect($assignment->price)->toBe($price)
        ->and($assignment->billing_period)->toBe($period)
        ->and($assignment->expires_at->equalTo($period->expiresAt($assignment->starts_at)))->toBeTrue();
})->with([
    [BillingPeriod::Monthly, 1000],
    [BillingPeriod::Quarterly, 2700],
    [BillingPeriod::Yearly, 9900],
]);

test('one time assignment requires and stores a manual expiry', function () {
    $url = route('admin.users.package.update', $this->client);
    $payload = ['package_id' => $this->package->id, 'billing_period' => 'one_time'];

    $this->actingAs($this->super)->put($url, $payload)->assertSessionHasErrors('expires_at');
    $this->actingAs($this->super)->put($url, [...$payload, 'expires_at' => now()->addDays(30)->toDateTimeString()])->assertRedirect();

    $assignment = $this->client->packageAssignment()->firstOrFail();
    expect($assignment->price)->toBe(4900)->and($assignment->expires_at->isSameDay(now()->addDays(30)))->toBeTrue();
});

test('one time expiry must be after the start', function () {
    $this->actingAs($this->super)->put(route('admin.users.package.update', $this->client), [
        'package_id' => $this->package->id, 'billing_period' => 'one_time',
        'starts_at' => now()->addDays(5)->toDateTimeString(), 'expires_at' => now()->addDay()->toDateTimeString(),
    ])->assertSessionHasErrors('expires_at');
});

test('a period without a price is rejected', function () {
    $package = Package::factory()->create(['price_monthly' => 1000]);

    $this->actingAs($this->super)->put(route('admin.users.package.update', $this->client), [
        'package_id' => $package->id, 'billing_period' => 'yearly',
    ])->assertSessionHasErrors('billing_period');

    expect($this->client->packageAssignment()->exists())->toBeFalse();
});

test('the unpriced default package cannot be assigned', function () {
    $free = Package::where('is_default', true)->firstOrFail();

    $this->actingAs($this->super)->put(route('admin.users.package.update', $this->client), [
        'package_id' => $free->id, 'billing_period' => 'monthly',
    ])->assertSessionHasErrors('billing_period');
});

test('assigning again replaces the previous assignment', function () {
    $other = Package::factory()->withPrices()->create();
    PackageAssignment::factory()->for($this->client)->for($this->package)->create();

    $this->actingAs($this->super)->put(route('admin.users.package.update', $this->client), [
        'package_id' => $other->id, 'billing_period' => 'monthly',
    ])->assertRedirect();

    expect(PackageAssignment::where('user_id', $this->client->id)->count())->toBe(1)
        ->and($this->client->packageAssignment()->first()->package_id)->toBe($other->id);
});

test('removing the assignment returns the user to the default package', function () {
    PackageAssignment::factory()->for($this->client)->for($this->package)->create();

    $this->actingAs($this->super)->delete(route('admin.users.package.destroy', $this->client))->assertRedirect();

    expect($this->client->packageAssignment()->exists())->toBeFalse()
        ->and($this->client->package()->is_default)->toBeTrue();
});

test('administrators and users cannot assign packages', function () {
    $payload = ['package_id' => $this->package->id, 'billing_period' => 'monthly'];
    $admin = User::factory()->admin()->create();
    $managed = User::factory()->assignedTo($admin)->create();

    $this->actingAs($admin)->put(route('admin.users.package.update', $managed), $payload)->assertForbidden();
    $this->actingAs($admin)->delete(route('admin.users.package.destroy', $managed))->assertForbidden();
    $this->actingAs($this->client)->put(route('admin.users.package.update', $this->client), $payload)->assertForbidden();
});

test('a package cannot be assigned to a super administrator', function () {
    $other = User::factory()->superAdmin()->create();

    $this->actingAs($this->super)->put(route('admin.users.package.update', $other), [
        'package_id' => $this->package->id, 'billing_period' => 'monthly',
    ])->assertForbidden();
});

test('show exposes package data to super admin only', function () {
    PackageAssignment::factory()->for($this->client)->for($this->package)->create();

    $this->actingAs($this->super)->get(route('admin.users.show', $this->client))
        ->assertInertia(fn (Assert $page) => $page
            ->where('packageAssignment.package_id', $this->package->id)
            ->where('packageAssignment.active', true)
            ->where('effectiveLimit', 50)
            ->has('packages'));

    $admin = User::factory()->admin()->create();
    $managed = User::factory()->assignedTo($admin)->create();

    $this->actingAs($admin)->get(route('admin.users.show', $managed))
        ->assertInertia(fn (Assert $page) => $page
            ->where('packageAssignment', null)
            ->where('packages', [])
            ->where('effectiveLimit', 10));
});

test('a withdrawn package cannot be assigned', function () {
    $this->package->delete();

    $this->actingAs($this->super)->put(route('admin.users.package.update', $this->client), [
        'package_id' => $this->package->id, 'billing_period' => 'monthly',
    ])->assertSessionHasErrors('package_id');
});

test('show flags a withdrawn package and leaves it out of the options', function () {
    PackageAssignment::factory()->for($this->client)->for($this->package)->create();
    $this->package->delete();

    $this->actingAs($this->super)->get(route('admin.users.show', $this->client))
        ->assertInertia(fn (Assert $page) => $page
            ->where('packageAssignment.package_withdrawn', true)
            ->where('packageAssignment.package_name', $this->package->name)
            ->where('packages', fn ($packages) => collect($packages)->doesntContain('id', $this->package->id)));
});

test('show reports an active package as not withdrawn', function () {
    PackageAssignment::factory()->for($this->client)->for($this->package)->create();

    $this->actingAs($this->super)->get(route('admin.users.show', $this->client))
        ->assertInertia(fn (Assert $page) => $page->where('packageAssignment.package_withdrawn', false));
});
