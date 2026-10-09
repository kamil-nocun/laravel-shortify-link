<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class StatsSeries
{
    /**
     * Click counts per day or month from start to end, with zeros for empty buckets.
     *
     * @param  Collection<string, mixed>  $perDay  click counts keyed by Y-m-d
     * @return list<array{date: string, count: int}>
     */
    public static function build(Collection $perDay, CarbonImmutable $from, CarbonImmutable $to, string $granularity): array
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
}
