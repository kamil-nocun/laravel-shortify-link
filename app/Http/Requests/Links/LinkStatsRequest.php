<?php

namespace App\Http\Requests\Links;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkStatsRequest extends FormRequest
{
    public const DEFAULT_DAYS = 30;

    /**
     * Click columns the stats can be filtered by.
     *
     * @var list<string>
     */
    public const FILTERS = ['country', 'city', 'browser', 'os', 'device_type'];

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
            ...array_fill_keys(self::FILTERS, ['nullable', 'string', 'max:255']),
        ];
    }

    /**
     * Length of the requested range in days.
     */
    public function days(): int
    {
        return $this->integer('days', self::DEFAULT_DAYS);
    }

    /**
     * Requested filter values keyed by column; null when a filter is not set.
     *
     * @return array<string, ?string>
     */
    public function filters(): array
    {
        return collect(self::FILTERS)
            ->mapWithKeys(fn (string $column): array => [$column => $this->filled($column) ? $this->string($column)->toString() : null])
            ->all();
    }
}
