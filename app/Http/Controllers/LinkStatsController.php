<?php

namespace App\Http\Controllers;

use App\Http\Requests\Links\LinkStatsRequest;
use App\Models\Link;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class LinkStatsController extends Controller
{
    private const TOP_LIMIT = 10;

    private const UNKNOWN = 'Unknown';

    /**
     * Show click statistics of a link for the requested number of days.
     */
    public function __invoke(LinkStatsRequest $request, Link $link): Response
    {
        $days = $request->days();
        $from = now()->subDays($days - 1)->startOfDay();
        $clicks = fn (): Builder => $link->clicks()->getQuery()->where('clicked_at', '>=', $from);

        $perDay = $clicks()
            ->selectRaw('DATE(clicked_at) as day, count(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $daily = collect(range($days - 1, 0))->map(fn (int $ago): array => [
            'date' => now()->subDays($ago)->toDateString(),
            'count' => (int) ($perDay[now()->subDays($ago)->toDateString()] ?? 0),
        ])->all();

        $countries = $clicks()
            ->selectRaw('country, count(*) as total')
            ->groupBy('country')
            ->orderByDesc('total')
            ->limit(self::TOP_LIMIT)
            ->get()
            ->map(fn ($row): array => ['name' => $row->country ?? self::UNKNOWN, 'count' => (int) $row->total]);

        $cities = $clicks()
            ->selectRaw('city, country, count(*) as total')
            ->groupBy('city', 'country')
            ->orderByDesc('total')
            ->limit(self::TOP_LIMIT)
            ->get()
            ->map(fn ($row): array => [
                'name' => $row->city === null ? self::UNKNOWN : collect([$row->city, $row->country])->filter()->implode(', '),
                'count' => (int) $row->total,
            ]);

        return Inertia::render('links/Stats', [
            'link' => $link,
            'days' => $days,
            'total' => array_sum(array_column($daily, 'count')),
            'daily' => $daily,
            'countries' => $countries->values(),
            'cities' => $cities->values(),
        ]);
    }
}
