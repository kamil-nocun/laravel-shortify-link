<?php

use App\Enums\Permission;
use App\Models\PermissionRecord;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A role granting the given permissions.
 *
 * @param  list<Permission>  $permissions
 */
function roleWith(array $permissions): Role
{
    $role = Role::factory()->create();
    $role->permissions()->attach(PermissionRecord::whereIn('key', array_map(fn (Permission $permission) => $permission->value, $permissions))->pluck('id'));

    return $role;
}

beforeEach(function () {
    $this->super = User::factory()->superAdmin()->create();
    $this->target = User::factory()->create(['role_id' => roleWith([Permission::LinksManage])->id]);
});

test('saving writes override rows with the granted flag', function () {
    $this->actingAs($this->super)
        ->put(route('admin.users.permissions.update', $this->target), ['granted' => ['links.stats'], 'revoked' => ['links.manage']])
        ->assertRedirect();

    $rows = DB::table('permission_user')->join('permissions', 'permissions.id', '=', 'permission_id')
        ->where('user_id', $this->target->id)->pluck('granted', 'key')->map(fn ($granted) => (bool) $granted)->all();

    expect($rows)->toEqual(['links.stats' => true, 'links.manage' => false]);
});

test('a second save replaces the first', function () {
    $this->actingAs($this->super)->put(route('admin.users.permissions.update', $this->target), ['granted' => ['links.stats'], 'revoked' => []]);
    $this->put(route('admin.users.permissions.update', $this->target), ['granted' => [], 'revoked' => ['links.manage']]);

    expect($this->target->permissionOverrides()->pluck('key')->all())->toBe(['links.manage']);
});

test('effective permissions follow the overrides', function () {
    $this->actingAs($this->super)->put(route('admin.users.permissions.update', $this->target), ['granted' => ['links.stats'], 'revoked' => ['links.manage']]);

    $target = $this->target->fresh();
    expect($target->hasPermission(Permission::LinksManage))->toBeFalse()
        ->and($target->hasPermission(Permission::LinksStats))->toBeTrue();
});

test('invalid override lists are rejected', function (array $payload) {
    $this->actingAs($this->super)
        ->put(route('admin.users.permissions.update', $this->target), $payload)
        ->assertSessionHasErrors();

    expect(DB::table('permission_user')->count())->toBe(0);
})->with([
    'admin.access granted' => [['granted' => ['admin.access'], 'revoked' => []]],
    'admin.access revoked' => [['granted' => [], 'revoked' => ['admin.access']]],
    'overlap' => [['granted' => ['links.stats'], 'revoked' => ['links.stats']]],
    'unknown key' => [['granted' => ['nope'], 'revoked' => []]],
]);

test('actor without users.change_role is forbidden', function () {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->assignedTo($admin)->create();

    $this->actingAs($admin)->put(route('admin.users.permissions.update', $client), ['granted' => [], 'revoked' => []])->assertForbidden();
});

test('an override-granted users.change_role does not reach unmanaged users', function () {
    $admin = User::factory()->admin()->create();
    $admin->permissionOverrides()->attach(PermissionRecord::where('key', 'users.change_role')->first(), ['granted' => true]);
    $unmanaged = User::factory()->create();

    $this->actingAs($admin)->put(route('admin.users.permissions.update', $unmanaged), ['granted' => [], 'revoked' => []])->assertNotFound();

    $managed = User::factory()->assignedTo($admin)->create();
    $this->put(route('admin.users.permissions.update', $managed), ['granted' => ['links.stats'], 'revoked' => []])->assertRedirect();
});

test('a super admin cannot change their own overrides', function () {
    $this->actingAs($this->super)->put(route('admin.users.permissions.update', $this->super), ['granted' => [], 'revoked' => []])->assertForbidden();
});

test('show gives a super admin the override data', function () {
    $this->target->permissionOverrides()->attach(PermissionRecord::where('key', 'links.stats')->first(), ['granted' => true]);
    $superRole = Role::where('is_super', true)->first();

    $this->actingAs($this->super)->get(route('admin.users.show', $this->target))
        ->assertInertia(fn (Assert $page) => $page
            ->where('permissionOverrides', ['granted' => ['links.stats'], 'revoked' => []])
            ->where('rolePermissions', ['links.manage'])
            ->has('permissionGroups')
            ->where('roles', fn ($roles) => collect($roles)->pluck('id')->doesntContain($superRole->id) && collect($roles)->pluck('id')->contains($this->target->role_id)));
});

test('show hides the override data from an administrator without users.change_role', function () {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->assignedTo($admin)->create();

    $this->actingAs($admin)->get(route('admin.users.show', $client))
        ->assertInertia(fn (Assert $page) => $page
            ->where('permissionOverrides', ['granted' => [], 'revoked' => []])
            ->where('rolePermissions', [])
            ->where('permissionGroups', [])
            ->where('roles', []));
});

/**
 * An administrator holding users.change_role only through an override, with a managed client.
 *
 * @return array{User, User}
 */
function overrideAdminWithClient(): array
{
    $admin = User::factory()->admin()->create();
    $admin->permissionOverrides()->attach(PermissionRecord::where('key', 'users.change_role')->first(), ['granted' => true]);

    return [$admin, User::factory()->assignedTo($admin)->create()];
}

test('a non-super actor cannot grant permissions they lack but can grant ones they hold', function () {
    [$admin, $client] = overrideAdminWithClient();

    $this->actingAs($admin)->put(route('admin.users.permissions.update', $client), ['granted' => ['roles.manage'], 'revoked' => []])
        ->assertSessionHasErrors('granted.0');
    expect(DB::table('permission_user')->where('user_id', $client->id)->count())->toBe(0);

    $this->put(route('admin.users.permissions.update', $client), ['granted' => ['users.change_role'], 'revoked' => ['links.manage']])->assertSessionHasNoErrors();
    expect($client->permissionOverrides()->count())->toBe(2);
});

test('a non-super actor cannot assign a role exceeding their own permissions but can assign a subset', function () {
    [$admin, $client] = overrideAdminWithClient();
    $tooMuch = roleWith([Permission::AdminAccess, Permission::RolesManage]);
    $subset = roleWith([Permission::AdminAccess, Permission::UsersChangeRole]);

    $this->actingAs($admin)->patch(route('admin.users.role', $client), ['role_id' => $tooMuch->id])->assertSessionHasErrors('role_id');
    expect($client->fresh()->role_id)->not->toBe($tooMuch->id);

    $this->patch(route('admin.users.role', $client), ['role_id' => $subset->id])->assertSessionHasNoErrors();
    expect($client->fresh()->role_id)->toBe($subset->id);
});

test('a super admin can grant any permission and assign any non-super role', function () {
    $role = roleWith([Permission::AdminAccess, Permission::RolesManage]);

    $this->actingAs($this->super)->put(route('admin.users.permissions.update', $this->target), ['granted' => ['roles.manage'], 'revoked' => []])->assertSessionHasNoErrors();
    $this->patch(route('admin.users.role', $this->target), ['role_id' => $role->id])->assertSessionHasNoErrors();
    expect($this->target->fresh()->role_id)->toBe($role->id);
});

test('nested array input is a validation error, not a server error', function () {
    $this->actingAs($this->super)->put(route('admin.users.permissions.update', $this->target), ['granted' => ['links.stats'], 'revoked' => [['x']]])
        ->assertSessionHasErrors('revoked.0');
});

test('a non-super actor cannot change the overrides of a super admin', function () {
    [$admin] = overrideAdminWithClient();

    $this->actingAs($admin)->put(route('admin.users.permissions.update', $this->super), ['granted' => [], 'revoked' => ['links.manage']])->assertStatus(404);
    expect(DB::table('permission_user')->where('user_id', $this->super->id)->count())->toBe(0);
});
