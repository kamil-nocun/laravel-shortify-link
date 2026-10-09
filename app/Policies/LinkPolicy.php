<?php

namespace App\Policies;

use App\Models\Link;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LinkPolicy
{
    /**
     * Determine whether the user's package allows another link.
     */
    public function create(User $user): Response|bool
    {
        return $user->canCreateLink() ?: Response::deny(__('Link limit reached for your package.'));
    }

    /**
     * Determine whether the user owns the link (or is an admin) and may see its stats.
     */
    public function view(User $user, Link $link): bool
    {
        return $user->id === $link->user_id || $user->isAdmin();
    }

    /**
     * Determine whether the user owns the link and may edit it.
     */
    public function update(User $user, Link $link): bool
    {
        return $user->id === $link->user_id;
    }

    /**
     * Determine whether the user owns the link and may delete it.
     */
    public function delete(User $user, Link $link): bool
    {
        return $user->id === $link->user_id;
    }

    /**
     * Determine whether the user owns the link and an admin has not disabled it.
     */
    public function toggle(User $user, Link $link): bool
    {
        return $user->id === $link->user_id && $link->disabled_by_admin_at === null;
    }
}
