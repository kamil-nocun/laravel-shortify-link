<?php

namespace App\Http\Requests\Links;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkStatsRequest extends FormRequest
{
    public const DEFAULT_DAYS = 30;

    /**
     * Allowed values of the period parameter.
     *
     * @var list<string>
     */
    public const PERIODS = ['7d', '30d', '90d', 'all', 'day', 'month', 'year', 'range'];

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
            'period' => ['sometimes', Rule::in(self::PERIODS)],
            'date' => ['required_if:period,day', 'nullable', 'date_format:Y-m-d'],
            'month' => ['required_if:period,month', 'nullable', 'date_format:Y-m'],
            'year' => ['required_if:period,year', 'nullable', 'digits:4'],
            'from' => ['required_if:period,range', 'nullable', 'date_format:Y-m-d', 'before_or_equal:to'],
            'to' => [
                'required_if:period,range',
                'nullable',
                'date_format:Y-m-d',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $from = $this->input('from');

                    if (! is_string($from) || ! is_string($value) || ! Carbon::canBeCreatedFromFormat($from, 'Y-m-d') || ! Carbon::canBeCreatedFromFormat($value, 'Y-m-d')) {
                        return;
                    }

                    if (Carbon::createFromFormat('!Y-m-d', $from)->addYears(5)->lt(Carbon::createFromFormat('!Y-m-d', $value))) {
                        $fail('The range must not be longer than 5 years.');
                    }
                },
            ],
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
     * Requested period parameters; null when a parameter is not set.
     *
     * @return array{period: string, date: ?string, month: ?string, year: ?string, from: ?string, to: ?string}
     */
    public function period(): array
    {
        $value = fn (string $key): ?string => $this->filled($key) ? $this->string($key)->toString() : null;

        return [
            'period' => $value('period') ?? '30d',
            'date' => $value('date'),
            'month' => $value('month'),
            'year' => $value('year'),
            'from' => $value('from'),
            'to' => $value('to'),
        ];
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
