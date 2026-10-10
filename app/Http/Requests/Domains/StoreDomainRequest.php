<?php

namespace App\Http\Requests\Domains;

use App\Models\Domain;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Domain::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->host)) {
            $this->merge(['host' => rtrim(Str::lower(trim($this->host)), '.')]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'host' => [
                'required', 'string', 'max:253',
                'regex:/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/',
                'unique:domains,host',
                Rule::notIn([Str::lower((string) parse_url(config('app.url'), PHP_URL_HOST))]),
            ],
        ];
    }
}
