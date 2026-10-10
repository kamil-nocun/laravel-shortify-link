<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RolePolicy
{
    /**
     * Determine whether the user may list roles.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::RolesManage);
    }

    /**
     * Determine whether the user may create roles.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::RolesManage);
    }

    /**
     * Determine whether the user may edit the role; the super role grants everything and stays as it is.
     */
    public function update(User $user, Role $role): Response|bool
    {
        if (! $user->hasPermission(Permission::RolesManage)) {
            return false;
        }

        return $role->is_super ? Response::deny(__('The super admin role cannot be edited.')) : true;
    }

    /**
     * Determine whether the user may delete the role; only an unused, non-super, non-default role goes.
     */
    public function delete(User $user, Role $role): Response|bool
    {
        if (! $user->hasPermission(Permission::RolesManage)) {
            return false;
        }

        return match (true) {
            $role->is_super => Response::deny(__('The super admin role cannot be deleted.')),
            $role->is_default => Response::deny(__('The default role cannot be deleted.')),
            $role->users()->exists() => Response::deny(__('A role held by users cannot be deleted.')),
            default => true,
        };
    }
}
