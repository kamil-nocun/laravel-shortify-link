<?php

namespace App\Http\Controllers;

use App\Http\Requests\Links\LinkStatsRequest;
use App\Models\Link;
use Closure;
use Illuminate\Database\Query\Builder;
use Inertia\Inertia;
use Inertia\Response;

class LinkStatsController extends Controller
{
    private const TOP_LIMIT = 10;

    private const OPTIONS_LIMIT = 200;

    private const UNKNOWN = 'Unknown';

    /**
     * Show click statistics of a link for the requested number of days.
     */
    public function __invoke(LinkStatsRequest $request, Link $link): Response
    {
        $days = $request->days();
        $from = now()->subDays($days - 1)->startOfDay();
        $inRange = fn (): Builder => $link->clicks()->toBase()->where('clicked_at', '>=', $from);
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

        $daily = collect(range($days - 1, 0))->map(fn (int $ago): array => [
            'date' => now()->subDays($ago)->toDateString(),
            'count' => (int) ($perDay[now()->subDays($ago)->toDateString()] ?? 0),
        ])->all();

        $countries = $this->topBy($clicks, 'country');
        $browsers = $this->topBy($clicks, 'browser');
        $systems = $this->topBy($clicks, 'os');
        $devices = $this->topBy($clicks, 'device_type');

        $cities = $clicks()
            ->selectRaw('city, country, count(*) as total')
            ->groupBy('city', 'country')
            ->orderByDesc('total')
            ->limit(self::TOP_LIMIT)
            ->get()
            ->map(fn ($row): array => [
                'name' => collect([$row->city ?? self::UNKNOWN, $row->country])->filter()->implode(', '),
                'count' => (int) $row->total,
            ]);

        return Inertia::render('links/Stats', [
            'link' => $link,
            'days' => $days,
            'total' => array_sum(array_column($daily, 'count')),
            'daily' => $daily,
            'countries' => $countries,
            'cities' => $cities->values(),
            'browsers' => $browsers,
            'systems' => $systems,
            'devices' => $devices,
            'filters' => $filters,
            'options' => $this->options($inRange),
        ]);
    }

    /**
     * Most frequent values of a click column; missing values are grouped as Unknown.
     *
     * @param  Closure(): Builder  $clicks
     * @return list<array{name: string, count: int}>
     */
    private function topBy(Closure $clicks, string $column): array
    {
        $rows = $clicks()
            ->select($column)
            ->selectRaw('count(*) as total')
            ->groupBy($column)
            ->orderByDesc('total')
            ->limit(self::TOP_LIMIT)
            ->get()
            ->map(fn ($row): array => [
                'name' => is_string($row->{$column}) ? $row->{$column} : self::UNKNOWN,
                'count' => (int) $row->total,
            ])
            ->all();

        return array_values($rows);
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
