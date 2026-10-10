<?php

use App\Models\Link;
use App\Models\LinkClick;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->freezeTime();
    $this->user = User::factory()->create();
    $this->link = Link::factory()->for($this->user)->create();
});

function entryClicks(Link $link, array $counts, array $attributes = []): void
{
    foreach ($counts as $entry => $count) {
        LinkClick::factory()->for($link)->count($count)->create($attributes + ['entry' => $entry === 'Unknown' ? null : $entry]);
    }
}

function entryData(User $user, Link $link, array $query = []): TestResponse
{
    return test()->actingAs($user)->getJson(route('links.stats.data', [$link, ...$query]));
}

test('entries section counts by entry sorted by count desc', function () {
    entryClicks($this->link, ['Code' => 3, 'Alias' => 2, 'Unknown' => 1]);

    $this->actingAs($this->user)->get(route('links.stats', $this->link))
        ->assertInertia(fn (Assert $page) => $page
            ->where('entries.rows', [['name' => 'Code', 'count' => 3], ['name' => 'Alias', 'count' => 2], ['name' => 'Unknown', 'count' => 1]])
            ->where('entries.total', 3)
            ->where('sort.entries', 'count_desc')
            ->where('filters.entry', null));
});

test('entries sort by name and paginate', function () {
    entryClicks($this->link, ['Code' => 3, 'Alias' => 2, 'Unknown' => 1]);

    expect(entryData($this->user, $this->link, ['sort' => ['entries' => 'name_asc']])->json('entries.rows.*.name'))->toBe(['Alias', 'Code', 'Unknown'])
        ->and(entryData($this->user, $this->link, ['sort' => ['entries' => 'name_desc']])->json('entries.rows.*.name'))->toBe(['Unknown', 'Code', 'Alias']);

    $page = entryData($this->user, $this->link, ['entries_per_page' => 10, 'entries_page' => 9])->json('entries');
    expect($page['page'])->toBe(1)->and($page['lastPage'])->toBe(1);
});

test('entry filter narrows other tables and the series', function () {
    entryClicks($this->link, ['Code' => 3, 'Alias' => 2], ['browser' => 'Chrome']);
    entryClicks($this->link, ['Alias' => 1], ['browser' => 'Firefox']);

    $json = entryData($this->user, $this->link, ['entry' => 'Alias'])->assertOk()->json();

    expect($json['total'])->toBe(3)
        ->and($json['browsers']['rows'])->toBe([['name' => 'Chrome', 'count' => 2], ['name' => 'Firefox', 'count' => 1]])
        ->and($json['entries']['rows'])->toBe([['name' => 'Alias', 'count' => 3]]);
});

test('entry Unknown matches only clicks without entry and Bogus matches nothing', function () {
    entryClicks($this->link, ['Code' => 2, 'Unknown' => 1]);

    expect(entryData($this->user, $this->link, ['entry' => 'Unknown'])->json('total'))->toBe(1);

    $bogus = entryData($this->user, $this->link, ['entry' => 'Bogus'])->assertOk()->json();
    expect($bogus['total'])->toBe(0)->and($bogus['entries']['rows'])->toBe([]);
});

test('entry combines with browser and period', function () {
    entryClicks($this->link, ['Code' => 2], ['browser' => 'Chrome']);
    entryClicks($this->link, ['Code' => 1, 'Alias' => 1], ['browser' => 'Firefox']);
    entryClicks($this->link, ['Code' => 4], ['browser' => 'Chrome', 'clicked_at' => now()->subDays(20)]);

    $json = entryData($this->user, $this->link, ['entry' => 'Code', 'browser' => 'Chrome', 'period' => '7d'])->json();

    expect($json['total'])->toBe(2)
        ->and($json['entries']['rows'])->toBe([['name' => 'Code', 'count' => 2]])
        ->and($json['browsers']['rows'])->toBe([['name' => 'Chrome', 'count' => 2]]);
});

test('options list entries alphabetically with Unknown last', function () {
    entryClicks($this->link, ['Code' => 1, 'Alias' => 1, 'Unknown' => 1]);

    expect(entryData($this->user, $this->link)->json('options.entries'))->toBe(['Alias', 'Code', 'Unknown']);
});

test('sections can request only entries and invalid input is rejected', function () {
    entryClicks($this->link, ['Code' => 1]);

    expect(array_keys(entryData($this->user, $this->link, ['sections' => ['entries']])->assertOk()->json()))->toBe(['entries']);

    entryData($this->user, $this->link, ['sort' => ['entries' => 'bogus']])->assertJsonValidationErrors('sort.entries');
    entryData($this->user, $this->link, ['entries_per_page' => 7])->assertJsonValidationErrors('entries_per_page');
});

test('admin stats of another user link include entries', function () {
    entryClicks($this->link, ['Alias' => 2]);

    $this->actingAs(User::factory()->superAdmin()->create())->get(route('admin.links.stats', $this->link))
        ->assertInertia(fn (Assert $page) => $page->where('entries.rows', [['name' => 'Alias', 'count' => 2]]));
});
