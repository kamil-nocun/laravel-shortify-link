<?php

namespace App\Http\Requests\Admin;

use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\User;
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
            $rules['administrators.*'] = ['uuid', Rule::exists('users', 'id')->where('role', UserRole::Admin)];
        }

        return $rules;
    }
}
