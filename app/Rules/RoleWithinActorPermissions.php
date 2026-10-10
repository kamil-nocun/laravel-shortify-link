<?php

namespace App\Rules;

use App\Models\Role;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class RoleWithinActorPermissions implements ValidationRule
{
    public function __construct(private readonly User $actor) {}

    /**
     * A non-super actor may only assign roles whose permissions they hold; the super admin is unrestricted.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->actor->isSuperAdmin() || ! is_string($value) || ! Str::isUuid($value)) {
            return;
        }

        if (Role::find($value)?->permissionKeys()->diff($this->actor->permissionKeys())->isNotEmpty()) {
            $fail(__('You can only assign roles whose permissions you hold yourself.'));
        }
    }
}
