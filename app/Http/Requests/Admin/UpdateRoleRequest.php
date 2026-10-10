<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use Illuminate\Validation\Validator;

class UpdateRoleRequest extends StoreRoleRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('role'));
    }

    /**
     * Editing one's own role must keep roles.manage, so nobody locks themselves out of role management.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->user()->role_id === $this->route('role')->id && ! in_array(Permission::RolesManage->value, (array) $this->input('permissions'), true)) {
                $validator->errors()->add('permissions', __('Your own role must keep the :permission permission.', ['permission' => Permission::RolesManage->label()]));
            }
        }];
    }
}
