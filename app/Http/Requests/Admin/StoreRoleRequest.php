<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Role::class);
    }

    /**
     * A non-super actor may only put permissions into a role that they hold or that the edited role already has.
     */
    private function mayAssign(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->user()->isSuperAdmin() || $this->user()->hasPermission($value) || $this->route('role')?->permissionKeys()->contains($value)) {
            return;
        }

        $fail(__('You can only give a role permissions you hold yourself.'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($this->route('role'))],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['bail', 'string', 'distinct', Rule::exists('permissions', 'key'), $this->mayAssign(...)],
        ];
    }
}
