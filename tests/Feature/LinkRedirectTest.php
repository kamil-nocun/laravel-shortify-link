<?php

use App\Models\Link;
use Inertia\Testing\AssertableInertia as Assert;

test('active link redirects with 302 to original_url', function () {
    $link = Link::factory()->create();

    $this->get(route('links.redirect', $link->code))
        ->assertStatus(302)
        ->assertRedirect($link->original_url);
});

test('link expiring in the future still redirects', function () {
    $link = Link::factory()->create(['expires_at' => now()->addDay()]);

    $this->get(route('links.redirect', $link->code))->assertRedirect($link->original_url);
});

test('inactive, expired and unknown codes return 404', function (?string $state) {
    $code = 'zzzzzz';

    if ($state !== null) {
        $code = Link::factory()->{$state}()->create()->code;
    }

    $this->get(route('links.redirect', $code))->assertNotFound();
})->with([
    'inactive' => 'inactive',
    'expired' => 'expired',
    'unknown' => null,
]);

test('code lookup is case sensitive', function () {
    Link::factory()->create(['code' => 'Abc123']);

    $this->get('/abc123')->assertNotFound();
});

test('code outside the alphabet returns 404', function () {
    $this->get('/ab-12')->assertNotFound();
});

test('catch-all route does not shadow existing pages', function () {
    $this->get('/login')->assertOk();
    $this->get('/dashboard')->assertRedirect(route('login'));
});

test('refused redirects render the NotFound page with 404', function () {
    $link = Link::factory()->inactive()->create();

    $this->get(route('links.redirect', $link->code))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('errors/NotFound'));
});

test('other 404s are not rendered as the NotFound page', function () {
    $this->get('/no/such/page')
        ->assertNotFound()
        ->assertDontSee('errors\/NotFound', false);
});
