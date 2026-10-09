<?php

use App\Models\User;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;

/**
 * Every admin.* route as [method, uri]; {user} parameters get a real model.
 *
 * @return list<array{string, string}>
 */
function adminRoutes(): array
{
    $target = User::factory()->create();

    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (LaravelRoute $route) => str_starts_with((string) $route->getName(), 'admin.'))
        ->map(fn (LaravelRoute $route) => [$route->methods()[0], '/'.ltrim(str_replace('{user}', $target->id, $route->uri()), '/')])
        ->values()
        ->all();
}

test('admin routes redirect guests to login', function () {
    foreach (adminRoutes() as [$method, $uri]) {
        $this->call($method, $uri)->assertRedirect(route('login'));
    }
})->skip(fn () => Route::getRoutes()->getByName('admin.users.index') === null, 'No admin routes yet.');

test('admin routes are forbidden for regular users', function () {
    foreach (adminRoutes() as [$method, $uri]) {
        $this->actingAs(User::factory()->create())->call($method, $uri)->assertForbidden();
    }
})->skip(fn () => Route::getRoutes()->getByName('admin.users.index') === null, 'No admin routes yet.');

test('admin routes are not forbidden for admins', function (string $state) {
    foreach (adminRoutes() as [$method, $uri]) {
        expect($this->actingAs(User::factory()->{$state}()->create())->call($method, $uri)->status())->not->toBe(403, "{$method} {$uri} was forbidden");
    }
})->with(['admin', 'superAdmin'])->skip(fn () => Route::getRoutes()->getByName('admin.users.index') === null, 'No admin routes yet.');

test('admin panel is forbidden for regular users', function () {
    $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
})->todo();
