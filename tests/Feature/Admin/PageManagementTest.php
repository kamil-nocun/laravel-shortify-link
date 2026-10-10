<?php

use App\Actions\SavePageVersion;
use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->super = User::factory()->superAdmin()->create();
});

function pageWithVersion(string $slug = 'privacy', string $title = 'Privacy', string $body = '<p>One</p>'): Page
{
    $page = Page::factory()->create(['slug' => $slug]);
    app(SavePageVersion::class)($page, $title, $body, null);

    return $page->fresh();
}

test('index lists pages with current title', function () {
    pageWithVersion('privacy', 'Privacy policy');

    $this->actingAs($this->super)->get(route('admin.pages.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/pages/Index')
            ->where('pages.0.slug', 'privacy')
            ->where('pages.0.title', 'Privacy policy')
            ->where('pages.0.status', 'draft'));
});

test('store creates a draft page with first version and redirects to edit', function () {
    $this->actingAs($this->super)->post(route('admin.pages.store'), [
        'slug' => 'privacy', 'title' => 'Privacy', 'body' => '<p>Hi</p>',
    ])->assertRedirect(route('admin.pages.edit', Page::firstWhere('slug', 'privacy')));

    $page = Page::firstWhere('slug', 'privacy');

    expect($page->status)->toBe(PageStatus::Draft)
        ->and($page->versions)->toHaveCount(1)
        ->and($page->currentVersion->created_by)->toBe($this->super->id);
});

test('store rejects invalid slugs', function (string $slug) {
    Page::factory()->create(['slug' => 'taken']);

    $this->actingAs($this->super)->post(route('admin.pages.store'), ['slug' => $slug, 'title' => 'T', 'body' => '<p>a</p>'])
        ->assertSessionHasErrors('slug');
})->with(['Privacy', 'my page', 'a_b', '', '---', 'taken']);

test('store rejects empty title and body', function () {
    $this->actingAs($this->super)->post(route('admin.pages.store'), ['slug' => 'ok', 'title' => '', 'body' => ''])
        ->assertSessionHasErrors(['title', 'body']);
});

test('update with changed body creates a new version', function () {
    $page = pageWithVersion();

    $this->actingAs($this->super)->put(route('admin.pages.update', $page), ['slug' => 'privacy', 'title' => 'Privacy', 'body' => '<p>Two</p>'])
        ->assertRedirect(route('admin.pages.edit', $page));

    expect($page->versions()->count())->toBe(2)->and($page->fresh()->currentVersion->body)->toBe('<p>Two</p>');
});

test('update without content change only changes the slug and creates no version', function () {
    $page = pageWithVersion();

    $this->actingAs($this->super)->put(route('admin.pages.update', $page), ['slug' => 'privacy-policy', 'title' => 'Privacy', 'body' => '<p>One</p>'])
        ->assertRedirect();

    expect($page->versions()->count())->toBe(1)->and($page->fresh()->slug)->toBe('privacy-policy');
});

test('update allows keeping own slug but not another page slug', function () {
    $page = pageWithVersion();
    Page::factory()->create(['slug' => 'cookies']);

    $this->actingAs($this->super)->put(route('admin.pages.update', $page), ['slug' => 'privacy', 'title' => 'P', 'body' => '<p>x</p>'])
        ->assertSessionHasNoErrors();
    $this->actingAs($this->super)->put(route('admin.pages.update', $page), ['slug' => 'cookies', 'title' => 'P', 'body' => '<p>x</p>'])
        ->assertSessionHasErrors('slug');
});

test('status publishes a page and refuses a page without a version', function () {
    $page = pageWithVersion();
    $empty = Page::factory()->create();

    $this->actingAs($this->super)->patch(route('admin.pages.status', $page), ['status' => 'published'])->assertRedirect();
    $this->actingAs($this->super)->patch(route('admin.pages.status', $empty), ['status' => 'published'])->assertSessionHasErrors('status');

    expect($page->fresh()->status)->toBe(PageStatus::Published)->and($empty->fresh()->status)->toBe(PageStatus::Draft);

    $this->actingAs($this->super)->patch(route('admin.pages.status', $page), ['status' => 'draft']);
    expect($page->fresh()->status)->toBe(PageStatus::Draft);
});

test('versions index marks the current version', function () {
    $page = pageWithVersion();
    app(SavePageVersion::class)($page, 'Privacy', '<p>Two</p>', $this->super);

    $this->actingAs($this->super)->get(route('admin.pages.versions.index', $page))
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('admin/pages/Versions')
            ->has('versions', 2)
            ->where('versions', fn ($versions) => collect($versions)->where('is_current', true)->count() === 1));
});

test('version show renders the stored version', function () {
    $page = pageWithVersion();

    $this->actingAs($this->super)->get(route('admin.pages.versions.show', [$page, $page->current_version_id]))
        ->assertInertia(fn (Assert $inertia) => $inertia->component('admin/pages/VersionShow')->where('version.body', '<p>One</p>')->where('page.status', 'draft'));
});

test('restore creates a new version with the old content and keeps history', function () {
    $page = pageWithVersion();
    $old = $page->currentVersion;
    app(SavePageVersion::class)($page, 'Privacy', '<p>Two</p>', null);

    $this->actingAs($this->super)->post(route('admin.pages.versions.restore', [$page, $old]))->assertRedirect(route('admin.pages.edit', $page));

    $page->refresh();
    expect($page->versions()->count())->toBe(3)
        ->and($page->currentVersion->body)->toBe('<p>One</p>')
        ->and($page->current_version_id)->not->toBe($old->id);
});

test('restore of another page version returns 404', function () {
    $page = pageWithVersion('one');
    $other = pageWithVersion('two', 'Two', '<p>Other</p>');

    $this->actingAs($this->super)->post(route('admin.pages.versions.restore', [$page, $other->current_version_id]))->assertNotFound();
    $this->actingAs($this->super)->get(route('admin.pages.versions.show', [$page, $other->current_version_id]))->assertNotFound();

    expect($page->versions()->count())->toBe(1);
});

test('users without pages.manage get 403', function (string $role) {
    $actor = $role === 'client' ? User::factory()->create() : User::factory()->admin()->create();
    $page = pageWithVersion();
    $version = $page->current_version_id;

    $this->actingAs($actor)->get(route('admin.pages.index'))->assertForbidden();
    $this->actingAs($actor)->post(route('admin.pages.store'), ['slug' => 'x', 'title' => 'T', 'body' => '<p>a</p>'])->assertForbidden();
    $this->actingAs($actor)->put(route('admin.pages.update', $page), ['slug' => 'x', 'title' => 'T', 'body' => '<p>a</p>'])->assertForbidden();
    $this->actingAs($actor)->patch(route('admin.pages.status', $page), ['status' => 'published'])->assertForbidden();
    $this->actingAs($actor)->post(route('admin.pages.versions.restore', [$page, $version]))->assertForbidden();
    $this->actingAs($actor)->get(route('admin.pages.create'))->assertForbidden();
    $this->actingAs($actor)->get(route('admin.pages.edit', $page))->assertForbidden();
    $this->actingAs($actor)->get(route('admin.pages.versions.index', $page))->assertForbidden();
    $this->actingAs($actor)->get(route('admin.pages.versions.show', [$page, $version]))->assertForbidden();
})->with(['admin', 'client']);

test('the legal document flag is stored on create and toggled on update', function () {
    $this->actingAs($this->super)->post(route('admin.pages.store'), ['slug' => 'terms', 'title' => 'Terms', 'body' => '<p>a</p>', 'is_legal_document' => true]);
    $page = Page::firstWhere('slug', 'terms');

    expect($page->is_legal_document)->toBeTrue();

    $this->actingAs($this->super)->put(route('admin.pages.update', $page), ['slug' => 'terms', 'title' => 'Terms', 'body' => '<p>a</p>', 'is_legal_document' => false]);
    expect($page->fresh()->is_legal_document)->toBeFalse();

    $this->actingAs($this->super)->post(route('admin.pages.store'), ['slug' => 'about', 'title' => 'About', 'body' => '<p>a</p>']);
    expect(Page::firstWhere('slug', 'about')->is_legal_document)->toBeFalse();
});

test('the edit and index props expose the legal document flag', function () {
    $page = pageWithVersion('terms');
    $page->update(['is_legal_document' => true]);

    $this->actingAs($this->super)->get(route('admin.pages.edit', $page))
        ->assertInertia(fn (Assert $inertia) => $inertia->where('page.is_legal_document', true));
    $this->actingAs($this->super)->get(route('admin.pages.index'))
        ->assertInertia(fn (Assert $inertia) => $inertia->where('pages.0.is_legal_document', true));
});

test('an update that does not send the legal flag keeps it', function () {
    $page = pageWithVersion('terms');
    $page->update(['is_legal_document' => true]);

    $this->actingAs($this->super)->put(route('admin.pages.update', $page), ['slug' => 'terms', 'title' => 'Privacy', 'body' => '<p>One</p>']);

    expect($page->fresh()->is_legal_document)->toBeTrue();
});

test('blank content is rejected but text or an image alone is content', function (string $body, bool $valid) {
    $request = $this->actingAs($this->super)->post(route('admin.pages.store'), ['slug' => 'blank', 'title' => 'Blank', 'body' => $body]);

    $valid ? $request->assertSessionHasNoErrors() : $request->assertSessionHasErrors('body');
    expect(Page::where('slug', 'blank')->exists())->toBe($valid);
})->with([
    'empty paragraph' => ['<p></p>', false],
    'spaces only' => ['<p> </p>', false],
    'non-breaking space' => ['<p>&nbsp;</p>', false],
    'script only' => ['<script>alert(1)</script>', false],
    'text' => ['<p>Hello</p>', true],
    'image only' => ['<img src="https://example.com/a.png" alt="a">', true],
]);

test('blank content is rejected on update too', function () {
    $page = pageWithVersion();

    $this->actingAs($this->super)->put(route('admin.pages.update', $page), ['slug' => 'privacy', 'title' => 'Privacy', 'body' => '<p></p>'])
        ->assertSessionHasErrors('body');
});

test('a failing version save leaves no page behind on create and keeps the slug on update', function () {
    $page = pageWithVersion('stable');
    $failing = Mockery::mock(SavePageVersion::class);
    $failing->shouldReceive('ifChanged')->andThrow(new RuntimeException('boom'));
    $failing->shouldReceive('__invoke')->andThrow(new RuntimeException('boom'));
    app()->instance(SavePageVersion::class, $failing);

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($this->super)->post(route('admin.pages.store'), ['slug' => 'new-page', 'title' => 'New', 'body' => '<p>a</p>']))->toThrow(RuntimeException::class);
    expect(fn () => $this->actingAs($this->super)->put(route('admin.pages.update', $page), ['slug' => 'renamed', 'title' => 'Changed', 'body' => '<p>b</p>']))->toThrow(RuntimeException::class);

    expect(Page::where('slug', 'new-page')->exists())->toBeFalse()
        ->and($page->fresh()->slug)->toBe('stable');
});

test('restoring the current version creates nothing', function () {
    $page = pageWithVersion();

    $this->actingAs($this->super)->post(route('admin.pages.versions.restore', [$page, $page->current_version_id]))->assertRedirect(route('admin.pages.edit', $page));

    expect($page->versions()->count())->toBe(1);
});
