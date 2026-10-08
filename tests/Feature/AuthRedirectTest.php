<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

function protectedGetUris(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => in_array('GET', $route->methods())
            && in_array('auth', $route->gatherMiddleware())
            && ! str_contains($route->uri(), '{'))
        ->map(fn ($route) => '/'.ltrim($route->uri(), '/'))
        ->unique()
        ->values()
        ->all();
}

test('guests are redirected to login from every protected page', function () {
    $uris = protectedGetUris();
    expect($uris)->not->toBeEmpty();
    foreach ($uris as $uri) {
        $this->get($uri)->assertRedirect(route('login'));
    }
});

test('authenticated users are not redirected to login from any protected page', function () {
    $this->actingAs(User::factory()->create());
    foreach (protectedGetUris() as $uri) {
        $response = $this->get($uri);
        expect($response->headers->get('Location'))->not->toBe(route('login'), $uri);
    }
});

test('guests can view the front app page', function () {
    $this->get(route('front_app'))->assertOk();
});
