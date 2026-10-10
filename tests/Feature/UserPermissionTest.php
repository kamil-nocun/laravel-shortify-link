<?php

use App\Enums\Permission;
use App\Models\PermissionRecord;
use App\Models\Role;
use App\Models\User;

function userWithRole(Role $role): User
{
    $user = User::factory()->create();
    $user->role()->associate($role)->save();

    return $user;
}

test('role permission is granted', function () {
    $user = userWithRole(Role::where('name', 'Client')->firstOrFail());

    expect($user->hasPermission(Permission::LinksManage))->toBeTrue()
        ->and($user->hasPermission('links.manage'))->toBeTrue()
        ->and($user->hasPermission(Permission::UsersBlock))->toBeFalse();
});

test('override granted adds a permission outside the role', function () {
    $user = userWithRole(Role::where('name', 'Client')->firstOrFail());
    $user->permissionOverrides()->attach(PermissionRecord::where('key', 'users.block')->firstOrFail(), ['granted' => true]);

    expect($user->hasPermission(Permission::UsersBlock))->toBeTrue();
});

test('override revoked removes a role permission', function () {
    $user = userWithRole(Role::where('name', 'Client')->firstOrFail());
    $user->permissionOverrides()->attach(PermissionRecord::where('key', 'links.manage')->firstOrFail(), ['granted' => false]);

    expect($user->hasPermission(Permission::LinksManage))->toBeFalse();
});

test('super role has every permission regardless of overrides', function () {
    $user = userWithRole(Role::where('is_super', true)->firstOrFail());
    $user->permissionOverrides()->attach(PermissionRecord::where('key', 'roles.manage')->firstOrFail(), ['granted' => false]);

    expect($user->hasPermission(Permission::RolesManage))->toBeTrue()
        ->and($user->permissionKeys())->toHaveCount(count(Permission::cases()));
});

test('user without a role has no permissions', function () {
    $user = User::factory()->create();
    $user->forceFill(['role_id' => null])->save();

    expect($user->role_id)->toBeNull()
        ->and($user->hasPermission(Permission::LinksManage))->toBeFalse()
        ->and($user->permissionKeys())->toBeEmpty()
        ->and($user->isAdmin())->toBeFalse()
        ->and($user->isSuperAdmin())->toBeFalse();
});

test('forgetPermissions refreshes the cached result', function () {
    $user = userWithRole(Role::where('name', 'Client')->firstOrFail());
    expect($user->hasPermission(Permission::UsersBlock))->toBeFalse();

    $user->permissionOverrides()->attach(PermissionRecord::where('key', 'users.block')->firstOrFail(), ['granted' => true]);
    expect($user->hasPermission(Permission::UsersBlock))->toBeFalse();

    $user->forgetPermissions();
    expect($user->hasPermission(Permission::UsersBlock))->toBeTrue();
});
