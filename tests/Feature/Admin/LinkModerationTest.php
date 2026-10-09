<?php

use App\Models\Link;
use App\Models\LinkClick;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['inertia.testing.ensure_pages_exist' => false]);
    $this->admin = User::factory()->admin()->create();
    $this->link = Link::factory()->for(User::factory()->admin()->create())->create();
});

test('admin disables and enables a link without touching is_active', function () {
    $this->actingAs($this->admin)->patch(route('admin.links.disable', $this->link))->assertRedirect();
    expect($this->link->fresh())->disabled_by_admin_at->not->toBeNull()->is_active->toBeTrue();

    $this->actingAs($this->admin)->delete(route('admin.links.enable', $this->link))->assertRedirect();
    expect($this->link->fresh())->disabled_by_admin_at->toBeNull()->is_active->toBeTrue();
});

test('a disabled link stops redirecting until enabled', function () {
    $this->actingAs($this->admin)->patch(route('admin.links.disable', $this->link));
    $this->get(route('links.redirect', $this->link->code))->assertStatus(404);

    $this->actingAs($this->admin)->delete(route('admin.links.enable', $this->link));
    $this->get(route('links.redirect', $this->link->code))->assertRedirect($this->link->url);
});

test('regular users and guests cannot moderate', function () {
    $this->actingAs(User::factory()->create())->patch(route('admin.links.disable', $this->link))->assertForbidden();
    auth()->logout();
    $this->patch(route('admin.links.disable', $this->link))->assertRedirect(route('login'));
    expect($this->link->fresh()->disabled_by_admin_at)->toBeNull();
});

test('unknown links are 404', function () {
    $this->actingAs($this->admin)->patch('/admin/links/nope/disable')->assertNotFound();
    $this->actingAs($this->admin)->patch('/admin/links/'.fake()->uuid().'/disable')->assertNotFound();
});

test('admin views stats of any link, as page and as json', function () {
    LinkClick::factory()->for($this->link)->count(2)->create();

    $this->actingAs($this->admin)->get(route('admin.links.stats', $this->link))
        ->assertInertia(fn (Assert $page) => $page
            ->component('links/Stats')
            ->where('total', 2)
            ->where('dataUrl', route('admin.links.stats.data', $this->link))
            ->where('backUrl', route('admin.users.show', $this->link->user_id)));

    $data = $this->actingAs($this->admin)->getJson(route('admin.links.stats.data', $this->link))
        ->assertOk()->assertJsonStructure(['period', 'series', 'total', 'countries'])->json();
    expect($data['total'])->toBe(2);
});

test('regular users keep denied on foreign link stats and get own urls', function () {
    $this->actingAs(User::factory()->create())->get(route('links.stats', $this->link))->assertRedirect(route('links.index'));

    $owner = $this->link->user;
    $mine = Link::factory()->for($owner)->create();
    $this->actingAs($owner)->get(route('links.stats', $mine))
        ->assertInertia(fn (Assert $page) => $page
            ->where('dataUrl', route('links.stats.data', $mine))
            ->where('backUrl', route('links.index')));
});
