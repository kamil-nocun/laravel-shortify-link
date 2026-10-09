<?php

use App\Models\Link;
use App\Models\LinkClick;
use Carbon\CarbonInterface;

test('a link has many clicks', function () {
    $link = Link::factory()->create();
    $click = LinkClick::factory()->for($link)->create();

    expect($link->clicks->pluck('id')->all())->toBe([$click->id])
        ->and($click->link->is($link))->toBeTrue();
});

test('clicks are deleted with their link', function () {
    $link = Link::factory()->create();
    LinkClick::factory()->for($link)->create();

    $link->delete();

    expect(LinkClick::count())->toBe(0);
});

test('clicked_at is cast to datetime', function () {
    $click = LinkClick::factory()->create()->fresh();

    expect($click->clicked_at)->toBeInstanceOf(CarbonInterface::class);
});

test('country_code, country and city may be null', function () {
    $click = LinkClick::factory()->create(['country_code' => null, 'country' => null, 'city' => null])->fresh();

    expect($click->country_code)->toBeNull()
        ->and($click->country)->toBeNull()
        ->and($click->city)->toBeNull();
});
