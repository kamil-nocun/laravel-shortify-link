<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changeRole', $this->route('user'));
    }

    /**
     * admin.access is role-only, so it can be neither granted nor revoked per user.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $key = ['string', Rule::exists('permissions', 'key'), Rule::notIn([Permission::AdminAccess->value])];

        return [
            'granted' => ['present', 'array'],
            'granted.*' => [...$key, 'distinct', Rule::notIn((array) $this->input('revoked'))],
            'revoked' => ['present', 'array'],
            'revoked.*' => [...$key, 'distinct'],
        ];
    }
}
