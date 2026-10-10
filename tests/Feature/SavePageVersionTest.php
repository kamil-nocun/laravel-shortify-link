<?php

use App\Actions\SavePageVersion;
use App\Models\Page;
use App\Models\User;

test('creates a version and points the page at it', function () {
    $page = Page::factory()->create();

    $version = app(SavePageVersion::class)($page, 'Privacy', '<p>Hello</p>', null);

    expect($page->fresh()->current_version_id)->toBe($version->id)
        ->and($version->title)->toBe('Privacy')
        ->and($version->body)->toBe('<p>Hello</p>');
});

test('second save keeps the first version unchanged', function () {
    $page = Page::factory()->create();
    $first = app(SavePageVersion::class)($page, 'One', '<p>1</p>', null);
    $second = app(SavePageVersion::class)($page, 'Two', '<p>2</p>', null);

    expect($page->versions()->count())->toBe(2)
        ->and($first->fresh()->body)->toBe('<p>1</p>')
        ->and($page->fresh()->current_version_id)->toBe($second->id);
});

test('sanitizes the body before storing', function () {
    $page = Page::factory()->create();

    $version = app(SavePageVersion::class)($page, 'T', '<p onclick="x()">a</p><script>alert(1)</script>', null);

    expect($version->fresh()->body)->toBe('<p>a</p>');
});

test('records the author and accepts a null author', function () {
    $page = Page::factory()->create();
    $author = User::factory()->create();

    expect(app(SavePageVersion::class)($page, 'T', '<p>a</p>', $author)->created_by)->toBe($author->id)
        ->and(app(SavePageVersion::class)($page, 'T', '<p>a</p>', null)->created_by)->toBeNull();
});
