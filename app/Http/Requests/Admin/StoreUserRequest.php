<?php

namespace App\Http\Requests\Admin;

use App\Concerns\ProfileValidationRules;
use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use App\Rules\RoleWithinActorPermissions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    use ProfileValidationRules;

    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = $this->profileRules();

        if ($this->user()->isSuperAdmin()) {
            $rules['administrators'] = ['nullable', 'array'];
            $rules['administrators.*'] = ['uuid', Rule::exists('users', 'id')->whereIn('role_id', Role::administratorIds())];
        }

        if ($this->user()->hasPermission(Permission::UsersChangeRole)) {
            $rules['role_id'] = [...$this->roleRules(), new RoleWithinActorPermissions($this->user())];
        }

        return $rules;
    }

    /**
     * A client role: neither super nor granting admin panel access.
     *
     * @return array<int, mixed>
     */
    protected function roleRules(): array
    {
        return ['bail', 'nullable', 'uuid', Rule::exists('roles', 'id')->where('is_super', false)->whereNotIn('id', Role::administratorIds())];
    }
}
