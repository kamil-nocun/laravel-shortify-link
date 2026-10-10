<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changeRole', $this->route('user'));
    }

    /**
     * A non-super actor may only grant permissions they hold themselves.
     */
    private function mayGrant(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->user()->isSuperAdmin() && ! $this->user()->hasPermission((string) $value)) {
            $fail(__('You can only grant permissions you hold yourself.'));
        }
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
            'granted.*' => [...$key, 'distinct', Rule::notIn(array_filter((array) $this->input('revoked'), 'is_string')), $this->mayGrant(...)],
            'revoked' => ['present', 'array'],
            'revoked.*' => [...$key, 'distinct'],
        ];
    }
}
