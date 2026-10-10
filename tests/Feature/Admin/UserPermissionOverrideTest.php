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
