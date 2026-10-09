<?php

use App\Models\Link;
use App\Models\LinkClick;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->freezeTime();
    $this->user = User::factory()->create();
    $this->link = Link::factory()->for($this->user)->create();
});

function clickOn(Link $link, array $attributes = []): LinkClick
{
    return LinkClick::factory()->for($link)->create($attributes);
}

test('owner sees stats with total, series, countries and cities', function () {
    clickOn($this->link);
    clickOn($this->link);
    clickOn($this->link, ['clicked_at' => now()->subDays(2), 'country_code' => 'DE', 'country' => 'Germany', 'city' => 'Berlin']);

    $this->actingAs($this->user)->get(route('links.stats', $this->link))
        ->assertInertia(fn (Assert $page) => $page
            ->component('links/Stats')
            ->where('link.id', $this->link->id)
            ->where('total', 3)
            ->where('series.29.count', 2)
            ->where('series.27.count', 1)
            ->where('countries.rows', [['name' => 'Poland', 'count' => 2], ['name' => 'Germany', 'count' => 1]])
            ->where('cities.rows', [['name' => 'Warsaw, Poland', 'count' => 2], ['name' => 'Berlin, Germany', 'count' => 1]]));
});

test('period defaults to 30d and the series covers every day with zeros', function () {
    clickOn($this->link);

    $this->actingAs($this->user)->get(route('links.stats', $this->link))
        ->assertInertia(fn (Assert $page) => $page
            ->where('period.period', '30d')
            ->where('granularity', 'day')
            ->has('series', 30)
            ->where('series.0.date', now()->subDays(29)->toDateString())
            ->where('series.0.count', 0)
            ->where('series.29.date', now()->toDateString()));
});

test('period selects the range', function (string $period, int $days) {
    $this->actingAs($this->user)->get(route('links.stats', [$this->link, 'period' => $period]))
        ->assertInertia(fn (Assert $page) => $page->where('period.period', $period)->has('series', $days));
})->with([['7d', 7], ['30d', 30], ['90d', 90]]);

test('day, month, year and range limit the clicks', function (array $query, array $inside, array $outside) {
    foreach ([...$inside, ...$outside] as $moment) {
        clickOn($this->link, ['clicked_at' => $moment]);
    }

    $this->actingAs($this->user)->get(route('links.stats', [$this->link, ...$query]))
        ->assertInertia(fn (Assert $page) => $page->where('total', count($inside)));
})->with([
    'day' => [['period' => 'day', 'date' => '2026-03-10'], ['2026-03-10 00:00:00', '2026-03-10 23:59:59'], ['2026-03-09 23:59:59', '2026-03-11 00:00:00']],
    'month' => [['period' => 'month', 'month' => '2026-03'], ['2026-03-01 00:00:00', '2026-03-31 23:59:59'], ['2026-02-28 23:59:59', '2026-04-01 00:00:00']],
    'year' => [['period' => 'year', 'year' => '2025'], ['2025-01-01 00:00:00', '2025-12-31 23:59:59'], ['2024-12-31 23:59:59', '2026-01-01 00:00:00']],
    'range' => [['period' => 'range', 'from' => '2026-03-10', 'to' => '2026-03-12'], ['2026-03-10 00:00:00', '2026-03-12 23:59:59'], ['2026-03-09 23:59:59', '2026-03-13 00:00:00']],
]);

test('all covers every click of the link and ignores other links', function () {
    clickOn($this->link, ['clicked_at' => '2025-11-20 10:00:00']);
    clickOn($this->link);
    clickOn(Link::factory()->for($this->user)->create(), ['clicked_at' => '2024-01-01 10:00:00']);

    $this->actingAs($this->user)->get(route('links.stats', [$this->link, 'period' => 'all']))
        ->assertInertia(fn (Assert $page) => $page->where('total', 2)->where('series.0.date', '2025-11'));
});

test('all without clicks returns an empty series and zero total', function () {
    $this->actingAs($this->user)->get(route('links.stats', [$this->link, 'period' => 'all']))
        ->assertInertia(fn (Assert $page) => $page->where('total', 0)->where('series', [])->where('options.countries', []));
});

test('series is monthly above 92 days and for year and all', function (array $query, string $granularity) {
    clickOn($this->link, ['clicked_at' => '2026-01-15 10:00:00']);

    $this->actingAs($this->user)->get(route('links.stats', [$this->link, ...$query]))
        ->assertInertia(fn (Assert $page) => $page->where('granularity', $granularity));
})->with([
    'range 92 days' => [['period' => 'range', 'from' => '2026-01-01', 'to' => '2026-04-02'], 'day'],
    'range 93 days' => [['period' => 'range', 'from' => '2026-01-01', 'to' => '2026-04-03'], 'month'],
    'year' => [['period' => 'year', 'year' => '2026'], 'month'],
    'all' => [['period' => 'all'], 'month'],
]);

test('monthly series sums days per month and fills empty months with zeros', function () {
    $year = now()->year - 1;
    clickOn($this->link, ['clicked_at' => "{$year}-01-05 10:00:00"]);
    clickOn($this->link, ['clicked_at' => "{$year}-01-20 10:00:00"]);
    clickOn($this->link, ['clicked_at' => "{$year}-03-02 10:00:00"]);

    $this->actingAs($this->user)->get(route('links.stats', [$this->link, 'period' => 'year', 'year' => $year]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('series.0', ['date' => "{$year}-01", 'count' => 2])
            ->where('series.1', ['date' => "{$year}-02", 'count' => 0])
            ->where('series.2', ['date' => "{$year}-03", 'count' => 1])
            ->has('series', 12)
            ->where('total', 3));
});

test('all fills zeros across a year boundary', function () {
    $year = now()->year - 2;
    clickOn($this->link, ['clicked_at' => "{$year}-12-15 10:00:00"]);
    clickOn($this->link, ['clicked_at' => ($year + 1).'-02-10 10:00:00']);

    $this->actingAs($this->user)->get(route('links.stats', [$this->link, 'period' => 'all']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('series.0', ['date' => "{$year}-12", 'count' => 1])
            ->where('series.1', ['date' => ($year + 1).'-01', 'count' => 0])
            ->where('series.2', ['date' => ($year + 1).'-02', 'count' => 1]));
});

test('an inverted explicit range is rejected and renders no stats', function () {
    $this->actingAs($this->user)->get(route('links.stats', [$this->link, 'period' => 'range', 'from' => '2026-03-12', 'to' => '2026-03-10']))
        ->assertSessionHasErrors('from')
        ->assertRedirect();
});

test('a range in the future returns zeros', function (array $query) {
    clickOn($this->link);

    $this->actingAs($this->user)->get(route('links.stats', [$this->link, ...$query]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('total', 0)->where('series', [])->where('countries.rows', [])->where('options.browsers', []));
})->with([
    'year' => [['period' => 'year', 'year' => '2030']],
    'month' => [['period' => 'month', 'month' => '2030-01']],
    'day' => [['period' => 'day', 'date' => '2030-01-01']],
]);

test('period applies to totals, tables and options', function () {
    clickOn($this->link, ['clicked_at' => '2026-03-10 10:00:00', 'country' => 'Germany', 'browser' => 'Opera']);
    clickOn($this->link, ['clicked_at' => '2026-03-11 10:00:00', 'country' => 'Spain', 'browser' => 'Safari']);

    $this->actingAs($this->user)->get(route('links.stats', [$this->link, 'period' => 'day', 'date' => '2026-03-10']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('total', 1)
            ->where('countries.rows', [['name' => 'Germany', 'count' => 1]])
            ->where('browsers.rows', [['name' => 'Opera', 'count' => 1]])
            ->where('options.countries', ['Germany'])
            ->where('options.browsers', ['Opera']));
});

test('clicks older than the range and clicks of other links are excluded', function () {
    clickOn($this->link, ['clicked_at' => now()->subDays(8)]);
    clickOn(Link::factory()->for($this->user)->create());

    $this->actingAs($this->user)->get(route('links.stats', [$this->link, 'period' => '7d']))
        ->assertInertia(fn (Assert $page) => $page->where('total', 0)->where('countries.rows', [])->where('cities.rows', []));
});

test('null geo is shown as Unknown', function () {
    clickOn($this->link, ['country_code' => null, 'country' => null, 'city' => null]);

    $this->actingAs($this->user)->get(route('links.stats', $this->link))
        ->assertInertia(fn (Assert $page) => $page
            ->where('countries.rows', [['name' => 'Unknown', 'count' => 1]])
            ->where('cities.rows', [['name' => 'Unknown', 'count' => 1]]));
});

test('cities with unknown name are listed per country, not merged under one label', function () {
    clickOn($this->link, ['country' => 'Poland', 'city' => null]);
    clickOn($this->link, ['country' => 'Germany', 'city' => null]);

    $this->actingAs($this->user)->get(route('links.stats', $this->link))
        ->assertInertia(fn (Assert $page) => $page
            ->where('cities.rows.0.name', fn (string $name) => in_array($name, ['Unknown, Poland', 'Unknown, Germany'], true))
            ->where('cities.rows.1.name', fn (string $name) => in_array($name, ['Unknown, Poland', 'Unknown, Germany'], true))
            ->where('cities.rows.0.name', fn (string $name) => $name !== 'Unknown')
            ->has('cities.rows', 2));
});

test('countries and cities show the first 10 rows with the full total', function () {
    foreach (range(1, 12) as $index) {
        clickOn($this->link, ['country' => "Country {$index}", 'city' => "City {$index}"]);
    }

    $this->actingAs($this->user)->get(route('links.stats', $this->link))
        ->assertInertia(fn (Assert $page) => $page->has('countries.rows', 10)->where('countries.total', 12)->where('countries.perPage', 10)->where('countries.lastPage', 2)
            ->has('cities.rows', 10)->where('cities.total', 12));
});

test('browsers, systems and devices are counted and ordered', function () {
    clickOn($this->link);
    clickOn($this->link);
    clickOn($this->link, ['browser' => 'Firefox', 'os' => 'Linux', 'device_type' => 'smartphone']);

    $this->actingAs($this->user)->get(route('links.stats', $this->link))
        ->assertInertia(fn (Assert $page) => $page
            ->where('browsers.rows', [['name' => 'Chrome', 'count' => 2], ['name' => 'Firefox', 'count' => 1]])
            ->where('systems.rows', [['name' => 'Windows', 'count' => 2], ['name' => 'Linux', 'count' => 1]])
            ->where('devices.rows', [['name' => 'desktop', 'count' => 2], ['name' => 'smartphone', 'count' => 1]]));
});

test('null client fields are shown as Unknown', function () {
    clickOn($this->link, ['browser' => null, 'os' => null, 'device_type' => null]);

    $this->actingAs($this->user)->get(route('links.stats', $this->link))
        ->assertInertia(fn (Assert $page) => $page
            ->where('browsers.rows', [['name' => 'Unknown', 'count' => 1]])
            ->where('systems.rows', [['name' => 'Unknown', 'count' => 1]])
            ->where('devices.rows', [['name' => 'Unknown', 'count' => 1]]));
});

test('client tables show the first 10 rows with the full total', function () {
    foreach (range(1, 12) as $index) {
        clickOn($this->link, ['browser' => "Browser {$index}", 'os' => "OS {$index}", 'device_type' => "device {$index}"]);
    }

    $this->actingAs($this->user)->get(route('links.stats', $this->link))
        ->assertInertia(fn (Assert $page) => $page->has('browsers.rows', 10)->where('browsers.total', 12)->has('systems.rows', 10)->has('devices.rows', 10));
});

test('client stats respect the range and the link', function () {
    clickOn($this->link, ['clicked_at' => now()->subDays(8)]);
    clickOn(Link::factory()->for($this->user)->create());

    $this->actingAs($this->user)->get(route('links.stats', [$this->link, 'period' => '7d']))
        ->assertInertia(fn (Assert $page) => $page->where('browsers.rows', [])->where('systems.rows', [])->where('devices.rows', []));
});

test('each filter narrows the total and the tables', function (string $filter, string $value, array $match, array $other) {
    clickOn($this->link, $match);
    clickOn($this->link, $match);
    clickOn($this->link, $other);

    $this->actingAs($this->user)->get(route('links.stats', [$this->link, $filter => $value]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('total', 2)
            ->where('filters.'.$filter, $value)
            ->where('browsers.rows.0.count', 2)
            ->has('browsers.rows', 1));
})->with([
    'country' => ['country', 'Germany', ['country' => 'Germany', 'browser' => 'Chrome'], ['country' => 'Poland', 'browser' => 'Firefox']],
    'city' => ['city', 'Berlin', ['city' => 'Berlin', 'browser' => 'Chrome'], ['city' => 'Warsaw', 'browser' => 'Firefox']],
    'browser' => ['browser', 'Chrome', ['browser' => 'Chrome'], ['browser' => 'Firefox']],
    'os' => ['os', 'Linux', ['os' => 'Linux', 'browser' => 'Chrome'], ['os' => 'Windows', 'browser' => 'Firefox']],
    'device_type' => ['device_type', 'tablet', ['device_type' => 'tablet', 'browser' => 'Chrome'], ['device_type' => 'desktop', 'browser' => 'Firefox']],
]);

test('filters combine with AND', function () {
    clickOn($this->link, ['country' => 'Poland', 'browser' => 'Chrome']);
    clickOn($this->link, ['country' => 'Poland', 'browser' => 'Firefox']);
    clickOn($this->link, ['country' => 'Germany', 'browser' => 'Chrome']);

    $this->actingAs($this->user)->get(route('links.stats', [$this->link, 'country' => 'Poland', 'browser' => 'Chrome']))
        ->assertInertia(fn (Assert $page) => $page->where('total', 1));
});

test('the Unknown filter matches clicks with null values', function () {
    clickOn($this->link, ['browser' => null]);
    clickOn($this->link, ['browser' => 'Chrome']);

    $this->actingAs($this->user)->get(route('links.stats', [$this->link, 'browser' => 'Unknown']))
        ->assertInertia(fn (Assert $page) => $page->where('total', 1)->where('browsers.rows', [['name' => 'Unknown', 'count' => 1]]));
});

test('the city filter matches the plain city name', function () {
    clickOn($this->link, ['city' => 'Warsaw', 'country' => 'Poland']);

    $this->actingAs($this->user)->get(route('links.stats', [$this->link, 'city' => 'Warsaw']))
        ->assertInertia(fn (Assert $page) => $page->where('total', 1));
    $this->actingAs($this->user)->get(route('links.stats', [$this->link, 'city' => 'Warsaw, Poland']))
        ->assertInertia(fn (Assert $page) => $page->where('total', 0));
});

test('a filter never reveals clicks of another link', function () {
    $other = Link::factory()->for($this->user)->create();
    clickOn($other, ['browser' => 'Opera']);

    $this->actingAs($this->user)->get(route('links.stats', [$this->link, 'browser' => 'Opera']))
        ->assertInertia(fn (Assert $page) => $page->where('total', 0)->where('browsers.rows', []));
});

test('an array filter value fails validation', function () {
    $this->actingAs($this->user)->get(route('links.stats', $this->link).'?browser[]=Chrome')
        ->assertSessionHasErrors('browser');
});

test('options list the distinct values of the link within the range', function () {
    clickOn($this->link, ['country' => 'Poland', 'city' => 'Warsaw', 'browser' => 'Chrome', 'os' => 'Windows', 'device_type' => 'desktop']);
    clickOn($this->link, ['country' => 'Germany', 'city' => null, 'browser' => 'Firefox', 'os' => 'Linux', 'device_type' => 'tablet']);
    clickOn($this->link, ['country' => 'Spain', 'clicked_at' => now()->subDays(40)]);
    clickOn(Link::factory()->for($this->user)->create(), ['country' => 'France']);

    $this->actingAs($this->user)->get(route('links.stats', $this->link))
        ->assertInertia(fn (Assert $page) => $page
            ->where('options.countries', ['Germany', 'Poland'])
            ->where('options.cities', ['Warsaw', 'Unknown'])
            ->where('options.browsers', ['Chrome', 'Firefox'])
            ->where('options.systems', ['Linux', 'Windows'])
            ->where('options.devices', ['desktop', 'tablet']));
});

test('options do not depend on the other filters', function () {
    clickOn($this->link, ['country' => 'Poland', 'browser' => 'Chrome']);
    clickOn($this->link, ['country' => 'Germany', 'browser' => 'Firefox']);

    $this->actingAs($this->user)->get(route('links.stats', [$this->link, 'country' => 'Poland']))
        ->assertInertia(fn (Assert $page) => $page->where('options.browsers', ['Chrome', 'Firefox']));
});

test('filters default to null', function () {
    $this->actingAs($this->user)->get(route('links.stats', $this->link))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters', ['country' => null, 'city' => null, 'browser' => null, 'os' => null, 'device_type' => null]));
});

test('a filter value longer than 255 characters fails validation', function () {
    $this->actingAs($this->user)->get(route('links.stats', [$this->link, 'browser' => str_repeat('a', 256)]))
        ->assertSessionHasErrors('browser');
});

test('other users are bounced to the list with an error toast', function () {
    $this->actingAs(User::factory()->create())->get(route('links.stats', $this->link))->assertRedirect(route('links.index'));
});

test('guests are redirected to login', function () {
    $this->get(route('links.stats', $this->link))->assertRedirect(route('login'));
});

test('filter options are loaded with a single query', function () {
    clickOn($this->link);
    DB::enableQueryLog();

    $this->actingAs($this->user)->get(route('links.stats', $this->link))->assertOk();

    $optionQueries = collect(DB::getQueryLog())
        ->filter(fn (array $entry): bool => str_contains($entry['query'], 'distinct'));
    expect($optionQueries)->toHaveCount(1);
});

test('each option list is capped at 200 values independently', function () {
    foreach (range(1, 205) as $index) {
        clickOn($this->link, ['browser' => sprintf('Browser %03d', $index), 'os' => 'Linux']);
    }

    $this->actingAs($this->user)->get(route('links.stats', $this->link))
        ->assertInertia(fn (Assert $page) => $page
            ->has('options.browsers', 200)
            ->where('options.browsers.0', 'Browser 001')
            ->where('options.systems', ['Linux'])
            ->where('options.countries', ['Poland']));
});

test('period must be one of the allowed values', function () {
    $this->actingAs($this->user)->get(route('links.stats', [$this->link, 'period' => 'week']))
        ->assertSessionHasErrors('period');
});

test('day, month, year and range require their parameters', function (array $query, string $field) {
    $this->actingAs($this->user)->get(route('links.stats', [$this->link, ...$query]))
        ->assertSessionHasErrors($field);
})->with([
    'day' => [['period' => 'day'], 'date'],
    'month' => [['period' => 'month'], 'month'],
    'year' => [['period' => 'year'], 'year'],
    'range from' => [['period' => 'range', 'to' => '2026-01-10'], 'from'],
    'range to' => [['period' => 'range', 'from' => '2026-01-10'], 'to'],
]);

test('date formats are validated', function (array $query, string $field) {
    $this->actingAs($this->user)->get(route('links.stats', [$this->link, ...$query]))
        ->assertSessionHasErrors($field);
})->with([
    'date' => [['period' => 'day', 'date' => '09-10-2026'], 'date'],
    'month' => [['period' => 'month', 'month' => '2026-13'], 'month'],
    'year' => [['period' => 'year', 'year' => '26'], 'year'],
]);

test('range must not be reversed or longer than 5 years', function (string $from, string $to, string $field) {
    $this->actingAs($this->user)->get(route('links.stats', [$this->link, 'period' => 'range', 'from' => $from, 'to' => $to]))
        ->assertSessionHasErrors($field);
})->with([
    'reversed' => ['2026-02-01', '2026-01-01', 'from'],
    'too long' => ['2020-01-01', '2026-01-01', 'to'],
]);

test('a malformed from reports a validation error instead of failing', function () {
    $this->actingAs($this->user)->get(route('links.stats', [$this->link, 'period' => 'range', 'from' => 'abc', 'to' => '2026-01-01']))
        ->assertSessionHasErrors('from')
        ->assertSessionDoesntHaveErrors('to');
});

test('a range of exactly 5 years is allowed', function () {
    $this->actingAs($this->user)->get(route('links.stats', [$this->link, 'period' => 'range', 'from' => '2021-01-01', 'to' => '2026-01-01']))
        ->assertSessionDoesntHaveErrors();
});

function manyCities(int $count): void
{
    foreach (range(1, $count) as $index) {
        clickOn(test()->link, ['country' => 'Poland', 'city' => sprintf('City %02d', $index)]);
    }
}

test('each per page value changes rows and lastPage', function (int $perPage) {
    manyCities(30);

    $this->actingAs($this->user)->get(route('links.stats', [$this->link, 'cities_per_page' => $perPage]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('cities.rows', min($perPage, 30))
            ->where('cities.perPage', $perPage)
            ->where('cities.total', 30)
            ->where('cities.lastPage', (int) ceil(30 / $perPage)));
})->with([10, 25, 50, 100]);

test('the last page holds the remainder', function () {
    manyCities(25);

    $this->actingAs($this->user)->get(route('links.stats', [$this->link, 'cities_page' => 3]))
        ->assertInertia(fn (Assert $page) => $page->where('cities.page', 3)->has('cities.rows', 5));
});

test('a page past the end returns the last page', function () {
    manyCities(25);

    $this->actingAs($this->user)->get(route('links.stats', [$this->link, 'cities_page' => 99]))
        ->assertInertia(fn (Assert $page) => $page->where('cities.page', 3)->where('cities.lastPage', 3)->has('cities.rows', 5));
});

test('table pages and per page are independent', function () {
    manyCities(25);

    $this->actingAs($this->user)->get(route('links.stats', [$this->link, 'countries_page' => 2, 'countries_per_page' => 25]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('countries.page', 1)
            ->where('countries.perPage', 25)
            ->where('cities.page', 1)
            ->where('cities.perPage', 10)
            ->has('cities.rows', 10));
});

test('rows are sorted by variant', function (string $sort, array $expected) {
    clickOn($this->link, ['browser' => 'Chrome']);
    clickOn($this->link, ['browser' => 'Chrome']);
    clickOn($this->link, ['browser' => 'Chrome']);
    clickOn($this->link, ['browser' => 'Firefox']);
    clickOn($this->link, ['browser' => 'Firefox']);
    clickOn($this->link, ['browser' => 'Brave']);
    clickOn($this->link, ['browser' => null]);

    $this->actingAs($this->user)->get(route('links.stats', [$this->link, 'sort' => ['browsers' => $sort]]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('browsers.rows', fn ($rows) => collect($rows)->pluck('name')->all() === $expected)
            ->where('sort.browsers', $sort));
})->with([
    'count_desc' => ['count_desc', ['Chrome', 'Firefox', 'Brave', 'Unknown']],
    'count_asc' => ['count_asc', ['Brave', 'Unknown', 'Firefox', 'Chrome']],
    'name_asc' => ['name_asc', ['Brave', 'Chrome', 'Firefox', 'Unknown']],
    'name_desc' => ['name_desc', ['Unknown', 'Firefox', 'Chrome', 'Brave']],
]);

test('ties keep the same order across pages', function () {
    manyCities(30);
    $names = [];

    foreach ([1, 2, 3] as $page) {
        $this->actingAs($this->user)->get(route('links.stats', [$this->link, 'cities_page' => $page]))
            ->assertInertia(function (Assert $inertia) use (&$names) {
                $names = [...$names, ...collect($inertia->toArray()['props']['cities']['rows'])->pluck('name')->all()];
            });
    }

    expect($names)->toHaveCount(30)->and(array_unique($names))->toHaveCount(30);
});

test('sort echoes the current values and defaults to count_desc', function () {
    $this->actingAs($this->user)->get(route('links.stats', $this->link))
        ->assertInertia(fn (Assert $page) => $page->where('sort', array_fill_keys(['countries', 'cities', 'browsers', 'systems', 'devices'], 'count_desc')));

    $this->actingAs($this->user)->get(route('links.stats', [$this->link, 'sort' => ['cities' => 'name_asc']]))
        ->assertInertia(fn (Assert $page) => $page->where('sort.cities', 'name_asc')->where('sort.countries', 'count_desc'));
});

test('invalid sort, per page or page fails validation', function (array $query, string $field) {
    $this->actingAs($this->user)->get(route('links.stats', [$this->link, ...$query]))
        ->assertSessionHasErrors($field);
})->with([
    'sort' => [['sort' => ['countries' => 'x']], 'sort.countries'],
    'per page' => [['countries_per_page' => 7], 'countries_per_page'],
    'page zero' => [['countries_page' => 0], 'countries_page'],
    'page text' => [['countries_page' => 'abc'], 'countries_page'],
]);

test('pagination respects period and filters', function () {
    manyCities(12);
    clickOn($this->link, ['country' => 'Germany', 'city' => 'Berlin', 'clicked_at' => now()->subDays(60)]);

    $this->actingAs($this->user)->get(route('links.stats', [$this->link, 'country' => 'Poland']))
        ->assertInertia(fn (Assert $page) => $page->where('cities.total', 12)->where('cities.lastPage', 2)->where('countries.total', 1));
});
