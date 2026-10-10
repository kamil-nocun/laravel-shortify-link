<?php

namespace App\Policies;

use App\Models\Domain;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DomainPolicy
{
    /**
     * Determine whether the user's package allows another domain.
     */
    public function create(User $user): Response|bool
    {
        return $user->canAddDomain() ?: Response::deny(__('Domain limit reached for your package.'));
    }

    /**
     * Determine whether the user owns the domain and no link uses it.
     */
    public function delete(User $user, Domain $domain): bool
    {
        return $user->id === $domain->user_id && ! $domain->links()->exists();
    }

    /**
     * Determine whether the user owns the domain and may re-check its DNS record.
     */
    public function verify(User $user, Domain $domain): bool
    {
        return $user->id === $domain->user_id;
    }
}
