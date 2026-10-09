<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

function adminRouteUris(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'admin.') && in_array('GET', $route->methods(), true))
        ->map(fn ($route) => '/'.ltrim($route->uri(), '/'))
        ->values()
        ->all();
}

test('admin routes redirect guests to login', function () {
    foreach (adminRouteUris() as $uri) {
        $this->get($uri)->assertRedirect(route('login'));
    }
})->skip(fn () => adminRouteUris() === [], 'No admin routes yet.');

test('admin routes are forbidden for regular users', function () {
    foreach (adminRouteUris() as $uri) {
        $this->actingAs(User::factory()->create())->get($uri)->assertForbidden();
    }
})->skip(fn () => adminRouteUris() === [], 'No admin routes yet.');

test('admin routes are open for admins', function (string $state) {
    foreach (adminRouteUris() as $uri) {
        $this->actingAs(User::factory()->{$state}()->create())->get($uri)->assertSuccessful();
    }
})->with(['admin', 'superAdmin'])->skip(fn () => adminRouteUris() === [], 'No admin routes yet.');

test('admin panel is forbidden for regular users', function () {
    $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
})->todo();
