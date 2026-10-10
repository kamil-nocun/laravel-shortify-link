<?php

use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

test('page casts status to enum and defaults to draft', function () {
    $page = Page::factory()->create();

    expect($page->fresh()->status)->toBe(PageStatus::Draft)
        ->and(Page::factory()->published()->create()->status)->toBe(PageStatus::Published);
});

test('current version relation resolves the pointed version', function () {
    $page = Page::factory()->create();
    PageVersion::factory()->for($page)->create();
    $current = PageVersion::factory()->for($page)->create();
    $page->update(['current_version_id' => $current->id]);

    expect($page->fresh()->currentVersion->is($current))->toBeTrue()
        ->and($page->versions)->toHaveCount(2);
});

test('deleting the author keeps the version with null created_by', function () {
    $author = User::factory()->create();
    $version = PageVersion::factory()->create(['created_by' => $author->id]);

    $author->delete();

    expect($version->fresh()->created_by)->toBeNull();
});

test('slug must be unique at database level', function () {
    Page::factory()->create(['slug' => 'privacy']);

    Page::factory()->create(['slug' => 'privacy']);
})->throws(UniqueConstraintViolationException::class);
