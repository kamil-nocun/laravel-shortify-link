<?php

use App\Models\Link;
use App\Models\LinkClick;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;

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

test('browser, os and device_type may be null', function () {
    $click = LinkClick::factory()->create(['browser' => null, 'os' => null, 'device_type' => null])->fresh();

    expect($click->browser)->toBeNull()
        ->and($click->os)->toBeNull()
        ->and($click->device_type)->toBeNull();
});

test('clicks are stored in the link_statistics table', function () {
    $click = LinkClick::factory()->create();

    expect($click->getTable())->toBe('link_statistics')
        ->and(Schema::hasTable('link_statistics'))->toBeTrue()
        ->and(Schema::hasTable('link_clicks'))->toBeFalse();
});
