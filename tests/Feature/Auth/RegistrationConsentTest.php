<?php

use App\Actions\SavePageVersion;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function registrationInput(array $overrides = []): array
{
    return ['name' => 'Test User', 'email' => 'test@example.com', 'password' => 'password', 'password_confirmation' => 'password', ...$overrides];
}

test('registration requires accepting the documents when some are published', function () {
    documentPage('terms');

    $this->post(route('register.store'), registrationInput())->assertSessionHasErrors('accept_terms');

    expect(User::count())->toBe(0);
});

test('registration with acceptance stores consents for the current versions', function () {
    $terms = documentPage('terms');
    $privacy = documentPage('privacy-policy');

    $this->post(route('register.store'), registrationInput(['accept_terms' => '1']))->assertRedirect(route('dashboard', absolute: false));

    expect(User::sole()->consents()->pluck('page_version_id')->all())->toEqualCanonicalizing([$terms->current_version_id, $privacy->current_version_id]);
});

test('registration works without the checkbox when nothing is published', function () {
    documentPage('terms', published: false);

    $this->post(route('register.store'), registrationInput())->assertRedirect(route('dashboard', absolute: false));

    expect(User::sole()->consents()->count())->toBe(0);
});

test('the register page exposes the documents to accept', function () {
    documentPage('terms');

    $this->get(route('register'))->assertInertia(fn (Assert $page) => $page
        ->component('auth/Register')
        ->where('consentDocuments.0.slug', 'terms')
        ->where('consentDocuments.0.title', 'Terms'));
});

test('registration records only the versions that were shown on the form', function () {
    $terms = documentPage('terms');
    $shown = $terms->current_version_id;
    app(SavePageVersion::class)($terms, 'Terms', '<p>Newer</p>', null);

    $this->post(route('register.store'), registrationInput(['accept_terms' => '1', 'versions' => [$shown]]));

    $user = User::sole();
    expect($user->consents()->pluck('page_version_id')->all())->toBe([$shown]);
    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('consent.show'));
});

test('the register page exposes the version ids to post back', function () {
    $terms = documentPage('terms');

    $this->get(route('register'))->assertInertia(fn (Assert $page) => $page->where('consentDocuments.0.id', $terms->current_version_id));
});
