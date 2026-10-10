<?php

use App\Actions\SavePageVersion;
use App\Models\PageVersion;
use App\Models\User;
use App\Models\UserConsent;
use App\Support\ConsentDocuments;
use Illuminate\Database\QueryException;

test('current returns the current versions of published required pages only', function () {
    $terms = documentPage('terms');
    documentPage('privacy-policy', published: false);
    documentPage('imprint');

    expect(ConsentDocuments::current()->pluck('id')->all())->toBe([$terms->current_version_id]);
});

test('a published required page without a version is skipped', function () {
    documentPage('terms', withVersion: false);

    expect(ConsentDocuments::current())->toBeEmpty();
});

test('pending lists current versions the user has not accepted', function () {
    $terms = documentPage('terms');
    $privacy = documentPage('privacy-policy');
    $user = User::factory()->create();
    UserConsent::factory()->for($user)->create(['page_version_id' => $terms->current_version_id]);

    expect(ConsentDocuments::pendingFor($user)->pluck('id')->all())->toBe([$privacy->current_version_id]);
});

test('accept stores consents for every current version and is idempotent', function () {
    documentPage('terms');
    documentPage('privacy-policy');
    $user = User::factory()->create();

    ConsentDocuments::accept($user);
    ConsentDocuments::accept($user);

    expect($user->consents()->count())->toBe(2)
        ->and(ConsentDocuments::pendingFor($user))->toBeEmpty();
});

test('a new version of a document makes only that document pending again', function () {
    $terms = documentPage('terms');
    documentPage('privacy-policy');
    $user = User::factory()->create();
    ConsentDocuments::accept($user);

    app(SavePageVersion::class)($terms, 'Terms', '<p>Changed</p>', null);

    expect(ConsentDocuments::pendingFor($user)->pluck('page_id')->all())->toBe([$terms->id]);
});

test('consents are removed with the user but the page versions stay', function () {
    $terms = documentPage('terms');
    $user = User::factory()->create();
    ConsentDocuments::accept($user);

    $user->delete();

    expect(UserConsent::count())->toBe(0)
        ->and(PageVersion::whereKey($terms->current_version_id)->exists())->toBeTrue();
});

test('a version with consents cannot be deleted', function () {
    $terms = documentPage('terms');
    ConsentDocuments::accept(User::factory()->create());

    PageVersion::whereKey($terms->current_version_id)->delete();
})->throws(QueryException::class);
