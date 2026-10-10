<?php

use App\Models\Link;
use App\Models\LinkClick;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AccountCreatedNotification;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;

test('admin sees users with counts and capability flags', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->assignedTo($admin)->create(['name' => 'Zed']);
    User::factory()->superAdmin()->create();
    Link::factory()->for($user)->count(2)->create();

    $this->actingAs($admin)->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/Index')
            ->where('search', '')
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Zed')
            ->where('users.data.0.links_count', 2)
            ->where('users.data.0.can', ['block' => true, 'update' => true, 'createLink' => true, 'manageDomains' => true, 'createDomain' => false, 'delete' => true, 'changeRole' => false, 'assignAdministrators' => false]));
});

test('super admin can change roles in flags', function () {
    $super = User::factory()->superAdmin()->create();
    User::factory()->create(['name' => 'Zed']);

    $this->actingAs($super)->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('users.data.0.can.changeRole', true));
});

test('regular user cannot list users', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.users.index'))->assertForbidden();
});

test('search matches name and email', function () {
    $admin = User::factory()->superAdmin()->create(['name' => 'Boss', 'email' => 'boss@example.com']);
    User::factory()->create(['name' => 'Alice', 'email' => 'x1@example.com']);
    User::factory()->create(['name' => 'Bob', 'email' => 'alice-mail@example.com']);
    User::factory()->create(['name' => 'Carl', 'email' => 'c@example.com']);

    $this->actingAs($admin)->get(route('admin.users.index', ['search' => 'alice']))
        ->assertInertia(fn (Assert $page) => $page->where('search', 'alice')->has('users.data', 2));
});

test('search treats like wildcards literally', function (string $search, int $expected) {
    $admin = User::factory()->superAdmin()->create(['name' => 'Boss', 'email' => 'boss@example.com']);
    User::factory()->create(['name' => 'a_b', 'email' => 'one@example.com']);
    User::factory()->create(['name' => 'axb', 'email' => 'two@example.com']);
    User::factory()->create(['name' => '100%', 'email' => 'three@example.com']);
    User::factory()->create(['name' => 'back\\slash', 'email' => 'four@example.com']);

    $this->actingAs($admin)->get(route('admin.users.index', ['search' => $search]))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', $expected));
})->with([['a_b', 1], ['%', 1], ['k\\s', 1], ['_', 1]]);

test('users are paginated by fifteen', function () {
    $admin = User::factory()->superAdmin()->create();
    User::factory()->count(16)->create();

    $this->actingAs($admin)->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 15)->where('users.total', 16));
    $this->actingAs($admin)->get(route('admin.users.index', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 1));
});

test('show returns user, links and stats scoped to the user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->assignedTo($admin)->create();
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
    $user = User::factory()->assignedTo($admin)->create();
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
    $user = User::factory()->assignedTo($admin)->create();

    $this->actingAs($admin)->patch(route('admin.users.block', $user))->assertRedirect();
    expect($user->fresh()->blocked_at)->not->toBeNull();

    $this->actingAs($admin)->delete(route('admin.users.unblock', $user))->assertRedirect();
    expect($user->fresh()->blocked_at)->toBeNull();
});

test('moderation is limited by rank', function (string $actor, string $target, bool $allowed) {
    $actor = $actor === 'admin' ? User::factory()->admin()->create() : User::factory()->superAdmin()->create();
    $target = $target === 'self' ? $actor : User::factory()->admin()->create();

    $this->actingAs($actor)->patch(route('admin.users.block', $target))->assertStatus($allowed ? 302 : 404);
    $this->actingAs($actor)->delete(route('admin.users.destroy', $target))->assertStatus($allowed ? 302 : 404);
})->with([
    'admin blocks admin' => ['admin', 'admin', false],
    'admin blocks self' => ['admin', 'self', false],
    'super admin blocks admin' => ['super', 'admin', true],
]);

test('unblock is forbidden for equal rank', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.users.unblock', User::factory()->admin()->blocked()->create()))
        ->assertNotFound();
});

test('deleting a user removes their links and statistics only', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->assignedTo($admin)->create();
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
    $this->actingAs($user)->patch(route('admin.users.role', $target), ['role_id' => roleId(PermissionCatalog::ADMINISTRATOR_ROLE)])->assertForbidden();
});

test('super admin promotes and demotes a user', function () {
    $super = User::factory()->superAdmin()->create();
    $user = User::factory()->create();

    $this->actingAs($super)->patch(route('admin.users.role', $user), ['role_id' => roleId(PermissionCatalog::ADMINISTRATOR_ROLE)])->assertRedirect();
    expect($user->fresh()->role->name)->toBe(PermissionCatalog::ADMINISTRATOR_ROLE);

    $this->actingAs($super)->patch(route('admin.users.role', $user), ['role_id' => roleId(PermissionCatalog::CLIENT_ROLE)])->assertRedirect();
    expect($user->fresh()->role->name)->toBe(PermissionCatalog::CLIENT_ROLE);
});

test('admin cannot change roles', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->assignedTo($admin)->create();

    $this->actingAs($admin)
        ->patch(route('admin.users.role', $user), ['role_id' => roleId(PermissionCatalog::ADMINISTRATOR_ROLE)])->assertForbidden();
    expect($user->fresh()->role->name)->toBe(PermissionCatalog::CLIENT_ROLE);
});

test('role must be an existing non-super role', function (mixed $role) {
    $user = User::factory()->create();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->patch(route('admin.users.role', $user), ['role_id' => $role])->assertSessionHasErrors('role_id');
    expect($user->fresh()->role->name)->toBe(PermissionCatalog::CLIENT_ROLE);
})->with([
    'super role' => fn () => roleId(PermissionCatalog::SUPER_ADMIN_ROLE),
    'garbage' => 'garbage',
    'empty' => '',
    'null' => null,
]);

test('super admin cannot change own role', function () {
    $super = User::factory()->superAdmin()->create();

    $this->actingAs($super)->patch(route('admin.users.role', $super), ['role_id' => roleId(PermissionCatalog::CLIENT_ROLE)])->assertForbidden();
    expect($super->fresh()->isSuperAdmin())->toBeTrue();
});

test('super admin cannot change another super admin role', function () {
    $other = User::factory()->create()->forceFill(['role_id' => roleId(PermissionCatalog::SUPER_ADMIN_ROLE)]);
    $other->save();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->patch(route('admin.users.role', $other), ['role_id' => roleId(PermissionCatalog::CLIENT_ROLE)])->assertForbidden();
    expect($other->fresh()->isSuperAdmin())->toBeTrue();
});

test('admin edits a user profile and password is optional', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->assignedTo($admin)->create();
    $oldHash = $user->password;

    $this->actingAs($admin)->get(route('admin.users.edit', $user))
        ->assertInertia(fn (Assert $page) => $page->component('admin/users/Edit')->where('user.id', $user->id));

    $this->actingAs($admin)->put(route('admin.users.update', $user), ['name' => 'New', 'email' => 'new@example.com'])
        ->assertRedirect(route('admin.users.show', $user));

    $user->refresh();
    expect($user->name)->toBe('New')
        ->and($user->email)->toBe('new@example.com')
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->password)->toBe($oldHash);
});

test('admin can set a new password for a user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->assignedTo($admin)->create();

    $this->actingAs($admin)->put(route('admin.users.update', $user), [
        'name' => $user->name,
        'email' => $user->email,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertRedirect();

    expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue()
        ->and($user->fresh()->email_verified_at)->not->toBeNull();
});

test('update validates input and keeps email unique', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->assignedTo($admin)->create();
    $other = User::factory()->create();

    $this->actingAs($admin)->put(route('admin.users.update', $user), ['name' => '', 'email' => $other->email])
        ->assertSessionHasErrors(['name', 'email']);
    $this->actingAs($admin)->put(route('admin.users.update', $user), [
        'name' => 'X', 'email' => $user->email, 'password' => 'new-password-123', 'password_confirmation' => 'nope',
    ])->assertSessionHasErrors('password');
});

test('editing is limited by rank', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->admin()->create();
    $payload = ['name' => 'X', 'email' => 'x@example.com'];

    $this->actingAs($admin)->get(route('admin.users.edit', $target))->assertNotFound();
    $this->actingAs($admin)->put(route('admin.users.update', $target), $payload)->assertNotFound();
    $this->actingAs(User::factory()->create())->put(route('admin.users.update', $target), $payload)->assertForbidden();
});

test('admin creates a verified user with an unknown password and e-mails a set-password link', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.users.store'), ['name' => 'New', 'email' => 'new@example.com'])
        ->assertRedirect(route('admin.users.index'));

    $user = User::where('email', 'new@example.com')->firstOrFail();
    expect($user->role->name)->toBe(PermissionCatalog::CLIENT_ROLE)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->password)->not->toBeEmpty();

    Notification::assertSentTo($user, AccountCreatedNotification::class, function ($notification) use ($user) {
        return Password::tokenExists($user, $notification->token);
    });
});

test('creating a user validates name and unique email', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    User::factory()->create(['email' => 'taken@example.com']);

    $this->actingAs($admin)->post(route('admin.users.store'), ['name' => '', 'email' => 'taken@example.com'])
        ->assertSessionHasErrors(['name', 'email']);

    Notification::assertNothingSent();
});

test('regular user and guest cannot create users', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.users.create'))->assertForbidden();
    $this->post(route('admin.users.store'), ['name' => 'X', 'email' => 'x@example.com'])->assertForbidden();
    auth()->logout();
    $this->post(route('admin.users.store'), [])->assertRedirect(route('login'));
});

test('admin adds a link owned by the user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->assignedTo($admin)->create();

    $this->actingAs($admin)->post(route('admin.users.links.store', $user), ['original_url' => 'https://example.com/a', 'title' => 'A', 'is_active' => true])
        ->assertRedirect(route('admin.users.show', $user));

    $link = $user->links()->firstOrFail();
    expect($link->original_url)->toBe('https://example.com/a')
        ->and($link->code)->toMatch('/^[0-9A-Za-z]{6}$/');
    expect(Link::count())->toBe(1);
});

test('adding a user link validates the url', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->assignedTo($admin)->create();

    $this->actingAs($admin)->post(route('admin.users.links.store', $user), ['original_url' => 'not-a-url'])
        ->assertSessionHasErrors('original_url');
});

test('link creation for another user follows the role hierarchy', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();
    $super = User::factory()->superAdmin()->create();
    $payload = ['original_url' => 'https://example.com'];

    $this->actingAs($admin)->get(route('admin.users.links.create', $otherAdmin))->assertNotFound();
    $this->actingAs($admin)->post(route('admin.users.links.store', $otherAdmin), $payload)->assertNotFound();
    $this->actingAs($super)->post(route('admin.users.links.store', $otherAdmin), $payload)->assertRedirect();
    $this->actingAs(User::factory()->create())->get(route('admin.users.links.create', $admin))->assertForbidden();
});

test('blocking records the acting admin, unblocking clears it, and the index shows the name', function () {
    $admin = User::factory()->admin()->create(['name' => 'Alice Admin']);
    $user = User::factory()->assignedTo($admin)->create();

    $this->actingAs($admin)->patch(route('admin.users.block', $user));
    expect($user->fresh()->blocked_by)->toBe($admin->id);

    $this->actingAs($admin)->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page->where('users.data.0.blocked_by_name', 'Alice Admin'));

    $this->actingAs($admin)->delete(route('admin.users.unblock', $user));
    expect($user->fresh())->blocked_at->toBeNull()->blocked_by->toBeNull();
});

test('deleting the blocking admin nulls blocked_by', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create(['blocked_at' => now()]);
    $user->forceFill(['blocked_by' => $admin->id])->save();

    $admin->delete();

    expect($user->fresh())->blocked_by->toBeNull()->blocked_at->not->toBeNull();
});

test('creating a user takes a client role only from a role changer', function () {
    Notification::fake();
    $custom = Role::factory()->create();
    $admin = User::factory()->admin()->create();
    $super = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->post(route('admin.users.store'), ['name' => 'A', 'email' => 'a@example.com', 'role_id' => $custom->id])->assertRedirect();
    $this->actingAs($super)->post(route('admin.users.store'), ['name' => 'B', 'email' => 'b@example.com', 'role_id' => $custom->id])->assertRedirect();
    $this->actingAs($super)->post(route('admin.users.store'), ['name' => 'C', 'email' => 'c@example.com', 'role_id' => roleId(PermissionCatalog::ADMINISTRATOR_ROLE)])
        ->assertSessionHasErrors('role_id');

    expect(User::where('email', 'a@example.com')->sole()->role->name)->toBe(PermissionCatalog::CLIENT_ROLE)
        ->and(User::where('email', 'b@example.com')->sole()->role_id)->toBe($custom->id)
        ->and(User::where('email', 'c@example.com')->exists())->toBeFalse();
});

test('shared auth props and role options expose the role kind', function () {
    $super = User::factory()->superAdmin()->create();
    $user = User::factory()->create();

    $this->actingAs($super)->get(route('admin.users.show', $user))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.isAdmin', true)
            ->where('auth.isSuperAdmin', true)
            ->where('user.role', PermissionCatalog::CLIENT_ROLE)
            ->where('roleOptions', [
                'client' => roleId(PermissionCatalog::CLIENT_ROLE),
                'administrator' => roleId(PermissionCatalog::ADMINISTRATOR_ROLE),
            ]));
});

test('users index resolves roles and permissions without a query per row', function (string $viewerState) {
    $viewer = User::factory()->{$viewerState}()->create();
    $count = function () use ($viewer): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($viewer->fresh())->get(route('admin.users.index'))->assertOk();

        return collect(DB::getQueryLog())->filter(fn (array $query) => preg_match('/from `(roles|permissions)`/', $query['query']))->count();
    };

    User::factory()->assignedTo($viewer)->create();
    $before = $count();
    User::factory()->count(5)->assignedTo($viewer)->create();

    expect($count())->toBe($before);
})->with(['superAdmin', 'admin']);
