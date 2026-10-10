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

test('the foreign key and index carry the link_statistics prefix', function () {
    $indexes = collect(Schema::getIndexes('link_statistics'))->pluck('name');
    $foreignKeys = collect(Schema::getForeignKeys('link_statistics'))->pluck('name');

    expect($indexes)->toContain('link_statistics_link_id_clicked_at_index')
        ->not->toContain('link_clicks_link_id_clicked_at_index')
        ->and($foreignKeys->all())->toBe(['link_statistics_link_id_foreign']);
});

test('a new click counts as one and is not a summary', function () {
    $click = LinkClick::factory()->create()->fresh();

    expect($click->clicks)->toBe(1)->and($click->is_summary)->toBeFalse();
});

test('summary state has no city and a day start time', function () {
    $click = LinkClick::factory()->summary()->create()->fresh();

    expect($click->is_summary)->toBeTrue()
        ->and($click->clicks)->toBe(5)
        ->and($click->city)->toBeNull()
        ->and($click->clicked_at->equalTo($click->clicked_at->copy()->startOfDay()))->toBeTrue();
});

test('an index supports the daily aggregation range scans', function () {
    expect(collect(Schema::getIndexes('link_statistics'))->pluck('name'))->toContain('link_statistics_clicked_at_is_summary_index');
});
