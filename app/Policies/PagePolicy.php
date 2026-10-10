<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Page;
use App\Models\User;

class PagePolicy
{
    /**
     * Determine whether the user may list pages.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::PagesManage);
    }

    /**
     * Determine whether the user may create pages.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::PagesManage);
    }

    /**
     * Determine whether the user may edit, publish and restore versions of the page.
     */
    public function update(User $user, Page $page): bool
    {
        return $user->hasPermission(Permission::PagesManage);
    }
}
