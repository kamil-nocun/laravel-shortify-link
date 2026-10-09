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
            ->where('countries', [['name' => 'Poland', 'count' => 2], ['name' => 'Germany', 'count' => 1]])
            ->where('cities', [['name' => 'Warsaw, Poland', 'count' => 2], ['name' => 'Berlin, Germany', 'count' => 1]]));
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
    foreach ($inside as $moment) {
        clickOn($this->link, ['clicked_at' => $moment]);
    }
    foreach ($outside as $moment) {
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
    clickOn($this->link, ['clicked_at' => '2026-01-05 10:00:00']);
    clickOn($this->link, ['clicked_at' => '2026-01-20 10:00:00']);
    clickOn($this->link, ['clicked_at' => '2026-03-02 10:00:00']);

    $this->actingAs($this->user)->get(route('links.stats', [$this->link, 'period' => 'year', 'year' => '2026']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('series.0', ['date' => '2026-01', 'count' => 2])
            ->where('series.1', ['date' => '2026-02', 'count' => 0])
            ->where('series.2', ['date' => '2026-03', 'count' => 1])
            ->has('series', now()->month)
            ->where('total', 3));
});

test('a range in the future returns zeros', function (array $query) {
    clickOn($this->link);

    $this->actingAs($this->user)->get(route('links.stats', [$this->link, ...$query]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('total', 0)->where('series', [])->where('countries', [])->where('options.browsers', []));
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
            ->where('countries', [['name' => 'Germany', 'count' => 1]])
            ->where('browsers', [['name' => 'Opera', 'count' => 1]])
            ->where('options.countries', ['Germany'])
            ->where('options.browsers', ['Opera']));
});

test('clicks older than the range and clicks of other links are excluded', function () {
    clickOn($this->link, ['clicked_at' => now()->subDays(8)]);
    clickOn(Link::factory()->for($this->user)->create());

    $this->actingAs($this->user)->get(route('links.stats', [$this->link, 'period' => '7d']))
        ->assertInertia(fn (Assert $page) => $page->where('total', 0)->where('countries', [])->where('cities', []));
});

test('null geo is shown as Unknown', function () {
    clickOn($this->link, ['country_code' => null, 'country' => null, 'city' => null]);

    $this->actingAs($this->user)->get(route('links.stats', $this->link))
        ->assertInertia(fn (Assert $page) => $page
            ->where('countries', [['name' => 'Unknown', 'count' => 1]])
            ->where('cities', [['name' => 'Unknown', 'count' => 1]]));
});

test('cities with unknown name are listed per country, not merged under one label', function () {
    clickOn($this->link, ['country' => 'Poland', 'city' => null]);
    clickOn($this->link, ['country' => 'Germany', 'city' => null]);

    $this->actingAs($this->user)->get(route('links.stats', $this->link))
        ->assertInertia(fn (Assert $page) => $page
            ->where('cities.0.name', fn (string $name) => in_array($name, ['Unknown, Poland', 'Unknown, Germany'], true))
            ->where('cities.1.name', fn (string $name) => in_array($name, ['Unknown, Poland', 'Unknown, Germany'], true))
            ->where('cities.0.name', fn (string $name) => $name !== 'Unknown')
            ->has('cities', 2));
});

test('countries and cities are capped at the top 10', function () {
    foreach (range(1, 12) as $index) {
        clickOn($this->link, ['country' => "Country {$index}", 'city' => "City {$index}"]);
    }

    $this->actingAs($this->user)->get(route('links.stats', $this->link))
        ->assertInertia(fn (Assert $page) => $page->has('countries', 10)->has('cities', 10));
});

test('browsers, systems and devices are counted and ordered', function () {
    clickOn($this->link);
    clickOn($this->link);
    clickOn($this->link, ['browser' => 'Firefox', 'os' => 'Linux', 'device_type' => 'smartphone']);

    $this->actingAs($this->user)->get(route('links.stats', $this->link))
        ->assertInertia(fn (Assert $page) => $page
            ->where('browsers', [['name' => 'Chrome', 'count' => 2], ['name' => 'Firefox', 'count' => 1]])
            ->where('systems', [['name' => 'Windows', 'count' => 2], ['name' => 'Linux', 'count' => 1]])
            ->where('devices', [['name' => 'desktop', 'count' => 2], ['name' => 'smartphone', 'count' => 1]]));
});

test('null client fields are shown as Unknown', function () {
    clickOn($this->link, ['browser' => null, 'os' => null, 'device_type' => null]);

    $this->actingAs($this->user)->get(route('links.stats', $this->link))
        ->assertInertia(fn (Assert $page) => $page
            ->where('browsers', [['name' => 'Unknown', 'count' => 1]])
            ->where('systems', [['name' => 'Unknown', 'count' => 1]])
            ->where('devices', [['name' => 'Unknown', 'count' => 1]]));
});

test('client tables are capped at the top 10', function () {
    foreach (range(1, 12) as $index) {
        clickOn($this->link, ['browser' => "Browser {$index}", 'os' => "OS {$index}", 'device_type' => "device {$index}"]);
    }

    $this->actingAs($this->user)->get(route('links.stats', $this->link))
        ->assertInertia(fn (Assert $page) => $page->has('browsers', 10)->has('systems', 10)->has('devices', 10));
});

test('client stats respect the range and the link', function () {
    clickOn($this->link, ['clicked_at' => now()->subDays(8)]);
    clickOn(Link::factory()->for($this->user)->create());

    $this->actingAs($this->user)->get(route('links.stats', [$this->link, 'period' => '7d']))
        ->assertInertia(fn (Assert $page) => $page->where('browsers', [])->where('systems', [])->where('devices', []));
});

test('each filter narrows the total and the tables', function (string $filter, string $value, array $match, array $other) {
    clickOn($this->link, $match);
    clickOn($this->link, $match);
    clickOn($this->link, $other);

    $this->actingAs($this->user)->get(route('links.stats', [$this->link, $filter => $value]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('total', 2)
            ->where('filters.'.$filter, $value)
            ->where('browsers.0.count', 2)
            ->has('browsers', 1));
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
        ->assertInertia(fn (Assert $page) => $page->where('total', 1)->where('browsers', [['name' => 'Unknown', 'count' => 1]]));
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
        ->assertInertia(fn (Assert $page) => $page->where('total', 0)->where('browsers', []));
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
