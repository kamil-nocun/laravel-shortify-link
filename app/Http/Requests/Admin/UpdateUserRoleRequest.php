<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changeRole', $this->route('user'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return ['role_id' => ['required', 'uuid', Rule::exists('roles', 'id')->where('is_super', false), function (string $attribute, mixed $value, Closure $fail): void {
            $actor = $this->user();

            if (! $actor->isSuperAdmin() && Role::find($value)?->permissionKeys()->diff($actor->permissionKeys())->isNotEmpty()) {
                $fail(__('You can only assign roles whose permissions you hold yourself.'));
            }
        }]];
    }
}
