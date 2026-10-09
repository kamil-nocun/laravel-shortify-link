<?php

namespace App\Http\Controllers;

use App\Http\Requests\Links\LinkStatsRequest;
use App\Models\Link;
use App\Support\StatsPeriod;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class LinkStatsController extends Controller
{
    private const TOP_LIMIT = 10;

    private const OPTIONS_LIMIT = 200;

    private const UNKNOWN = 'Unknown';

    /**
     * Show click statistics of a link for the requested period.
     */
    public function __invoke(LinkStatsRequest $request, Link $link): Response
    {
        $firstClick = $link->clicks()->min('clicked_at');
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
            'period' => $request->period(),
            'granularity' => $period->granularity,
            'total' => array_sum(array_column($series, 'count')),
            'series' => $series,
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
