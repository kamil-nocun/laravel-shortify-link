<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;

class StatsPeriod
{
    private const MAX_DAILY_DAYS = 92;

    public function __construct(
        public readonly ?CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly string $granularity,
    ) {}

    /**
     * Turn request parameters into a date range and chart granularity.
     *
     * @param  array{period: string, date: ?string, month: ?string, year: ?string, from: ?string, to: ?string}  $input
     */
    public static function resolve(array $input, ?CarbonInterface $firstClick, CarbonInterface $now): self
    {
        $now = CarbonImmutable::instance($now);
        $period = $input['period'];

        [$from, $end] = match ($period) {
            '7d', '30d', '90d' => [$now->subDays((int) $period - 1)->startOfDay(), $now],
            'all' => [$firstClick === null ? null : CarbonImmutable::instance($firstClick)->startOfDay(), $now],
            'day' => [CarbonImmutable::parse($input['date'])->startOfDay(), CarbonImmutable::parse($input['date'])->endOfDay()],
            'month' => [
                CarbonImmutable::createFromFormat('!Y-m', $input['month'])->startOfMonth(),
                CarbonImmutable::createFromFormat('!Y-m', $input['month'])->endOfMonth(),
            ],
            'year' => [
                CarbonImmutable::create((int) $input['year'])->startOfYear(),
                CarbonImmutable::create((int) $input['year'])->endOfYear(),
            ],
            default => [CarbonImmutable::parse($input['from'])->startOfDay(), CarbonImmutable::parse($input['to'])->endOfDay()],
        };

        $to = $end->min($now);
        $isMonthly = in_array($period, ['year', 'all'], true)
            || ($from !== null && $from->diffInDays($to) > self::MAX_DAILY_DAYS);

        return new self($from, $to, $isMonthly ? 'month' : 'day');
    }

    /**
     * Whether the period holds no days at all (e.g. 'all' without any click).
     */
    public function isEmpty(): bool
    {
        return $this->from === null || $this->from->gt($this->to);
    }

    /**
     * Restrict a click query to this period.
     */
    public function constrain(Builder $query): Builder
    {
        return $this->isEmpty()
            ? $query->whereRaw('1 = 0')
            : $query->where('clicked_at', '>=', $this->from)->where('clicked_at', '<=', $this->to);
    }

    /**
     * Clicks per day or month of this period from a query already limited to it, empty days included.
     *
     * @return list<array{date: string, count: int}>
     */
    public function series(Builder $clicks): array
    {
        if ($this->isEmpty()) {
            return [];
        }

        $perDay = $clicks->selectRaw('DATE(clicked_at) as day, sum(clicks) as total')->groupBy('day')->pluck('total', 'day');

        return StatsSeries::build($perDay, $this->from, $this->to, $this->granularity);
    }
}
