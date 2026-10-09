<?php

namespace App\Policies;

use App\Models\Package;
use App\Models\User;

class PackagePolicy
{
    /**
     * Determine whether the user may list packages.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user may create packages.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user may edit the package.
     */
    public function update(User $user, Package $package): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user may delete the package.
     */
    public function delete(User $user, Package $package): bool
    {
        return $user->isSuperAdmin();
    }
}
