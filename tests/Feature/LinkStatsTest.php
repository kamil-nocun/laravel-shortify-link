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

test('owner sees stats with total, daily series, countries and cities', function () {
    clickOn($this->link);
    clickOn($this->link);
    clickOn($this->link, ['clicked_at' => now()->subDays(2), 'country_code' => 'DE', 'country' => 'Germany', 'city' => 'Berlin']);

    $this->actingAs($this->user)->get(route('links.stats', $this->link))
        ->assertInertia(fn (Assert $page) => $page
            ->component('links/Stats')
            ->where('link.id', $this->link->id)
            ->where('total', 3)
            ->where('daily.29.count', 2)
            ->where('daily.27.count', 1)
            ->where('countries', [['name' => 'Poland', 'count' => 2], ['name' => 'Germany', 'count' => 1]])
            ->where('cities', [['name' => 'Warsaw, Poland', 'count' => 2], ['name' => 'Berlin, Germany', 'count' => 1]]));
});

test('days defaults to 30 and the daily series covers every day with zeros', function () {
    clickOn($this->link);

    $this->actingAs($this->user)->get(route('links.stats', $this->link))
        ->assertInertia(fn (Assert $page) => $page
            ->where('days', 30)
            ->has('daily', 30)
            ->where('daily.0.date', now()->subDays(29)->toDateString())
            ->where('daily.0.count', 0)
            ->where('daily.29.date', now()->toDateString()));
});

test('days selects the range', function (int $days) {
    $this->actingAs($this->user)->get(route('links.stats', [$this->link, 'days' => $days]))
        ->assertInertia(fn (Assert $page) => $page->where('days', $days)->has('daily', $days));
})->with([7, 30, 90]);

test('clicks older than the range and clicks of other links are excluded', function () {
    clickOn($this->link, ['clicked_at' => now()->subDays(8)]);
    clickOn(Link::factory()->for($this->user)->create());

    $this->actingAs($this->user)->get(route('links.stats', [$this->link, 'days' => 7]))
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

    $this->actingAs($this->user)->get(route('links.stats', [$this->link, 'days' => 7]))
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

test('invalid days value fails validation', function () {
    $this->actingAs($this->user)->get(route('links.stats', [$this->link, 'days' => 5]))
        ->assertSessionHasErrors('days');
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
