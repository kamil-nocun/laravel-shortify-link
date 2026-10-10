<?php

namespace App\Support;

use App\Enums\Permission;
use App\Models\PermissionRecord;
use App\Models\Role;

class PermissionCatalog
{
    public const SUPER_ADMIN_ROLE = 'Super admin';

    public const ADMINISTRATOR_ROLE = 'Administrator';

    public const CLIENT_ROLE = 'Client';

    /**
     * Upsert the permissions and create any missing starting role; existing roles are left untouched.
     */
    public static function sync(): void
    {
        foreach (Permission::cases() as $permission) {
            PermissionRecord::updateOrCreate(
                ['key' => $permission->value],
                ['label' => $permission->label(), 'group' => $permission->group()],
            );
        }

        $administratorGroups = ['Admin panel', 'Users', 'Links (admin)', 'Domains (admin)'];
        $administratorKeys = collect(Permission::cases())
            ->filter(fn (Permission $permission) => in_array($permission->group(), $administratorGroups, true) && $permission !== Permission::UsersChangeRole)
            ->map->value;

        self::createRole(self::SUPER_ADMIN_ROLE, ['is_super' => true], []);
        self::createRole(self::ADMINISTRATOR_ROLE, [], $administratorKeys->all());
        self::createRole(self::CLIENT_ROLE, ['is_default' => true], array_map(fn (Permission $permission) => $permission->value, Permission::forClients()));
    }

    /**
     * @param  array<string, bool>  $flags
     * @param  list<string>  $keys
     */
    private static function createRole(string $name, array $flags, array $keys): void
    {
        $role = Role::firstOrNew(['name' => $name]);

        if ($role->exists) {
            return;
        }

        $role->forceFill($flags)->save();
        $role->permissions()->attach(PermissionRecord::whereIn('key', $keys)->pluck('id'));
    }
}
