<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use App\Models\User;
use App\Rules\RoleWithinActorPermissions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class StoreAdministratorRequest extends StoreUserRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageAdministrators', User::class);
    }

    /**
     * Every administrator gets a chosen administrator role (not super, granting admin panel access) within the actor's own permissions.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'role_id' => ['bail', 'required', 'uuid', Rule::in(Role::administratorIds()), new RoleWithinActorPermissions($this->user())],
        ];
    }
}
