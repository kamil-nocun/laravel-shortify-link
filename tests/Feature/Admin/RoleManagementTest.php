<?php

use App\Enums\Permission;
use App\Models\PermissionRecord;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A custom role granting the given permissions.
 *
 * @param  list<Permission>  $permissions
 */
function roleGranting(array $permissions): Role
{
    $role = Role::factory()->create();
    $role->permissions()->attach(PermissionRecord::whereIn('key', array_map(fn (Permission $permission) => $permission->value, $permissions))->pluck('id'));

    return $role;
}

beforeEach(function () {
    $this->super = User::factory()->superAdmin()->create();
});

test('roles manager lists roles with permission and user counts', function () {
    $role = roleGranting([Permission::LinksManage, Permission::LinksStats]);
    User::factory()->count(2)->create(['role_id' => $role->id]);

    $this->actingAs($this->super)->get(route('admin.roles.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/roles/Index')
            ->where('roles', fn ($roles) => collect($roles)->firstWhere('id', $role->id) === [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'is_super' => false,
                'is_default' => false,
                'permissions_count' => 2,
                'users_count' => 2,
            ]));
});

test('create and edit pages receive permissions grouped from the permissions table', function () {
    $role = roleGranting([Permission::LinksManage]);

    $this->actingAs($this->super)->get(route('admin.roles.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/roles/Create')
            ->where('permissionGroups', fn ($groups) => collect($groups)->firstWhere('group', 'Roles')['permissions'] === [['key' => 'roles.manage', 'label' => 'Manage roles']]
                && collect($groups)->flatMap(fn ($group) => $group['permissions'])->count() === count(Permission::cases())));

    $this->actingAs($this->super)->get(route('admin.roles.edit', $role))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/roles/Edit')
            ->where('role.id', $role->id)
            ->where('role.permissions', ['links.manage'])
            ->has('permissionGroups'));
});

test('roles manager creates a role with permissions', function () {
    $this->actingAs($this->super)->post(route('admin.roles.store'), [
        'name' => 'Support', 'description' => 'Helps clients', 'permissions' => ['admin.access', 'users.view'],
    ])->assertRedirect(route('admin.roles.index'));

    $role = Role::where('name', 'Support')->firstOrFail();
    expect($role->description)->toBe('Helps clients')
        ->and($role->is_super)->toBeFalse()
        ->and($role->is_default)->toBeFalse()
        ->and($role->permissions()->pluck('key')->sort()->values()->all())->toBe(['admin.access', 'users.view']);
});

test('role description may be up to 1000 characters long', function () {
    $this->actingAs($this->super)->post(route('admin.roles.store'), ['name' => 'Support', 'description' => str_repeat('a', 1000), 'permissions' => []])
        ->assertSessionHasNoErrors();

    expect(mb_strlen(Role::where('name', 'Support')->value('description')))->toBe(1000);
});

test('roles manager updates a role and syncs its permissions', function () {
    $role = roleGranting([Permission::LinksManage, Permission::LinksStats]);

    $this->actingAs($this->super)->put(route('admin.roles.update', $role), [
        'name' => $role->name, 'description' => null, 'permissions' => ['billing.manage'],
    ])->assertRedirect(route('admin.roles.index'));

    expect($role->fresh()->description)->toBeNull()
        ->and($role->permissions()->pluck('key')->all())->toBe(['billing.manage']);
});

test('roles manager deletes an empty role', function () {
    $role = roleGranting([Permission::LinksManage]);

    $this->actingAs($this->super)->delete(route('admin.roles.destroy', $role))->assertRedirect(route('admin.roles.index'));

    expect($role->fresh())->toBeNull()
        ->and(DB::table('permission_role')->where('role_id', $role->id)->exists())->toBeFalse();
});

test('role routes are forbidden without roles.manage', function () {
    $role = roleGranting([Permission::LinksManage]);
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('admin.roles.index'))->assertForbidden();
    $this->get(route('admin.roles.create'))->assertForbidden();
    $this->post(route('admin.roles.store'), ['name' => 'X', 'permissions' => []])->assertForbidden();
    $this->get(route('admin.roles.edit', $role))->assertForbidden();
    $this->put(route('admin.roles.update', $role), ['name' => 'X', 'permissions' => []])->assertForbidden();
    $this->delete(route('admin.roles.destroy', $role))->assertForbidden();

    expect(Role::where('name', 'X')->exists())->toBeFalse()->and($role->fresh())->not->toBeNull();
});

test('role data is validated', function (array $payload, string $field) {
    $this->actingAs($this->super)->post(route('admin.roles.store'), $payload)->assertSessionHasErrors($field);
})->with([
    'missing name' => [['permissions' => []], 'name'],
    'duplicate name' => [['name' => PermissionCatalog::ADMINISTRATOR_ROLE, 'permissions' => []], 'name'],
    'unknown permission' => [['name' => 'X', 'permissions' => ['links.fly']], 'permissions.0'],
    'permissions not a list' => [['name' => 'X', 'permissions' => 'links.manage'], 'permissions'],
    'too long description' => [['name' => 'X', 'permissions' => [], 'description' => str_repeat('a', 1001)], 'description'],
]);

test('updating a role may keep its own name but not take another one', function () {
    $role = roleGranting([Permission::LinksManage]);

    $this->actingAs($this->super)->put(route('admin.roles.update', $role), ['name' => $role->name, 'permissions' => []])->assertSessionHasNoErrors();
    $this->actingAs($this->super)->put(route('admin.roles.update', $role), ['name' => PermissionCatalog::CLIENT_ROLE, 'permissions' => []])->assertSessionHasErrors('name');
});

test('the super role is shown read-only and cannot be edited', function () {
    $superRole = Role::findOrFail(roleId(PermissionCatalog::SUPER_ADMIN_ROLE));

    $this->actingAs($this->super)->get(route('admin.roles.edit', $superRole))
        ->assertInertia(fn (Assert $page) => $page->component('admin/roles/Edit')->where('role.is_super', true));
    $this->actingAs($this->super)->put(route('admin.roles.update', $superRole), ['name' => 'Renamed', 'permissions' => []])->assertForbidden();
    $this->actingAs($this->super)->delete(route('admin.roles.destroy', $superRole))->assertForbidden();

    expect($superRole->fresh()->name)->toBe(PermissionCatalog::SUPER_ADMIN_ROLE);
});

test('roles that are super, default or held by users cannot be deleted', function (Role $role) {
    $this->actingAs($this->super)->delete(route('admin.roles.destroy', $role))->assertForbidden();

    expect($role->fresh())->not->toBeNull();
})->with([
    'super' => fn () => Role::findOrFail(roleId(PermissionCatalog::SUPER_ADMIN_ROLE)),
    'default' => fn () => Role::findOrFail(roleId(PermissionCatalog::CLIENT_ROLE)),
    'with users' => function () {
        $role = roleGranting([Permission::LinksManage]);
        User::factory()->create(['role_id' => $role->id]);

        return $role;
    },
]);

test('actor cannot remove roles.manage from their own role', function () {
    $role = roleGranting([Permission::AdminAccess, Permission::RolesManage]);
    $actor = User::factory()->create(['role_id' => $role->id]);
    $actor->permissionOverrides()->attach(PermissionRecord::where('key', Permission::UsersView)->firstOrFail(), ['granted' => true]);

    $this->actingAs($actor)->put(route('admin.roles.update', $role), ['name' => $role->name, 'permissions' => ['admin.access']])
        ->assertSessionHasErrors('permissions');
    expect($role->permissionKeys()->sort()->values()->all())->toBe(['admin.access', 'roles.manage']);

    $this->actingAs($actor)->put(route('admin.roles.update', $role), ['name' => $role->name, 'permissions' => ['admin.access', 'roles.manage', 'users.view']])
        ->assertSessionHasNoErrors();
    expect($role->permissionKeys()->sort()->values()->all())->toBe(['admin.access', 'roles.manage', 'users.view']);
});

test('changing a user between client roles keeps their assigned administrators', function () {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->assignedTo($admin)->create();
    $otherClientRole = roleGranting([Permission::LinksManage]);

    $this->actingAs($this->super)->patch(route('admin.users.role', $client), ['role_id' => $otherClientRole->id])->assertRedirect();

    expect($client->fresh()->role_id)->toBe($otherClientRole->id)
        ->and($client->assignedAdmins()->pluck('users.id')->all())->toBe([$admin->id]);
});

test('changing a client to a custom role with admin panel access detaches assigned administrators', function () {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->assignedTo($admin)->create();
    $staffRole = roleGranting([Permission::AdminAccess]);

    $this->actingAs($this->super)->patch(route('admin.users.role', $client), ['role_id' => $staffRole->id])->assertRedirect();

    expect($client->fresh()->isAdmin())->toBeTrue()
        ->and($client->assignedAdmins()->exists())->toBeFalse();
});

test('actor cannot remove admin.access from their own role', function () {
    $role = roleGranting([Permission::AdminAccess, Permission::RolesManage]);
    $actor = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($actor)->put(route('admin.roles.update', $role), ['name' => $role->name, 'permissions' => ['roles.manage']])
        ->assertSessionHasErrors('permissions');

    expect($role->permissionKeys()->sort()->values()->all())->toBe(['admin.access', 'roles.manage']);
});

test('a non-super roles manager only puts permissions they hold into a role', function () {
    $actor = User::factory()->create(['role_id' => roleGranting([Permission::AdminAccess, Permission::RolesManage, Permission::LinksManage])->id]);
    $role = roleGranting([Permission::LinksManage]);

    $this->actingAs($actor)->post(route('admin.roles.store'), ['name' => 'Escalated', 'permissions' => ['links.manage', 'users.block']])
        ->assertSessionHasErrors('permissions.1');
    $this->actingAs($actor)->put(route('admin.roles.update', $role), ['name' => $role->name, 'permissions' => ['links.manage', 'users.block']])
        ->assertSessionHasErrors('permissions.1');
    expect(Role::where('name', 'Escalated')->exists())->toBeFalse()
        ->and($role->permissionKeys()->all())->toBe(['links.manage']);

    $this->actingAs($actor)->post(route('admin.roles.store'), ['name' => 'Linker', 'permissions' => ['links.manage']])->assertSessionHasNoErrors();
    $this->actingAs($actor)->put(route('admin.roles.update', $role), ['name' => $role->name, 'permissions' => ['links.manage', 'admin.access']])
        ->assertSessionHasNoErrors();
    expect($role->permissionKeys()->sort()->values()->all())->toBe(['admin.access', 'links.manage']);
});

test('a non-super roles manager cannot edit or delete a role holding permissions they lack', function () {
    $actor = User::factory()->create(['role_id' => roleGranting([Permission::AdminAccess, Permission::RolesManage, Permission::LinksManage])->id]);
    $role = roleGranting([Permission::LinksManage, Permission::UsersBlock]);

    $this->actingAs($actor)->get(route('admin.roles.edit', $role))->assertForbidden();
    $this->actingAs($actor)->put(route('admin.roles.update', $role), ['name' => 'Renamed', 'permissions' => ['links.manage']])->assertForbidden();
    $this->actingAs($actor)->delete(route('admin.roles.destroy', $role))->assertForbidden();

    expect($role->fresh()->name)->not->toBe('Renamed')
        ->and($role->permissionKeys()->sort()->values()->all())->toBe(['links.manage', 'users.block']);
});

test('non-string role permissions are rejected, not a server error', function (mixed $permission) {
    $actor = User::factory()->create(['role_id' => roleGranting([Permission::AdminAccess, Permission::RolesManage])->id]);

    foreach ([$actor, $this->super] as $user) {
        $this->actingAs($user)->post(route('admin.roles.store'), ['name' => 'X', 'permissions' => [$permission]])
            ->assertSessionHasErrors('permissions.0');
    }
})->with([
    'nested array' => [['links.manage']],
    'integer' => [42],
]);

test('the default role cannot be given admin panel access', function () {
    $default = Role::findOrFail(roleId(PermissionCatalog::CLIENT_ROLE));
    $keys = $default->permissionKeys()->all();

    $this->actingAs($this->super)->put(route('admin.roles.update', $default), ['name' => $default->name, 'permissions' => [...$keys, 'admin.access']])
        ->assertSessionHasErrors('permissions');

    expect($default->permissionKeys()->all())->not->toContain('admin.access');
});
