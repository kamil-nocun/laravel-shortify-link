<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Determine whether the actor may create users.
     */
    public function create(User $actor): bool
    {
        return $actor->isAdmin();
    }

    /**
     * Determine whether the actor may list and create administrators.
     */
    public function manageAdministrators(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    /**
     * Determine whether the actor may add links on the target's behalf, within the target's package limit.
     */
    public function createLink(User $actor, User $target): Response|bool
    {
        if (! $this->outranks($actor, $target) || ! $this->manages($actor, $target)) {
            return false;
        }

        return $target->canCreateLink() ?: Response::deny(__('Link limit reached for the user\'s package.'));
    }

    /**
     * Determine whether the actor may block or unblock the target.
     */
    public function block(User $actor, User $target): bool
    {
        return $this->outranks($actor, $target) && $this->manages($actor, $target);
    }

    /**
     * Determine whether the actor may edit the target's profile.
     */
    public function update(User $actor, User $target): bool
    {
        return $this->outranks($actor, $target) && $this->manages($actor, $target);
    }

    /**
     * Determine whether the actor may delete the target.
     */
    public function delete(User $actor, User $target): bool
    {
        return $this->outranks($actor, $target) && $this->manages($actor, $target);
    }

    /**
     * Determine whether the actor may change the target's role.
     */
    public function changeRole(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin() && ! $target->is($actor) && ! $target->isSuperAdmin();
    }

    /**
     * Determine whether the actor may change which administrators manage a client.
     */
    public function assignAdministrators(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    /**
     * Determine whether the actor may assign a package to the target.
     */
    public function assignPackage(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin() && ! $target->isSuperAdmin();
    }

    /**
     * Determine whether the target is within the actor's scope of clients.
     */
    public function manages(User $actor, User $target): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        if ($target->relationLoaded('assignedAdmins')) {
            return $actor->isAdmin() && $target->assignedAdmins->contains('id', $actor->id);
        }

        return $actor->isAdmin() && $target->assignedAdmins()->whereKey($actor->id)->exists();
    }

    private function outranks(User $actor, User $target): bool
    {
        if ($target->is($actor) || ! $actor->isAdmin()) {
            return false;
        }

        return $actor->isSuperAdmin() || ! $target->isAdmin();
    }
}
