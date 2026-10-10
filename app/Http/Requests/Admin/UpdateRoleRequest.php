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
     * One's own role must keep admin.access and roles.manage, so nobody locks themselves out;
     * the default role never grants admin.access, or every new sign-up would be an administrator.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $role = $this->route('role');
            $permissions = (array) $this->input('permissions');

            if ($this->user()->role_id === $role->id) {
                foreach ([Permission::AdminAccess, Permission::RolesManage] as $required) {
                    if (! in_array($required->value, $permissions, true)) {
                        $validator->errors()->add('permissions', __('Your own role must keep the :permission permission.', ['permission' => $required->label()]));
                    }
                }
            }

            if ($role->is_default && in_array(Permission::AdminAccess->value, $permissions, true)) {
                $validator->errors()->add('permissions', __('The default role cannot grant :permission.', ['permission' => Permission::AdminAccess->label()]));
            }
        }];
    }
}
