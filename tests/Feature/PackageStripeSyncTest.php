<?php

use App\Models\Package;
use App\Models\PackageAssignment;
use App\Models\User;

beforeEach(function () {
    $this->super = User::factory()->superAdmin()->create();
});

test('saving a new package syncs it with Stripe', function () {
    $this->actingAs($this->super)->post(route('admin.packages.store'), ['name' => 'Pro', 'link_limit' => 50, 'domain_limit' => 0, 'price_monthly' => 1900])
        ->assertRedirect(route('admin.packages.index'));

    expect($this->billing->called())->toBe(['syncPackage']);
});

test('editing a package syncs it again', function () {
    $package = Package::factory()->create();

    $this->actingAs($this->super)->put(route('admin.packages.update', $package), ['name' => 'Renamed', 'link_limit' => 5, 'domain_limit' => 0, 'price_monthly' => 2500])
        ->assertRedirect();

    expect($this->billing->calls)->toBe([['syncPackage', [$package->id]]]);
});

test('the default package is never synced', function () {
    $default = Package::where('is_default', true)->firstOrFail();

    $this->actingAs($this->super)->put(route('admin.packages.update', $default), ['name' => 'Free', 'link_limit' => 12, 'domain_limit' => 0, 'is_default' => true])
        ->assertRedirect();

    expect($this->billing->called())->toBe([]);
});

test('a failing sync rolls the package back and reports an error', function () {
    $this->billing->failSync = true;

    $this->actingAs($this->super)->post(route('admin.packages.store'), ['name' => 'Pro', 'link_limit' => 50, 'domain_limit' => 0])
        ->assertSessionHasErrors('stripe');

    expect(Package::where('name', 'Pro')->exists())->toBeFalse();
});

test('withdrawing and deleting a package retire it in Stripe', function () {
    $withdrawn = Package::factory()->create();
    PackageAssignment::factory()->for($withdrawn)->create();
    $unused = Package::factory()->create();

    $this->actingAs($this->super)->delete(route('admin.packages.destroy', $withdrawn));
    $this->actingAs($this->super)->delete(route('admin.packages.destroy', $unused));

    expect($this->billing->calls)->toBe([['retirePackage', [$withdrawn->id]], ['retirePackage', [$unused->id]]]);
});
