<?php

namespace App\Http\Requests\Links;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLinkRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'original_url' => ['required', 'url:http,https', 'max:2048'],
            'title' => ['nullable', 'string', 'max:255'],
            'domain_id' => ['nullable', 'uuid', Rule::exists('domains', 'id')
                ->where('user_id', $this->linkOwner()->id)
                ->whereNotNull('verified_at')
                ->whereNull('disabled_by_admin_at')],
            'is_active' => ['boolean'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    /**
     * The user who will own the new link.
     */
    protected function linkOwner(): User
    {
        return $this->user();
    }

    /**
     * Get custom attribute names matching the form labels.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'original_url' => __('target URL'),
            'expires_at' => __('expiry date'),
        ];
    }
}
