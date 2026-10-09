<?php

use App\Enums\UserRole;
use App\Models\Link;
use App\Models\LinkClick;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admin sees users with counts and capability flags', function () {
    $admin = User::factory()->admin()->create(['created_at' => now()->subDay()]);
    $user = User::factory()->create(['name' => 'Zed']);
    Link::factory()->for($user)->count(2)->create();

    $this->actingAs($admin)->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/Index')
            ->where('search', '')
            ->has('users.data', 2)
            ->where('users.data.0.name', 'Zed')
            ->where('users.data.0.links_count', 2)
            ->where('users.data.0.can', ['block' => true, 'delete' => true, 'changeRole' => false])
            ->where('users.data.1.can', ['block' => false, 'delete' => false, 'changeRole' => false]));
});

test('super admin can change roles in flags', function () {
    $super = User::factory()->superAdmin()->create(['created_at' => now()->subDay()]);
    User::factory()->create(['name' => 'Zed']);

    $this->actingAs($super)->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('users.data.0.can.changeRole', true));
});

test('regular user cannot list users', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.users.index'))->assertForbidden();
});

test('search matches name and email', function () {
    $admin = User::factory()->admin()->create(['name' => 'Boss', 'email' => 'boss@example.com']);
    User::factory()->create(['name' => 'Alice', 'email' => 'x1@example.com']);
    User::factory()->create(['name' => 'Bob', 'email' => 'alice-mail@example.com']);
    User::factory()->create(['name' => 'Carl', 'email' => 'c@example.com']);

    $this->actingAs($admin)->get(route('admin.users.index', ['search' => 'alice']))
        ->assertInertia(fn (Assert $page) => $page->where('search', 'alice')->has('users.data', 2));
});

test('search treats like wildcards literally', function (string $search, int $expected) {
    $admin = User::factory()->admin()->create(['name' => 'Boss', 'email' => 'boss@example.com']);
    User::factory()->create(['name' => 'a_b', 'email' => 'one@example.com']);
    User::factory()->create(['name' => 'axb', 'email' => 'two@example.com']);
    User::factory()->create(['name' => '100%', 'email' => 'three@example.com']);
    User::factory()->create(['name' => 'back\\slash', 'email' => 'four@example.com']);

    $this->actingAs($admin)->get(route('admin.users.index', ['search' => $search]))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', $expected));
})->with([['a_b', 1], ['%', 1], ['k\\s', 1], ['_', 1]]);

test('users are paginated by fifteen', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(15)->create();

    $this->actingAs($admin)->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 15)->where('users.total', 16));
    $this->actingAs($admin)->get(route('admin.users.index', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 1));
});

test('show returns user, links and stats scoped to the user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $link = Link::factory()->for($user)->create();
    LinkClick::factory()->for($link)->count(2)->create();
    LinkClick::factory()->for(Link::factory()->for($admin))->create();

    $this->actingAs($admin)->get(route('admin.users.show', $user))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/Show')
            ->where('user.id', $user->id)
            ->where('user.can.block', true)
            ->has('links.data', 1)
            ->where('links.data.0.clicks_count', 2)
            ->where('total', 2)
            ->has('series'));
});

test('user links pagination keeps the stats period', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    Link::factory()->for($user)->count(16)->create();

    $this->actingAs($admin)->get(route('admin.users.show', [$user, 'period' => '7d']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('links.next_page_url', fn (string $url) => str_contains($url, 'period=7d')));
});

test('missing or malformed ids give 404', function (string $id) {
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('admin.users.show', $id))->assertNotFound();
    $this->patch(route('admin.users.block', $id))->assertNotFound();
    $this->delete(route('admin.users.destroy', $id))->assertNotFound();
})->with(['not-a-uuid', '019f0000-0000-7000-8000-000000000000']);

test('admin blocks and unblocks a user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)->patch(route('admin.users.block', $user))->assertRedirect();
    expect($user->fresh()->blocked_at)->not->toBeNull();

    $this->actingAs($admin)->delete(route('admin.users.unblock', $user))->assertRedirect();
    expect($user->fresh()->blocked_at)->toBeNull();
});

test('moderation is limited by rank', function (string $actor, string $target, bool $allowed) {
    $actor = $actor === 'admin' ? User::factory()->admin()->create() : User::factory()->superAdmin()->create();
    $target = $target === 'self' ? $actor : User::factory()->admin()->create();

    $this->actingAs($actor)->patch(route('admin.users.block', $target))->assertStatus($allowed ? 302 : 403);
    $this->actingAs($actor)->delete(route('admin.users.destroy', $target))->assertStatus($allowed ? 302 : 403);
})->with([
    'admin blocks admin' => ['admin', 'admin', false],
    'admin blocks self' => ['admin', 'self', false],
    'super admin blocks admin' => ['super', 'admin', true],
]);

test('unblock is forbidden for equal rank', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.users.unblock', User::factory()->admin()->blocked()->create()))
        ->assertForbidden();
});

test('deleting a user removes their links and statistics only', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $other = User::factory()->create();
    $link = Link::factory()->for($user)->create();
    $otherLink = Link::factory()->for($other)->create();
    $click = LinkClick::factory()->for($link)->create();
    $otherClick = LinkClick::factory()->for($otherLink)->create();

    $this->actingAs($admin)->delete(route('admin.users.destroy', $user))->assertRedirect(route('admin.users.index'));

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
    $this->assertDatabaseMissing('links', ['id' => $link->id]);
    $this->assertDatabaseMissing('link_statistics', ['id' => $click->id]);
    $this->assertDatabaseHas('users', ['id' => $other->id]);
    $this->assertDatabaseHas('links', ['id' => $otherLink->id]);
    $this->assertDatabaseHas('link_statistics', ['id' => $otherClick->id]);
});

test('regular user cannot moderate', function () {
    $user = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($user)->patch(route('admin.users.block', $target))->assertForbidden();
    $this->actingAs($user)->delete(route('admin.users.destroy', $target))->assertForbidden();
    $this->actingAs($user)->patch(route('admin.users.role', $target), ['role' => 'admin'])->assertForbidden();
});

test('super admin promotes and demotes a user', function () {
    $super = User::factory()->superAdmin()->create();
    $user = User::factory()->create();

    $this->actingAs($super)->patch(route('admin.users.role', $user), ['role' => 'admin'])->assertRedirect();
    expect($user->fresh()->role)->toBe(UserRole::Admin);

    $this->actingAs($super)->patch(route('admin.users.role', $user), ['role' => 'user'])->assertRedirect();
    expect($user->fresh()->role)->toBe(UserRole::User);
});

test('admin cannot change roles', function () {
    $user = User::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('admin.users.role', $user), ['role' => 'admin'])->assertForbidden();
    expect($user->fresh()->role)->toBe(UserRole::User);
});

test('role must be user or admin', function (mixed $role) {
    $user = User::factory()->create();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->patch(route('admin.users.role', $user), ['role' => $role])->assertSessionHasErrors('role');
    expect($user->fresh()->role)->toBe(UserRole::User);
})->with(['super_admin', 'garbage', '', null]);

test('super admin cannot change own role', function () {
    $super = User::factory()->superAdmin()->create();

    $this->actingAs($super)->patch(route('admin.users.role', $super), ['role' => 'user'])->assertForbidden();
    expect($super->fresh()->role)->toBe(UserRole::SuperAdmin);
});

test('super admin cannot change another super admin role', function () {
    $other = User::factory()->create()->forceFill(['role' => UserRole::SuperAdmin]);
    $other->save();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->patch(route('admin.users.role', $other), ['role' => 'user'])->assertForbidden();
    expect($other->fresh()->role)->toBe(UserRole::SuperAdmin);
});
