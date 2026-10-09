<?php

use App\Models\Link;
use App\Models\LinkClick;
use App\Models\User;
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
