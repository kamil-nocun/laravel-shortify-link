<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DomainPolicy
{
    /**
     * Determine whether the user may manage domains and their package allows another one.
     */
    public function create(User $user): Response|bool
    {
        if (! $user->hasPermission(Permission::DomainsManage)) {
            return false;
        }

        return $user->canAddDomain() ?: Response::deny(__('Domain limit reached for your package.'));
    }

    /**
     * Determine whether the user may manage domains, owns the domain and no link uses it.
     */
    public function delete(User $user, Domain $domain): bool
    {
        return $user->hasPermission(Permission::DomainsManage) && $user->id === $domain->user_id && ! $domain->links()->exists();
    }

    /**
     * Determine whether the user may manage domains, owns the domain and may re-check its DNS record.
     */
    public function verify(User $user, Domain $domain): bool
    {
        return $user->hasPermission(Permission::DomainsManage) && $user->id === $domain->user_id;
    }
}
