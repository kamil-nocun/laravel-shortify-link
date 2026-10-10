<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserAdministratorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assignAdministrators', User::class)
            && $this->route('user')->isClient();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'administrators' => ['present', 'array'],
            'administrators.*' => ['uuid', Rule::exists('users', 'id')->whereIn('role_id', Role::administratorIds())],
        ];
    }
}
