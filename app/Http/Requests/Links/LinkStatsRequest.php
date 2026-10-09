<?php

namespace App\Http\Requests\Links;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkStatsRequest extends FormRequest
{
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
     * Tables that are sorted and paginated independently.
     *
     * @var list<string>
     */
    public const TABLES = ['countries', 'cities', 'browsers', 'systems', 'devices'];

    /**
     * Allowed rows per page of a table.
     *
     * @var list<int>
     */
    public const PER_PAGE = [10, 25, 50, 100];

    /**
     * Allowed sort orders of a table.
     *
     * @var list<string>
     */
    public const SORTS = ['count_desc', 'count_asc', 'name_asc', 'name_desc'];

    /**
     * Sections of the stats payload that can be requested separately.
     *
     * @var list<string>
     */
    public const SECTIONS = ['summary', 'options', 'countries', 'cities', 'browsers', 'systems', 'devices'];

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
            'sections' => ['sometimes', 'array'],
            'sections.*' => [Rule::in(self::SECTIONS)],
            ...array_fill_keys(self::FILTERS, ['nullable', 'string', 'max:255']),
            ...$this->tableRules(),
        ];
    }

    /**
     * Rules of the sort, per page and page parameters of every table.
     *
     * @return array<string, array<mixed>>
     */
    private function tableRules(): array
    {
        $rules = [];

        foreach (self::TABLES as $table) {
            $rules["sort.{$table}"] = ['sometimes', Rule::in(self::SORTS)];
            $rules["{$table}_per_page"] = ['sometimes', Rule::in(self::PER_PAGE)];
            $rules["{$table}_page"] = ['sometimes', 'integer', 'min:1'];
        }

        return $rules;
    }

    /**
     * Requested sections; null when the parameter is not set (all sections).
     *
     * @return ?list<string>
     */
    public function sections(): ?array
    {
        return $this->has('sections') ? array_values($this->input('sections')) : null;
    }

    /**
     * Requested sort order of a table.
     */
    public function sort(string $table): string
    {
        return $this->input("sort.{$table}", self::SORTS[0]);
    }

    /**
     * Requested rows per page of a table.
     */
    public function perPage(string $table): int
    {
        return $this->integer("{$table}_per_page", self::PER_PAGE[0]);
    }

    /**
     * Requested page of a table.
     */
    public function page(string $table): int
    {
        return $this->integer("{$table}_page", 1);
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
