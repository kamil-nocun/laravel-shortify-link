<?php

use App\Models\Link;
use App\Models\LinkClick;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('guests are redirected to login on every links route', function (string $method, string $route, bool $needsLink) {
    $params = $needsLink ? [Link::factory()->create()] : [];

    $this->{$method}(route($route, $params))->assertRedirect(route('login'));
})->with([
    ['get', 'links.index', false],
    ['get', 'links.create', false],
    ['post', 'links.store', false],
    ['get', 'links.edit', true],
    ['patch', 'links.update', true],
    ['delete', 'links.destroy', true],
]);

test('index lists only own links, newest first', function () {
    $old = Link::factory()->for($this->user)->create(['created_at' => now()->subDay()]);
    $new = Link::factory()->for($this->user)->create();
    Link::factory()->create();

    $this->actingAs($this->user)->get(route('links.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('links/Index')
            ->where('links.data.0.id', $new->id)
            ->where('links.data.1.id', $old->id)
            ->where('links.data.0.short_url', $new->short_url)
            ->has('links.data', 2));
});

test('index exposes the number of clicks per link', function () {
    $link = Link::factory()->for($this->user)->create();
    LinkClick::factory()->count(3)->for($link)->create();

    $this->actingAs($this->user)->get(route('links.index'))
        ->assertInertia(fn (Assert $page) => $page->where('links.data.0.clicks_count', 3));
});

test('create and edit render their pages', function () {
    $link = Link::factory()->for($this->user)->create();

    $this->actingAs($this->user)->get(route('links.create'))
        ->assertInertia(fn (Assert $page) => $page->component('links/Create'));
    $this->actingAs($this->user)->get(route('links.edit', $link))
        ->assertInertia(fn (Assert $page) => $page->component('links/Edit')->where('link.id', $link->id)->where('link.short_url', $link->short_url));
});

test('store creates a link with a generated code and ignores a supplied one', function () {
    $this->actingAs($this->user)->post(route('links.store'), [
        'original_url' => 'https://example.com/page',
        'title' => 'Example',
        'is_active' => true,
        'code' => 'hacked',
    ])->assertRedirect(route('links.index'));

    $link = $this->user->links()->sole();
    expect($link->code)->toMatch('/^[0-9A-Za-z]{6}$/')->not->toBe('hacked')
        ->and($link->original_url)->toBe('https://example.com/page');
});

test('update changes fields but never the code', function () {
    $link = Link::factory()->for($this->user)->create(['code' => 'abc123']);

    $this->actingAs($this->user)->patch(route('links.update', $link), [
        'original_url' => 'https://example.org',
        'title' => 'New',
        'is_active' => false,
        'code' => 'zzz999',
    ])->assertRedirect(route('links.index'));

    $link->refresh();
    expect($link->code)->toBe('abc123')
        ->and($link->title)->toBe('New')
        ->and($link->is_active)->toBeFalse();
});

test('update of an expired link keeps its unchanged expires_at', function () {
    $link = Link::factory()->for($this->user)->expired()->create();

    $this->actingAs($this->user)->patch(route('links.update', $link), [
        'original_url' => $link->original_url,
        'title' => 'Renamed',
        'is_active' => true,
        'expires_at' => $link->expires_at->toDateTimeString(),
    ])->assertSessionHasNoErrors();

    expect($link->refresh()->title)->toBe('Renamed');
});

test('update with an unparseable expires_at is rejected', function () {
    $link = Link::factory()->for($this->user)->expired()->create();

    $this->actingAs($this->user)->patch(route('links.update', $link), [
        'original_url' => $link->original_url,
        'is_active' => true,
        'expires_at' => 'not-a-date',
    ])->assertSessionHasErrors('expires_at');
});

test('destroy deletes the link', function () {
    $link = Link::factory()->for($this->user)->create();

    $this->actingAs($this->user)->delete(route('links.destroy', $link))->assertRedirect(route('links.index'));

    $this->assertModelMissing($link);
});

test('other users links redirect to the list with an error toast', function (string $method, string $route) {
    $link = Link::factory()->create();

    $this->actingAs($this->user)->{$method}(route($route, $link))
        ->assertRedirect(route('links.index'));
})->with([
    ['get', 'links.edit'],
    ['patch', 'links.update'],
    ['delete', 'links.destroy'],
]);

test('store validates input', function (array $payload, string $field) {
    $this->actingAs($this->user)->post(route('links.store'), $payload)->assertSessionHasErrors($field);
})->with([
    'missing url' => [[], 'original_url'],
    'javascript scheme' => [['original_url' => 'javascript:alert(1)'], 'original_url'],
    'ftp scheme' => [['original_url' => 'ftp://example.com/file'], 'original_url'],
    'url too long' => [['original_url' => 'https://example.com/'.str_repeat('a', 2050)], 'original_url'],
    'title too long' => [['original_url' => 'https://example.com', 'title' => str_repeat('a', 256)], 'title'],
    'expires in the past' => [['original_url' => 'https://example.com', 'expires_at' => '2000-01-01 00:00:00'], 'expires_at'],
]);

test('store accepts plain http targets', function () {
    $this->actingAs($this->user)->post(route('links.store'), ['original_url' => 'http://example.com'])
        ->assertSessionHasNoErrors();

    expect(Link::where('original_url', 'http://example.com')->exists())->toBeTrue();
});

test('store reports validation errors with form label names', function () {
    $this->actingAs($this->user)->post(route('links.store'), [])
        ->assertSessionHasErrors(['original_url' => 'The target URL field is required.']);
});

test('store accepts an ISO 8601 UTC expires_at as sent by the form', function () {
    $at = now()->addDay()->startOfMinute();

    $this->actingAs($this->user)->post(route('links.store'), [
        'original_url' => 'https://example.com',
        'expires_at' => $at->toIso8601ZuluString('millisecond'),
    ])->assertSessionHasNoErrors();

    expect($this->user->links()->sole()->expires_at->equalTo($at))->toBeTrue();
});
