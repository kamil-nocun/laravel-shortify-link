<?php

namespace App\Http\Controllers;

use App\Http\Requests\Links\LinkStatsRequest;
use App\Models\Link;
use App\Support\StatsPeriod;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LinkStatsController extends Controller
{
    private const OPTIONS_LIMIT = 200;

    private const UNKNOWN = 'Unknown';

    /**
     * Show click statistics of a link for the requested period.
     */
    public function __invoke(LinkStatsRequest $request, Link $link): Response
    {
        $firstClick = $request->period()['period'] === 'all' ? $link->clicks()->min('clicked_at') : null;
        $period = StatsPeriod::resolve($request->period(), $firstClick === null ? null : CarbonImmutable::parse($firstClick), now());
        $from = $period->from;
        $isEmpty = $from === null || $from->gt($period->to);
        $inRange = fn (): Builder => $isEmpty
            ? $link->clicks()->toBase()->whereRaw('1 = 0')
            : $link->clicks()->toBase()->where('clicked_at', '>=', $from)->where('clicked_at', '<=', $period->to);
        $filters = $request->filters();
        $clicks = function () use ($inRange, $filters): Builder {
            $query = $inRange();

            foreach (array_filter($filters, fn (?string $value): bool => $value !== null) as $column => $value) {
                $value === self::UNKNOWN ? $query->whereNull($column) : $query->where($column, $value);
            }

            return $query;
        };

        $perDay = $clicks()
            ->selectRaw('DATE(clicked_at) as day, count(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $series = $isEmpty ? [] : $this->series($perDay, $from, $period->to, $period->granularity);

        $tables = [
            'countries' => $this->table($clicks, 'countries', ['country'], $request),
            'cities' => $this->table($clicks, 'cities', ['city', 'country'], $request),
            'browsers' => $this->table($clicks, 'browsers', ['browser'], $request),
            'systems' => $this->table($clicks, 'systems', ['os'], $request),
            'devices' => $this->table($clicks, 'devices', ['device_type'], $request),
        ];

        return Inertia::render('links/Stats', [
            'link' => $link,
            'period' => $request->period(),
            'granularity' => $period->granularity,
            'total' => array_sum(array_column($series, 'count')),
            'series' => $series,
            ...$tables,
            'sort' => collect(LinkStatsRequest::TABLES)->mapWithKeys(fn (string $table): array => [$table => $request->sort($table)])->all(),
            'filters' => $filters,
            'options' => $this->options($inRange),
        ]);
    }

    /**
     * Click counts per day or month from start to end, with zeros for empty buckets.
     *
     * @param  Collection<string, mixed>  $perDay  click counts keyed by Y-m-d
     * @return list<array{date: string, count: int}>
     */
    private function series(Collection $perDay, CarbonImmutable $from, CarbonImmutable $to, string $granularity): array
    {
        $isMonthly = $granularity === 'month';
        $counts = [];

        foreach ($perDay as $day => $total) {
            $key = $isMonthly ? substr((string) $day, 0, 7) : (string) $day;
            $counts[$key] = ($counts[$key] ?? 0) + (int) $total;
        }

        $series = [];

        for ($cursor = $isMonthly ? $from->startOfMonth() : $from; $cursor->lte($to); $cursor = $isMonthly ? $cursor->addMonth() : $cursor->addDay()) {
            $key = $cursor->format($isMonthly ? 'Y-m' : 'Y-m-d');
            $series[] = ['date' => $key, 'count' => $counts[$key] ?? 0];
        }

        return $series;
    }

    /**
     * One sorted page of click counts grouped by the given columns (a fixed whitelist, never request input);
     * missing values are grouped as Unknown and an out-of-range page is clamped to the last one.
     *
     * @param  Closure(): Builder  $clicks
     * @param  list<string>  $columns  grouping columns; the first one is the name, the second (cities) the country
     * @return array{rows: list<array{name: string, count: int}>, page: int, lastPage: int, total: int, perPage: int}
     */
    private function table(Closure $clicks, string $table, array $columns, LinkStatsRequest $request): array
    {
        $perPage = $request->perPage($table);
        $sort = $request->sort($table);
        $direction = str_ends_with($sort, '_asc') ? 'asc' : 'desc';

        $total = DB::query()->fromSub($clicks()->select($columns)->groupBy($columns), 'groups')->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($request->page($table), $lastPage);

        $query = $clicks()->select($columns)->selectRaw('count(*) as total')->groupBy($columns);

        if (str_starts_with($sort, 'count_')) {
            $query->orderBy('total', $direction);
            $direction = 'asc';
        }

        foreach ($columns as $index => $column) {
            $query->orderByRaw($this->orderSql($column, $index === 0, $direction));
        }

        $rows = $query->forPage($page, $perPage)->get()
            ->map(fn ($row): array => [
                'name' => collect(array_map(fn (string $column): ?string => $row->{$column}, $columns))
                    ->map(fn (?string $value, int $index): ?string => $index === 0 ? ($value ?? self::UNKNOWN) : $value)
                    ->filter()->implode(', '),
                'count' => (int) $row->total,
            ])
            ->all();

        return ['rows' => array_values($rows), 'page' => $page, 'lastPage' => $lastPage, 'total' => $total, 'perPage' => $perPage];
    }

    /**
     * ORDER BY fragment for a whitelisted click column; the name column treats NULL as Unknown.
     *
     * @return literal-string
     */
    private function orderSql(string $column, bool $isName, string $direction): string
    {
        $expression = match ($column) {
            'country' => $isName ? "COALESCE(country, 'Unknown')" : 'country',
            'city' => "COALESCE(city, 'Unknown')",
            'browser' => "COALESCE(browser, 'Unknown')",
            'os' => "COALESCE(os, 'Unknown')",
            'device_type' => "COALESCE(device_type, 'Unknown')",
            default => throw new \InvalidArgumentException("Unsortable column {$column}"),
        };

        return $expression.($direction === 'asc' ? ' asc' : ' desc');
    }

    /**
     * Distinct values of every filterable column in alphabetical order, with Unknown last when some are missing;
     * fetched with one UNION query instead of one query per column.
     *
     * @param  Closure(): Builder  $inRange
     * @return array<string, list<string>>
     */
    private function options(Closure $inRange): array
    {
        $keys = ['country' => 'countries', 'city' => 'cities', 'browser' => 'browsers', 'os' => 'systems', 'device_type' => 'devices'];

        $query = null;

        foreach (array_keys($keys) as $column) {
            $part = $inRange()->select($column.' as value')->selectRaw('? as field', [$column])->distinct()->orderBy($column)->limit(self::OPTIONS_LIMIT);
            $query = $query === null ? $part : $query->unionAll($part);
        }

        $rows = $query->get()->groupBy('field');

        return collect($keys)->map(function (string $key, string $column) use ($rows): array {
            $values = $rows->get($column, collect())->pluck('value');
            $options = $values->filter(fn (mixed $value): bool => is_string($value))->values();

            if ($values->contains(null)) {
                $options->push(self::UNKNOWN);
            }

            return array_values($options->all());
        })->mapWithKeys(fn (array $options, string $column): array => [$keys[$column] => $options])->all();
    }
}
