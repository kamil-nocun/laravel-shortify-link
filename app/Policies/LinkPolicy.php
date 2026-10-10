<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Link;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LinkPolicy
{
    /**
     * Determine whether the user may manage links and their package allows another one.
     */
    public function create(User $user): Response|bool
    {
        if (! $user->hasPermission(Permission::LinksManage)) {
            return false;
        }

        return $user->canCreateLink() ?: Response::deny(__('Link limit reached for your package.'));
    }

    /**
     * Determine whether the user owns the link and may see stats, or is an admin.
     */
    public function view(User $user, Link $link): bool
    {
        return ($user->id === $link->user_id && $user->hasPermission(Permission::LinksStats)) || $user->isAdmin();
    }

    /**
     * Determine whether the user may manage links and owns this one.
     */
    public function update(User $user, Link $link): bool
    {
        return $user->hasPermission(Permission::LinksManage) && $user->id === $link->user_id;
    }

    /**
     * Determine whether the user may manage links and owns this one.
     */
    public function delete(User $user, Link $link): bool
    {
        return $user->hasPermission(Permission::LinksManage) && $user->id === $link->user_id;
    }

    /**
     * Determine whether the user may manage links, owns this one and an admin has not disabled it.
     */
    public function toggle(User $user, Link $link): bool
    {
        return $user->hasPermission(Permission::LinksManage) && $user->id === $link->user_id && $link->disabled_by_admin_at === null;
    }
}
