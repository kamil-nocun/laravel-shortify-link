<?php

namespace App\Http\Requests\Admin;

use App\Models\Package;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Package::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $price = ['nullable', 'integer', 'min:0', 'max:4294967295'];

        return [
            'name' => ['required', 'string', 'max:255'],
            'link_limit' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'price_one_time' => $price,
            'price_monthly' => $price,
            'price_quarterly' => $price,
            'price_yearly' => $price,
            'is_default' => ['boolean'],
        ];
    }
}
