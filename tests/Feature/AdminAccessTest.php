<?php

use App\Models\Domain;
use App\Models\Link;
use App\Models\Package;
use App\Models\PackageAssignment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;

/**
 * Every admin.* route as [method, uri]; {user}, {link}, {domain}, {package} and {role} parameters get a real model.
 *
 * @return list<array{string, string}>
 */
function adminRoutes(): array
{
    $target = User::factory()->create();
    PackageAssignment::factory()->for($target)->for(Package::factory()->create(['domain_limit' => 5]))->create();
    $domain = Domain::factory()->for($target)->create();
    $link = Link::factory()->for($target)->create();
    $package = Package::factory()->create();
    $role = Role::factory()->create();

    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (LaravelRoute $route) => str_starts_with((string) $route->getName(), 'admin.'))
        ->map(fn (LaravelRoute $route) => [$route->methods()[0], '/'.ltrim(str_replace(['{user}', '{link}', '{domain}', '{package}', '{role}'], [$target->id, $link->id, $domain->id, $package->id, $role->id], $route->uri()), '/')])
        ->sortBy(fn (array $route) => $route[0] === 'DELETE' && (str_ends_with($route[1], $target->id) || str_ends_with($route[1], $role->id)))
        ->values()
        ->all();
}

test('admin routes redirect guests to login', function () {
    foreach (adminRoutes() as [$method, $uri]) {
        $this->call($method, $uri)->assertRedirect(route('login'));
    }
});

test('admin routes are forbidden for regular users', function () {
    foreach (adminRoutes() as [$method, $uri]) {
        $this->actingAs(User::factory()->create())->call($method, $uri)->assertForbidden();
    }
});

test('admin routes are not forbidden for admins', function (string $state) {
    foreach (adminRoutes() as [$method, $uri]) {
        if ($state === 'admin' && (str_ends_with($uri, '/role') || str_starts_with($uri, '/admin/administrators') || str_starts_with($uri, '/admin/packages') || str_starts_with($uri, '/admin/roles') || str_ends_with($uri, '/package'))) {
            continue; // role changes, administrator, package and role management and assignment are super admin only (policy)
        }

        expect($this->actingAs(User::factory()->{$state}()->create())->call($method, $uri)->status())->not->toBe(403, "{$method} {$uri} was forbidden");
    }
})->with(['admin', 'superAdmin']);

test('administrators without package, administrator or role permissions are forbidden there', function () {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->assignedTo($admin)->create();
    $this->actingAs($admin);

    $this->get(route('admin.packages.index'))->assertForbidden();
    $this->get(route('admin.administrators.index'))->assertForbidden();
    $this->get(route('admin.roles.index'))->assertForbidden();
    $this->put(route('admin.users.package.update', $client), [])->assertForbidden();
    $this->patch(route('admin.users.role', $client), [])->assertForbidden();
});
