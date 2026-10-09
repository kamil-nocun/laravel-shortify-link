<?php

namespace App\Http\Requests\Links;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkStatsRequest extends FormRequest
{
    public const DEFAULT_DAYS = 30;

    /**
     * Only the link owner may see its stats.
     */
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('link'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'days' => ['sometimes', 'integer', Rule::in([7, 30, 90])],
        ];
    }

    /**
     * Length of the requested range in days.
     */
    public function days(): int
    {
        return $this->integer('days', self::DEFAULT_DAYS);
    }
}
