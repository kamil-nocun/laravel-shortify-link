<?php

use App\Models\Link;
use App\Models\Package;
use App\Models\PackageAssignment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function userWithLimit(int $limit, int $links, ?User $user = null): User
{
    $user ??= User::factory()->create();
    PackageAssignment::factory()->for($user)->for(Package::factory()->create(['link_limit' => $limit]))->create();
    Link::factory()->count($links)->for($user)->create();

    return $user;
}

test('user below the limit creates a link', function () {
    $user = userWithLimit(2, 1);

    $this->actingAs($user)->get(route('links.create'))->assertOk();
    $this->actingAs($user)->post(route('links.store'), ['original_url' => 'https://example.com'])
        ->assertRedirect(route('links.index'));

    expect($user->links()->count())->toBe(2);
});

test('user at the limit is redirected instead of creating but can still manage existing links', function () {
    $user = userWithLimit(1, 1);
    $link = $user->links()->first();

    $this->actingAs($user)->get(route('links.create'))->assertRedirect(route('links.index'));
    $this->actingAs($user)->post(route('links.store'), ['original_url' => 'https://example.com'])->assertRedirect(route('links.index'));
    expect($user->links()->count())->toBe(1);

    $this->actingAs($user)->patch(route('links.update', $link), ['original_url' => 'https://example.org'])->assertRedirect();
    $this->actingAs($user)->patch(route('links.toggle', $link))->assertRedirect();
    $this->actingAs($user)->delete(route('links.destroy', $link))->assertRedirect();

    expect($user->links()->count())->toBe(0);
});

test('user without any package cannot create links', function () {
    Package::query()->delete();

    $this->actingAs(User::factory()->create())->get(route('links.create'))->assertRedirect(route('links.index'));
});

test('admin cannot add a link for a client at the limit', function () {
    $admin = User::factory()->admin()->create();
    $client = userWithLimit(1, 1, User::factory()->assignedTo($admin)->create());

    $this->actingAs($admin)->get(route('admin.users.links.create', $client))->assertForbidden();
    $this->actingAs($admin)->post(route('admin.users.links.store', $client), ['original_url' => 'https://example.com'])->assertForbidden();
});

test('index exposes the quota', function () {
    $user = userWithLimit(5, 2);

    $this->actingAs($user)->get(route('links.index'))
        ->assertInertia(fn (Assert $page) => $page->where('quota.used', 2)->where('quota.limit', 5));
});

test('super admin quota has no limit', function () {
    $this->actingAs(User::factory()->superAdmin()->create())->get(route('links.index'))
        ->assertInertia(fn (Assert $page) => $page->where('quota.limit', null));
});
