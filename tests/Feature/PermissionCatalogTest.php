<?php

use App\Enums\Permission;
use App\Models\Package;
use App\Models\PackageAssignment;
use App\Models\PermissionRecord;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\Queue;

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

test('administrator role holds the client permissions but not the super-only ones', function () {
    $keys = Role::where('name', PermissionCatalog::ADMINISTRATOR_ROLE)->firstOrFail()->permissionKeys();

    expect($keys)->toContain('links.manage', 'links.stats', 'domains.manage', 'billing.manage')
        ->and($keys)->not->toContain('users.change_role', 'packages.manage', 'roles.manage', 'administrators.manage');
});

test('default administrator manages own links and domains', function () {
    Queue::fake();
    $admin = User::factory()->admin()->create();
    PackageAssignment::factory()->for($admin)->for(Package::factory()->create(['domain_limit' => 1]))->create();

    $this->actingAs($admin)->post(route('links.store'), ['original_url' => 'https://example.com', 'is_active' => true])
        ->assertRedirect(route('links.index'));
    $this->actingAs($admin)->post(route('domains.store'), ['host' => 'admin.example.com'])
        ->assertRedirect(route('domains.index'));

    expect($admin->links()->count())->toBe(1)->and($admin->domains()->count())->toBe(1);
});

test('data migration restores client permissions on the administrator role once', function () {
    $keys = ['links.manage', 'links.stats', 'domains.manage', 'billing.manage'];
    $role = Role::where('name', PermissionCatalog::ADMINISTRATOR_ROLE)->firstOrFail();
    $role->permissions()->detach(PermissionRecord::whereIn('key', $keys)->pluck('id'));
    $migration = require database_path('migrations/2026_10_10_113410_add_client_permissions_to_administrator_role.php');

    $migration->up();
    $migration->up();

    $attached = $role->permissionKeys();
    expect($attached)->toContain(...$keys)
        ->and($attached->count())->toBe($attached->unique()->count());
});
