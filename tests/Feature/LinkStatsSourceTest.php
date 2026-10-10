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

function sourceClicks(Link $link, array $counts, array $attributes = []): void
{
    foreach ($counts as $source => $count) {
        LinkClick::factory()->for($link)->count($count)->create($attributes + ['source' => $source === 'Unknown' ? null : $source]);
    }
}

function sourceData(User $user, Link $link, array $query = []): TestResponse
{
    return test()->actingAs($user)->getJson(route('links.stats.data', [$link, ...$query]));
}

test('sources section counts by source sorted by count desc', function () {
    sourceClicks($this->link, ['Link' => 3, 'QR' => 2, 'Unknown' => 1]);

    $this->actingAs($this->user)->get(route('links.stats', $this->link))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sources.rows', [['name' => 'Link', 'count' => 3], ['name' => 'QR', 'count' => 2], ['name' => 'Unknown', 'count' => 1]])
            ->where('sources.total', 3)
            ->where('sort.sources', 'count_desc')
            ->where('filters.source', null));
});

test('sources sort by name and paginate', function () {
    sourceClicks($this->link, ['Link' => 3, 'QR' => 2, 'Unknown' => 1]);

    expect(sourceData($this->user, $this->link, ['sort' => ['sources' => 'name_asc']])->json('sources.rows.*.name'))->toBe(['Link', 'QR', 'Unknown'])
        ->and(sourceData($this->user, $this->link, ['sort' => ['sources' => 'name_desc']])->json('sources.rows.*.name'))->toBe(['Unknown', 'QR', 'Link']);

    $page = sourceData($this->user, $this->link, ['sources_per_page' => 10, 'sources_page' => 9])->json('sources');
    expect($page['page'])->toBe(1)->and($page['lastPage'])->toBe(1);
});

test('source filter narrows other tables and the series', function () {
    sourceClicks($this->link, ['QR' => 3, 'Link' => 2], ['browser' => 'Chrome']);
    sourceClicks($this->link, ['QR' => 1], ['browser' => 'Firefox']);

    $json = sourceData($this->user, $this->link, ['source' => 'QR'])->assertOk()->json();

    expect($json['total'])->toBe(4)
        ->and($json['browsers']['rows'])->toBe([['name' => 'Chrome', 'count' => 3], ['name' => 'Firefox', 'count' => 1]])
        ->and($json['sources']['rows'])->toBe([['name' => 'QR', 'count' => 4]]);
});

test('source Unknown matches only clicks without source and Bogus matches nothing', function () {
    sourceClicks($this->link, ['Link' => 2, 'Unknown' => 1]);

    expect(sourceData($this->user, $this->link, ['source' => 'Unknown'])->json('total'))->toBe(1);

    $bogus = sourceData($this->user, $this->link, ['source' => 'Bogus'])->assertOk()->json();
    expect($bogus['total'])->toBe(0)->and($bogus['sources']['rows'])->toBe([]);
});

test('source combines with entry and period', function () {
    sourceClicks($this->link, ['QR' => 2], ['entry' => 'Alias']);
    sourceClicks($this->link, ['QR' => 1], ['entry' => 'Code']);
    sourceClicks($this->link, ['QR' => 4], ['entry' => 'Alias', 'clicked_at' => now()->subDays(20)]);

    $json = sourceData($this->user, $this->link, ['source' => 'QR', 'entry' => 'Alias', 'period' => '7d'])->json();

    expect($json['total'])->toBe(2)
        ->and($json['sources']['rows'])->toBe([['name' => 'QR', 'count' => 2]])
        ->and($json['entries']['rows'])->toBe([['name' => 'Alias', 'count' => 2]]);
});

test('options list sources alphabetically with Unknown last', function () {
    sourceClicks($this->link, ['QR' => 1, 'Link' => 1, 'Unknown' => 1]);

    expect(sourceData($this->user, $this->link)->json('options.sources'))->toBe(['Link', 'QR', 'Unknown']);
});

test('sections can request only sources and invalid input is rejected', function () {
    sourceClicks($this->link, ['Link' => 1]);

    expect(array_keys(sourceData($this->user, $this->link, ['sections' => ['sources']])->assertOk()->json()))->toBe(['sources']);

    sourceData($this->user, $this->link, ['sort' => ['sources' => 'bogus']])->assertJsonValidationErrors('sort.sources');
    sourceData($this->user, $this->link, ['sources_per_page' => 7])->assertJsonValidationErrors('sources_per_page');
    sourceData($this->user, $this->link, ['sources_page' => 0])->assertJsonValidationErrors('sources_page');
});

test('admin stats of another user link include sources', function () {
    sourceClicks($this->link, ['QR' => 2]);

    $this->actingAs(User::factory()->superAdmin()->create())->get(route('admin.links.stats', $this->link))
        ->assertInertia(fn (Assert $page) => $page->where('sources.rows', [['name' => 'QR', 'count' => 2]]));
});
