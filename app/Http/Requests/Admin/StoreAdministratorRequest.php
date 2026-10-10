<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Validation\Rule;

class StoreAdministratorRequest extends StoreUserRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageAdministrators', User::class);
    }

    /**
     * An administrator role: not super and granting admin panel access.
     *
     * @return array<int, mixed>
     */
    protected function roleRules(): array
    {
        return ['bail', 'nullable', 'uuid', Rule::in(Role::administratorIds())];
    }
}
