<?php

use App\Actions\SavePageVersion;
use App\Models\Link;
use App\Models\User;
use App\Support\ConsentDocuments;
use Inertia\Testing\AssertableInertia as Assert;

test('a user with pending documents is redirected to the consent page', function () {
    documentPage('terms');

    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertRedirect(route('consent.show'));
});

test('a json request gets 409', function () {
    documentPage('terms');

    $this->actingAs(User::factory()->create())->getJson('/dashboard')->assertStatus(409);
});

test('after accepting the user reaches the dashboard', function () {
    documentPage('terms');
    documentPage('privacy-policy');
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('consent.store'))->assertRedirect(route('dashboard'));

    expect($user->consents()->count())->toBe(2);
    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

test('a new version of a document asks again', function () {
    $terms = documentPage('terms');
    $user = User::factory()->create();
    ConsentDocuments::accept($user);
    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    app(SavePageVersion::class)($terms, 'Terms', '<p>Changed</p>', null);

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('consent.show'));
});

test('a user without published documents is not blocked', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk();
});

test('a draft or empty document does not block', function () {
    documentPage('terms', published: false);
    documentPage('privacy-policy', withVersion: false);

    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk();
});

test('exempt routes work without consent', function () {
    documentPage('terms');
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'))->assertRedirect();
    $this->assertGuest();

    $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'wrong'])->assertSessionHasErrors('password');
    $this->actingAs($user)->get(route('privacy.export'))->assertOk();
});

test('the short link redirect is unaffected', function () {
    documentPage('terms');
    $link = Link::factory()->create();

    $this->actingAs(User::factory()->create())->get(route('links.redirect', $link->code))->assertRedirect($link->original_url);
});

test('the consent page lists pending documents', function () {
    $terms = documentPage('terms');

    $this->actingAs(User::factory()->create())->get(route('consent.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('consent/Show')
            ->where('documents.0.slug', 'terms')
            ->where('documents.0.id', $terms->current_version_id));
});

test('the consent page redirects away when nothing is pending', function () {
    $this->actingAs(User::factory()->create())->get(route('consent.show'))->assertRedirect(route('dashboard'));
});

test('accepting twice does not fail or duplicate', function () {
    documentPage('terms');
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('consent.store'));
    $this->actingAs($user)->post(route('consent.store'))->assertRedirect();

    expect($user->consents()->count())->toBe(1);
});

test('guests cannot open the consent page', function () {
    $this->get(route('consent.show'))->assertRedirect(route('login'));
});

test('the documents themselves stay readable while consent is pending', function () {
    documentPage('terms');
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('content.show', 'terms'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('content/Show'));
});

test('a super admin is never asked for consent but other administrators are', function () {
    documentPage('terms');
    documentPage('privacy-policy');
    documentPage('cookies-policy');

    $this->actingAs(User::factory()->superAdmin()->create())->get(route('dashboard'))->assertOk();
    $this->actingAs(User::factory()->admin()->create())->get(route('dashboard'))->assertRedirect(route('consent.show'));
});

test('the cookies policy is one of the documents to accept', function () {
    documentPage('cookies-policy');

    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertRedirect(route('consent.show'));
});
