<?php

use App\Enums\Permission;
use App\Models\Link;
use App\Models\Package;
use App\Models\PackageAssignment;
use App\Models\PermissionRecord;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A user whose fresh role grants exactly the given permissions.
 *
 * @param  list<Permission>  $permissions
 */
function userWithPermissions(array $permissions): User
{
    $role = Role::factory()->create();
    $role->permissions()->attach(PermissionRecord::whereIn('key', array_map(fn (Permission $permission) => $permission->value, $permissions))->pluck('id'));

    return User::factory()->create(['role_id' => $role->id]);
}

test('blocking an assigned client requires the users.block permission', function (array $permissions, bool $expected) {
    $admin = userWithPermissions($permissions);
    $client = User::factory()->assignedTo($admin)->create();

    expect(Gate::forUser($admin)->allows('block', $client))->toBe($expected);
})->with([
    'without users.block' => [[Permission::AdminAccess], false],
    'with users.block' => [[Permission::AdminAccess, Permission::UsersBlock], true],
]);

test('a revoking override takes users.block away despite the role', function () {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->assignedTo($admin)->create();
    $admin->permissionOverrides()->attach(PermissionRecord::where('key', Permission::UsersBlock)->firstOrFail(), ['granted' => false]);

    expect(Gate::forUser($admin)->allows('block', $client))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('update', $client))->toBeTrue();
});

test('super admin cannot block, delete or change the role of themselves', function (string $ability) {
    $super = User::factory()->superAdmin()->create();

    expect(Gate::forUser($super)->allows($ability, $super))->toBeFalse();
})->with(['block', 'delete', 'changeRole']);

test('super admin cannot update a foreign link through the client route', function () {
    $link = Link::factory()->create();
    $super = User::factory()->superAdmin()->create();

    expect(Gate::forUser($super)->allows('update', $link))->toBeFalse();
    $this->actingAs($super)
        ->patch(route('links.update', $link), ['original_url' => 'https://example.com', 'title' => 'x', 'is_active' => true])
        ->assertRedirect(route('links.index')); // denials on links.* render as a redirect with an error toast
    expect($link->fresh()->original_url)->not->toBe('https://example.com');
});

test('creating a link requires links.manage and respects the package limit', function () {
    $withoutPermission = userWithPermissions([Permission::LinksStats]);
    PackageAssignment::factory()->for($withoutPermission)->for(Package::factory()->create(['link_limit' => 5]))->create();
    expect(Gate::forUser($withoutPermission)->allows('create', Link::class))->toBeFalse();
    $this->actingAs($withoutPermission)->post(route('links.store'), ['original_url' => 'https://example.com'])->assertRedirect(route('links.index'));
    expect($withoutPermission->links()->exists())->toBeFalse();

    $client = userWithPermissions([Permission::LinksManage]);
    PackageAssignment::factory()->for($client)->for(Package::factory()->create(['link_limit' => 1]))->create();

    $this->actingAs($client)->post(route('links.store'), ['original_url' => 'https://example.com'])->assertRedirect(route('links.index'));
    $this->actingAs($client)->post(route('links.store'), ['original_url' => 'https://example.org'])->assertRedirect(route('links.index'));
    expect($client->links()->count())->toBe(1);
});

test('disabling links and domains needs their own permission, not users.block', function () {
    $admin = userWithPermissions([Permission::AdminAccess, Permission::UsersBlock]);
    $client = User::factory()->assignedTo($admin)->create();
    $link = Link::factory()->for($client)->create();

    $this->actingAs($admin)->patch(route('admin.links.disable', $link))->assertForbidden();
    expect(Gate::forUser($admin)->allows('disableDomains', $client))->toBeFalse();

    $moderator = userWithPermissions([Permission::AdminAccess, Permission::LinksDisable, Permission::DomainsDisable]);
    $client->assignedAdmins()->attach($moderator);

    expect(Gate::forUser($moderator)->allows('disableLinks', $client))->toBeTrue()
        ->and(Gate::forUser($moderator)->allows('disableDomains', $client))->toBeTrue();
});

test('auth.can shares every permission key, true only for the user\'s own', function () {
    $this->actingAs(userWithPermissions([Permission::LinksManage, Permission::LinksStats]))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.can', collect(Permission::cases())->mapWithKeys(fn (Permission $permission) => [
                $permission->value => in_array($permission, [Permission::LinksManage, Permission::LinksStats], true),
            ])->all()));
});

test('guests share an empty auth.can map', function () {
    $this->get(route('front_app'))->assertInertia(fn (Assert $page) => $page->where('auth.can', []));
});

test('an administrator granted users.change_role changes roles only of clients they manage', function () {
    $admin = User::factory()->admin()->create();
    $admin->permissionOverrides()->attach(PermissionRecord::where('key', Permission::UsersChangeRole)->firstOrFail(), ['granted' => true]);
    $assigned = User::factory()->assignedTo($admin)->create();
    $unassigned = User::factory()->create();
    $otherAdmin = User::factory()->admin()->create();
    $otherAdmin->assignedAdmins()->attach($admin);

    expect(Gate::forUser($admin)->allows('changeRole', $assigned))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('changeRole', $unassigned))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('changeRole', $otherAdmin))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('changeRole', $admin))->toBeFalse();

    $this->actingAs($admin)->patch(route('admin.users.role', $unassigned), ['role_id' => roleId('Administrator')])->assertNotFound();
    expect($unassigned->fresh()->role_id)->toBe(roleId('Client'));
});
