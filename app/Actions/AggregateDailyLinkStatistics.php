<?php

namespace App\Actions;

use App\Models\LinkClick;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AggregateDailyLinkStatistics
{
    /**
     * Columns that survive aggregation; the city and the exact time are dropped.
     */
    private const DIMENSIONS = ['link_id', 'country_code', 'country', 'browser', 'os', 'device_type', 'entry', 'source'];

    /**
     * Collapse every finished day that still has individual clicks into daily summary rows; returns the number of days processed.
     */
    public function __invoke(): int
    {
        $days = LinkClick::query()
            ->where('is_summary', false)
            ->where('clicked_at', '<', now()->startOfDay())
            ->selectRaw('DATE(clicked_at) as day')->distinct()->orderBy('day')
            ->pluck('day');

        foreach ($days as $day) {
            DB::transaction(fn () => $this->summarise(CarbonImmutable::parse($day)->startOfDay()));
        }

        return $days->count();
    }

    /**
     * Replace the rows of one day with one summary row per dimension set.
     *
     * The grouping read takes a shared lock, so a click for this day is either counted or waits until the day is replaced.
     */
    private function summarise(CarbonImmutable $start): void
    {
        $day = fn () => LinkClick::query()->where('clicked_at', '>=', $start)->where('clicked_at', '<', $start->addDay());

        $summaries = $day()->select(self::DIMENSIONS)->selectRaw('sum(clicks) as clicks')->groupBy(self::DIMENSIONS)->sharedLock()->get();

        $day()->delete();

        $summaries->chunk(500)->each(fn ($chunk) => LinkClick::query()->insert($chunk->map(fn (LinkClick $summary): array => [
            'id' => (new LinkClick)->newUniqueId(),
            ...$summary->only(self::DIMENSIONS),
            'clicks' => (int) $summary->clicks,
            'is_summary' => true,
            'clicked_at' => $start,
        ])->all()));
    }
}
