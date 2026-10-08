<?php

namespace App\Http\Requests\Links;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
            'is_active' => ['boolean'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
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
