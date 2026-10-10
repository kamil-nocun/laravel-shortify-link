<?php

use App\Actions\AggregateDailyLinkStatistics;
use App\Models\Link;
use App\Models\LinkClick;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;

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

test('midnight boundaries split days exactly', function () {
    clicksOnDay(now()->startOfDay());
    clicksOnDay(now()->subDay()->startOfDay());
    clicksOnDay(now()->subDay()->endOfDay()->startOfSecond());

    expect(app(AggregateDailyLinkStatistics::class)())->toBe(1);

    $raw = LinkClick::where('is_summary', false)->get();
    $summary = LinkClick::where('is_summary', true)->sole();
    expect($raw)->toHaveCount(1)
        ->and($raw->first()->clicked_at->equalTo(now()->startOfDay()))->toBeTrue()
        ->and($summary->clicks)->toBe(2)
        ->and($summary->clicked_at->equalTo(now()->subDay()->startOfDay()))->toBeTrue();
});

test('a failing day rolls back alone and earlier days stay summarised', function () {
    clicksOnDay(now()->subDays(2), times: 2);
    clicksOnDay(now()->subDay(), times: 3);

    $inserts = 0;
    DB::listen(function ($query) use (&$inserts) {
        if (str_starts_with($query->sql, 'insert into `link_statistics`') && ++$inserts === 2) {
            throw new RuntimeException('boom');
        }
    });

    expect(fn () => app(AggregateDailyLinkStatistics::class)())->toThrow(RuntimeException::class);

    expect(LinkClick::where('is_summary', true)->sole()->clicks)->toBe(2)
        ->and(LinkClick::where('is_summary', false)->count())->toBe(3);
});

test('reports are identical before and after aggregation', function () {
    $owner = $this->link->user;
    $nulls = ['city' => null];
    clicksOnDay(now()->subDays(3), ['country' => 'Poland', 'country_code' => 'PL', 'browser' => 'Chrome', ...$nulls], times: 3);
    clicksOnDay(now()->subDays(2), ['country' => 'Germany', 'country_code' => 'DE', 'browser' => 'Firefox', 'source' => 'QR', ...$nulls], times: 2);
    clicksOnDay(now()->subDay(), ['country' => 'Poland', 'country_code' => 'PL', 'browser' => 'Firefox', ...$nulls]);

    $report = fn () => collect($this->actingAs($owner)->getJson(route('links.stats.data', [$this->link, 'period' => '30d']))->assertOk()->json())->except(['cities'])->all();
    $admin = fn () => $this->actingAs(User::factory()->superAdmin()->create())->get(route('admin.dashboard', ['period' => '30d']))->viewData('page')['props'];

    $before = $report();
    $adminBefore = collect($admin())->only(['total', 'series', 'topLinks'])->all();

    app(AggregateDailyLinkStatistics::class)();

    expect($report())->toBe($before)
        ->and(collect($admin())->only(['total', 'series', 'topLinks'])->all())->toBe($adminBefore);
});

test('the grouping read takes a shared lock', function () {
    clicksOnDay(now()->subDay());
    DB::enableQueryLog();

    app(AggregateDailyLinkStatistics::class)();

    expect(collect(DB::getQueryLog())->pluck('query')->contains(fn (string $sql) => str_contains($sql, 'group by') && str_contains($sql, 'lock in share mode')))->toBeTrue();
});
