<?php

use App\Models\Domain;
use App\Models\Link;
use App\Models\LinkClick;
use App\Models\Package;
use App\Models\PackageAssignment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('client dashboard counts only the client\'s own links and clicks', function () {
    $client = User::factory()->create();
    $own = Link::factory()->for($client)->create();
    LinkClick::factory()->for($own)->count(2)->create();
    LinkClick::factory()->for(Link::factory()->for(User::factory()->create()))->count(5)->create();

    $this->actingAs($client)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('counters.links', 1)
            ->where('counters.clicks', 2)
            ->has('topLinks', 1)
            ->where('topLinks.0.id', $own->id)
            ->missing('topUsers'));
});

test('client dashboard exposes the package card data', function () {
    $client = User::factory()->create();
    $package = Package::factory()->create(['link_limit' => 20, 'domain_limit' => 2]);
    PackageAssignment::factory()->for($client)->for($package)->create();
    Link::factory()->for($client)->count(3)->create();
    Domain::factory()->for($client)->create();

    $this->actingAs($client)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('package.package_name', $package->name)
            ->where('package.link_limit', 20)
            ->where('package.links_used', 3)
            ->where('package.domain_limit', 2)
            ->where('package.domains_used', 1));
});

test('client without an assigned package sees the default package', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('package.package_name', Package::where('is_default', true)->value('name')));
});

test('super admin gets no package card', function () {
    $this->actingAs(User::factory()->superAdmin()->create())->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('package', null));
});

test('period parameter is validated', function () {
    $client = User::factory()->create();

    $this->actingAs($client)->get(route('dashboard', ['period' => '7d']))->assertOk();
    $this->actingAs($client)->get(route('dashboard', ['period' => 'bogus']))->assertSessionHasErrors('period');
});

test('shared props flag admins so the mode switcher shows', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.isAdmin', true));
})->with(['admin', 'superAdmin']);

test('shared props do not flag regular users', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.isAdmin', false));
});
