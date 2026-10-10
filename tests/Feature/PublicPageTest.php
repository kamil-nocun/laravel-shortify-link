<?php

use App\Actions\SavePageVersion;
use App\Models\Link;
use App\Models\Page;
use Inertia\Testing\AssertableInertia as Assert;

function publishedPage(string $slug = 'privacy', string $title = 'Privacy policy', string $body = '<p>Hello</p>'): Page
{
    $page = Page::factory()->published()->create(['slug' => $slug]);
    app(SavePageVersion::class)($page, $title, $body, null);

    return $page;
}

test('published page renders title and body', function () {
    publishedPage();

    $this->get(route('content.show', 'privacy'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('content/Show')
            ->where('page.title', 'Privacy policy')
            ->where('page.body', '<p>Hello</p>'));
});

test('draft returns 404', function () {
    $page = publishedPage();
    $page->update(['status' => 'draft']);

    $this->get('/content/privacy')->assertNotFound();
});

test('unknown slug returns 404', function () {
    $this->get('/content/missing')->assertNotFound();
});

test('uppercase slug returns 404', function () {
    publishedPage();

    $this->get('/content/Privacy')->assertNotFound();
});

test('published page without a current version returns 404', function () {
    Page::factory()->published()->create(['slug' => 'empty']);

    $this->get('/content/empty')->assertNotFound();
});

test('current version is shown after a newer save', function () {
    $page = publishedPage();
    app(SavePageVersion::class)($page, 'New title', '<p>New</p>', null);

    $this->get('/content/privacy')->assertInertia(fn (Assert $inertia) => $inertia->where('page.title', 'New title'));
});

test('short link with the same code as a slug is unaffected', function () {
    publishedPage('privacy');
    Link::factory()->create(['code' => 'privacy', 'original_url' => 'https://example.com/target']);

    $this->get('/privacy')->assertRedirect('https://example.com/target');
});
