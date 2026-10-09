<?php

use App\Models\Package;
use App\Models\PackageAssignment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->super = User::factory()->superAdmin()->create();
});

test('super admin lists packages with assignment counts', function () {
    $package = Package::factory()->create();
    PackageAssignment::factory()->count(2)->for($package)->create();

    $this->actingAs($this->super)->get(route('admin.packages.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/packages/Index')
            ->where('packages', fn ($packages) => collect($packages)->firstWhere('id', $package->id)['assignments_count'] === 2));
});

test('super admin creates and updates a package', function () {
    $this->actingAs($this->super)->post(route('admin.packages.store'), [
        'name' => 'Pro', 'link_limit' => 100, 'price_monthly' => 1900, 'price_yearly' => null, 'is_default' => false,
    ])->assertRedirect(route('admin.packages.index'));

    $package = Package::where('name', 'Pro')->firstOrFail();
    expect($package->price_monthly)->toBe(1900)->and($package->price_yearly)->toBeNull();

    $this->actingAs($this->super)->put(route('admin.packages.update', $package), ['name' => 'Pro+', 'link_limit' => 200])
        ->assertRedirect(route('admin.packages.index'));

    expect($package->fresh()->name)->toBe('Pro+')->and($package->fresh()->link_limit)->toBe(200);
});

test('only super admin can manage packages', function (string $role) {
    $actor = User::factory()->{$role}()->create();
    $package = Package::factory()->create();

    $this->actingAs($actor)->get(route('admin.packages.index'))->assertForbidden();
    $this->actingAs($actor)->get(route('admin.packages.create'))->assertForbidden();
    $this->actingAs($actor)->post(route('admin.packages.store'), ['name' => 'X', 'link_limit' => 1])->assertForbidden();
    $this->actingAs($actor)->get(route('admin.packages.edit', $package))->assertForbidden();
    $this->actingAs($actor)->put(route('admin.packages.update', $package), ['name' => 'X', 'link_limit' => 1])->assertForbidden();
    $this->actingAs($actor)->delete(route('admin.packages.destroy', $package))->assertForbidden();
})->with(['admin']);

test('regular user cannot reach package routes', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.packages.index'))->assertForbidden();
});

test('package data is validated', function (array $payload, string $field) {
    $this->actingAs($this->super)->post(route('admin.packages.store'), $payload)->assertSessionHasErrors($field);
})->with([
    'missing name' => [['link_limit' => 1], 'name'],
    'negative limit' => [['name' => 'X', 'link_limit' => -1], 'link_limit'],
    'non numeric price' => [['name' => 'X', 'link_limit' => 1, 'price_monthly' => 'abc'], 'price_monthly'],
    'negative price' => [['name' => 'X', 'link_limit' => 1, 'price_yearly' => -5], 'price_yearly'],
]);

test('marking a package as default leaves exactly one default', function () {
    $old = Package::where('is_default', true)->firstOrFail();

    $this->actingAs($this->super)->post(route('admin.packages.store'), ['name' => 'New default', 'link_limit' => 3, 'is_default' => true]);

    expect(Package::where('is_default', true)->count())->toBe(1)
        ->and(Package::where('is_default', true)->first()->name)->toBe('New default')
        ->and($old->fresh()->is_default)->toBeFalse();

    $this->actingAs($this->super)->put(route('admin.packages.update', $old), ['name' => 'Free', 'link_limit' => 10, 'is_default' => true]);

    expect(Package::where('is_default', true)->pluck('id')->all())->toBe([$old->id]);
});

test('default and assigned packages cannot be deleted', function () {
    $default = Package::where('is_default', true)->firstOrFail();
    $used = Package::factory()->create();
    PackageAssignment::factory()->for($used)->create();

    $this->actingAs($this->super)->delete(route('admin.packages.destroy', $default))->assertSessionHasErrors('package');
    $this->actingAs($this->super)->delete(route('admin.packages.destroy', $used))->assertSessionHasErrors('package');

    expect(Package::whereKey([$default->id, $used->id])->count())->toBe(2);
});

test('unused package can be deleted', function () {
    $package = Package::factory()->create();

    $this->actingAs($this->super)->delete(route('admin.packages.destroy', $package))->assertRedirect(route('admin.packages.index'));

    expect(Package::find($package->id))->toBeNull();
});

test('the current default package cannot be unmarked directly', function () {
    $default = Package::where('is_default', true)->firstOrFail();

    $this->actingAs($this->super)->put(route('admin.packages.update', $default), ['name' => 'Free', 'link_limit' => 10, 'is_default' => false])
        ->assertSessionHasErrors('is_default');

    expect($default->fresh()->is_default)->toBeTrue();
});
