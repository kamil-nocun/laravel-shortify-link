<?php

use App\Support\StatsPeriod;
use Carbon\CarbonImmutable;

function resolvePeriod(array $input, ?CarbonImmutable $firstClick = null): StatsPeriod
{
    $input += ['period' => '30d', 'date' => null, 'month' => null, 'year' => null, 'from' => null, 'to' => null];

    return StatsPeriod::resolve($input, $firstClick, CarbonImmutable::parse('2026-10-09 12:00'));
}

test('presets cover the last N days including today', function () {
    $period = resolvePeriod(['period' => '30d']);

    expect($period->from->toDateTimeString())->toBe('2026-09-10 00:00:00')
        ->and($period->to->toDateTimeString())->toBe('2026-10-09 12:00:00')
        ->and($period->granularity)->toBe('day');
});

test('all starts at the first click', function () {
    $period = resolvePeriod(['period' => 'all'], CarbonImmutable::parse('2026-03-05 15:30'));

    expect($period->from->toDateTimeString())->toBe('2026-03-05 00:00:00')
        ->and($period->to->toDateTimeString())->toBe('2026-10-09 12:00:00')
        ->and($period->granularity)->toBe('month');
});

test('all without clicks is empty', function () {
    expect(resolvePeriod(['period' => 'all'])->from)->toBeNull();
});

test('day, month and year cover the whole unit', function () {
    $day = resolvePeriod(['period' => 'day', 'date' => '2026-02-03']);
    $month = resolvePeriod(['period' => 'month', 'month' => '2026-02']);
    $year = resolvePeriod(['period' => 'year', 'year' => '2025']);

    expect($day->from->toDateTimeString())->toBe('2026-02-03 00:00:00')
        ->and($day->to->toDateTimeString())->toBe('2026-02-03 23:59:59')
        ->and($month->from->toDateTimeString())->toBe('2026-02-01 00:00:00')
        ->and($month->to->toDateTimeString())->toBe('2026-02-28 23:59:59')
        ->and($month->granularity)->toBe('day')
        ->and($year->from->toDateTimeString())->toBe('2025-01-01 00:00:00')
        ->and($year->to->toDateTimeString())->toBe('2025-12-31 23:59:59')
        ->and($year->granularity)->toBe('month');
});

test('the end never goes past now', function () {
    expect(resolvePeriod(['period' => 'month', 'month' => '2026-10'])->to->toDateTimeString())->toBe('2026-10-09 12:00:00');
});

test('range runs from the start of from to the end of to', function () {
    $period = resolvePeriod(['period' => 'range', 'from' => '2026-01-01', 'to' => '2026-01-10']);

    expect($period->from->toDateTimeString())->toBe('2026-01-01 00:00:00')
        ->and($period->to->toDateTimeString())->toBe('2026-01-10 23:59:59');
});

test('granularity is month above 92 days and for year and all', function () {
    $ninetyTwo = resolvePeriod(['period' => 'range', 'from' => '2026-01-01', 'to' => '2026-04-02']);
    $ninetyThree = resolvePeriod(['period' => 'range', 'from' => '2026-01-01', 'to' => '2026-04-03']);

    expect($ninetyTwo->granularity)->toBe('day')
        ->and($ninetyThree->granularity)->toBe('month');
});
