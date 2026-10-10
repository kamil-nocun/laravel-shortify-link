<?php

use App\Enums\Permission;
use App\Models\PermissionRecord;
use App\Models\Role;
use App\Support\PermissionCatalog;

test('sync is idempotent', function () {
    PermissionCatalog::sync();
    PermissionCatalog::sync();

    expect(PermissionRecord::count())->toBe(count(Permission::cases()))
        ->and(Role::count())->toBe(3);
});

test('sync creates the starting roles', function () {
    PermissionCatalog::sync();

    $keys = Role::where('name', PermissionCatalog::ADMINISTRATOR_ROLE)->firstOrFail()->permissionKeys();

    expect(Role::where('is_super', true)->pluck('name')->all())->toBe([PermissionCatalog::SUPER_ADMIN_ROLE])
        ->and(Role::where('is_default', true)->pluck('name')->all())->toBe([PermissionCatalog::CLIENT_ROLE])
        ->and($keys)->toContain('admin.access', 'users.block', 'links.disable')
        ->and($keys)->not->toContain('users.change_role', 'packages.manage', 'roles.manage');
});

test('client role has the client permissions', function () {
    expect(Role::where('name', PermissionCatalog::CLIENT_ROLE)->firstOrFail()->permissionKeys()->sort()->values()->all())
        ->toBe(collect(Permission::forClients())->map->value->sort()->values()->all());
});

test('sync does not overwrite an edited role', function () {
    $admin = Role::where('name', PermissionCatalog::ADMINISTRATOR_ROLE)->firstOrFail();
    $admin->permissions()->detach();

    PermissionCatalog::sync();

    expect($admin->refresh()->permissionKeys())->toBeEmpty();
});

test('migrations seed the starting roles', function () {
    $this->artisan('migrate:fresh')->assertSuccessful();

    expect(Role::count())->toBe(3);
});
