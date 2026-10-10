<?php

use App\Models\Link;
use App\Models\LinkClick;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->freezeTime();
    $this->user = User::factory()->create();
    $this->link = Link::factory()->for($this->user)->create();
    LinkClick::factory()->for($this->link)->count(2)->create(['country' => 'Poland']);
    LinkClick::factory()->for($this->link)->summary(7)->create(['clicked_at' => now()->subDay()->startOfDay(), 'country' => 'Germany']);
});

test('link stats totals and daily series count summary clicks', function () {
    $json = $this->actingAs($this->user)->getJson(route('links.stats.data', [$this->link, 'period' => '7d']))->assertOk()->json();

    expect($json['total'])->toBe(9)
        ->and(collect($json['series'])->sum('count'))->toBe(9)
        ->and(collect($json['series'])->firstWhere('date', now()->subDay()->toDateString())['count'])->toBe(7);
});

test('link stats breakdown counts summary clicks', function () {
    $this->actingAs($this->user)->getJson(route('links.stats.data', [$this->link, 'period' => '7d', 'sections' => ['countries']]))
        ->assertOk()
        ->assertJsonPath('countries.rows', [['name' => 'Germany', 'count' => 7], ['name' => 'Poland', 'count' => 2]]);
});

test('admin report totals, top links and top users count summary clicks', function () {
    $this->actingAs(User::factory()->superAdmin()->create())->get(route('admin.dashboard', ['period' => '7d']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('counters.clicks', 9)
            ->where('topLinks.0.clicks', 9)
            ->where('topUsers.0.clicks', 9));
});

test('link lists expose summed clicks_count including zero', function () {
    $empty = Link::factory()->for($this->user)->create();

    $this->actingAs($this->user)->get(route('links.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('links.data', fn ($links) => collect($links)->firstWhere('id', $this->link->id)['clicks_count'] === 9
                && collect($links)->firstWhere('id', $empty->id)['clicks_count'] === 0));

    $this->actingAs(User::factory()->superAdmin()->create())->get(route('admin.users.show', $this->user))
        ->assertInertia(fn (Assert $page) => $page
            ->where('links.data', fn ($links) => collect($links)->firstWhere('id', $this->link->id)['clicks_count'] === 9
                && collect($links)->firstWhere('id', $empty->id)['clicks_count'] === 0));
});
