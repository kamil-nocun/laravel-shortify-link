<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the actor may block or unblock the target.
     */
    public function block(User $actor, User $target): bool
    {
        return $this->outranks($actor, $target);
    }

    /**
     * Determine whether the actor may delete the target.
     */
    public function delete(User $actor, User $target): bool
    {
        return $this->outranks($actor, $target);
    }

    /**
     * Determine whether the actor may change the target's role.
     */
    public function changeRole(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin() && ! $target->is($actor) && ! $target->isSuperAdmin();
    }

    private function outranks(User $actor, User $target): bool
    {
        if ($target->is($actor) || ! $actor->isAdmin()) {
            return false;
        }

        return $actor->isSuperAdmin() || ! $target->isAdmin();
    }
}
