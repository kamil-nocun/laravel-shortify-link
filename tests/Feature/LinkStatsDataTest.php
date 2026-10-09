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
    LinkClick::factory()->for($this->link)->count(2)->create();
    LinkClick::factory()->for($this->link)->create(['clicked_at' => now()->subDays(2), 'country' => 'Germany', 'city' => 'Berlin']);
});

test('data returns the same payload as the page props', function () {
    $data = $this->actingAs($this->user)->getJson(route('links.stats.data', $this->link))
        ->assertOk()
        ->assertJsonStructure(['period', 'granularity', 'series', 'total', 'filters', 'options', 'sort', 'countries', 'cities', 'browsers', 'systems', 'devices'])
        ->json();

    $this->actingAs($this->user)->get(route('links.stats', $this->link))
        ->assertInertia(fn (Assert $page) => collect($data)->each(fn ($value, $key) => $page->where($key, $value)));
});

test('data respects period, filters, sort and pagination', function () {
    $this->actingAs($this->user)->getJson(route('links.stats.data', [
        $this->link,
        'period' => '7d',
        'country' => 'Poland',
        'sort' => ['countries' => 'name_asc'],
        'countries_per_page' => 10,
        'countries_page' => 1,
    ]))
        ->assertOk()
        ->assertJsonPath('total', 2)
        ->assertJsonPath('filters.country', 'Poland')
        ->assertJsonPath('sort.countries', 'name_asc')
        ->assertJsonPath('countries.rows', [['name' => 'Poland', 'count' => 2]]);
});

test('sections limits the keys', function (array $sections, array $keys) {
    $json = $this->actingAs($this->user)->getJson(route('links.stats.data', [$this->link, 'sections' => $sections]))
        ->assertOk()->json();

    expect(array_keys($json))->toEqualCanonicalizing($keys);
})->with([
    'one table' => [['countries'], ['countries']],
    'summary' => [['summary'], ['period', 'granularity', 'series', 'total', 'filters', 'sort']],
    'options' => [['options'], ['options']],
    'two sections' => [['options', 'devices'], ['options', 'devices']],
]);

test('a single table section runs only its queries', function () {
    DB::enableQueryLog();

    $this->actingAs($this->user)->getJson(route('links.stats.data', [$this->link, 'sections' => ['countries']]))->assertOk();

    $clickQueries = collect(DB::getQueryLog())->filter(fn (array $query) => str_contains($query['query'], 'link_statistics'));
    expect($clickQueries)->toHaveCount(2);
});

test('an unknown section fails validation', function () {
    $this->actingAs($this->user)->getJson(route('links.stats.data', [$this->link, 'sections' => ['x']]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sections.0');
});

test('invalid parameters return 422 json', function () {
    $this->actingAs($this->user)->getJson(route('links.stats.data', [$this->link, 'period' => 'week']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('period');
});

test('other users get 403 json and no redirect', function () {
    $this->actingAs(User::factory()->create())->getJson(route('links.stats.data', $this->link))
        ->assertForbidden();
});

test('guests get 401 json', function () {
    $this->getJson(route('links.stats.data', $this->link))->assertUnauthorized();
});
