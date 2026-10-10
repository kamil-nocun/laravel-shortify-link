<?php

use App\Actions\AggregateDailyLinkStatistics;
use App\Models\Link;
use App\Models\LinkClick;
use Illuminate\Console\Scheduling\Schedule;

beforeEach(function () {
    $this->freezeTime();
    $this->link = Link::factory()->create();
});

function clicksOnDay($day, array $attributes = [], ?Link $link = null, int $times = 1): void
{
    LinkClick::factory()->count($times)->for($link ?? test()->link)->create(['clicked_at' => $day, ...$attributes]);
}

test('collapses a past day into one summary row per dimension set', function () {
    $day = now()->subDay();
    clicksOnDay($day->copy()->setTime(9, 15), ['country_code' => 'PL', 'country' => 'Poland', 'city' => 'Warsaw', 'browser' => 'Chrome'], times: 2);
    clicksOnDay($day->copy()->setTime(22, 5), ['country_code' => 'PL', 'country' => 'Poland', 'city' => 'Krakow', 'browser' => 'Chrome']);
    clicksOnDay($day->copy()->setTime(11, 0), ['country_code' => 'DE', 'country' => 'Germany', 'city' => 'Berlin', 'browser' => 'Firefox']);

    $days = app(AggregateDailyLinkStatistics::class)();

    $rows = LinkClick::orderBy('country_code')->get();
    expect($days)->toBe(1)
        ->and($rows)->toHaveCount(2)
        ->and($rows->pluck('is_summary')->all())->toBe([true, true])
        ->and($rows->pluck('city')->all())->toBe([null, null])
        ->and($rows->pluck('clicks', 'country_code')->all())->toBe(['DE' => 1, 'PL' => 3])
        ->and($rows->every(fn (LinkClick $row) => $row->clicked_at->equalTo($day->copy()->startOfDay())))->toBeTrue();
});

test('keeps null dimensions null and groups them together', function () {
    $nulls = ['country_code' => null, 'country' => null, 'city' => null, 'browser' => null, 'os' => null, 'device_type' => null, 'entry' => null, 'source' => null];
    clicksOnDay(now()->subDay(), $nulls, times: 3);

    app(AggregateDailyLinkStatistics::class)();

    $row = LinkClick::sole();
    expect($row->clicks)->toBe(3)
        ->and($row->only(array_keys($nulls)))->each->toBeNull();
});

test('leaves today untouched', function () {
    clicksOnDay(now()->startOfDay()->addSecond());
    clicksOnDay(now()->endOfDay()->subSecond());

    expect(app(AggregateDailyLinkStatistics::class)())->toBe(0)
        ->and(LinkClick::where('is_summary', false)->count())->toBe(2);
});

test('is idempotent', function () {
    clicksOnDay(now()->subDay(), times: 2);

    app(AggregateDailyLinkStatistics::class)();
    $before = LinkClick::all()->map->getAttributes()->all();

    expect(app(AggregateDailyLinkStatistics::class)())->toBe(0)
        ->and(LinkClick::all()->map->getAttributes()->all())->toBe($before);
});

test('catches up several missed days and returns how many days it processed', function () {
    clicksOnDay(now()->subDays(3), times: 2);
    clicksOnDay(now()->subDays(2));
    clicksOnDay(now()->subDay());

    expect(app(AggregateDailyLinkStatistics::class)())->toBe(3)
        ->and(LinkClick::count())->toBe(3)
        ->and((int) LinkClick::sum('clicks'))->toBe(4);
});

test('merges a late click into an existing summary', function () {
    $day = now()->subDay();
    clicksOnDay($day, ['country_code' => 'PL'], times: 2);
    app(AggregateDailyLinkStatistics::class)();
    clicksOnDay($day->copy()->setTime(23, 59), ['country_code' => 'PL']);

    expect(app(AggregateDailyLinkStatistics::class)())->toBe(1);

    $row = LinkClick::sole();
    expect($row->clicks)->toBe(3)->and($row->is_summary)->toBeTrue();
});

test('keeps the links separate', function () {
    $other = Link::factory()->create();
    clicksOnDay(now()->subDay(), times: 2);
    clicksOnDay(now()->subDay(), link: $other, times: 5);

    app(AggregateDailyLinkStatistics::class)();

    expect(LinkClick::where('link_id', $this->link->id)->sole()->clicks)->toBe(2)
        ->and(LinkClick::where('link_id', $other->id)->sole()->clicks)->toBe(5);
});

test('command prints the processed day count', function () {
    clicksOnDay(now()->subDays(2));
    clicksOnDay(now()->subDay());

    $this->artisan('stats:aggregate')->expectsOutputToContain('2')->assertSuccessful();
    $this->artisan('stats:aggregate')->expectsOutputToContain('0')->assertSuccessful();
});

test('aggregation is scheduled daily at 00:10 without overlapping', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command, 'stats:aggregate'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('10 0 * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});
