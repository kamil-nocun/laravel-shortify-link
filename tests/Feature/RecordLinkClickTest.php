<?php

use App\Enums\LinkEntry;
use App\Enums\LinkSource;
use App\Jobs\RecordLinkClick;
use App\Models\Link;
use App\Models\LinkClick;
use Illuminate\Support\Str;

const NO_LOCATION = ['country_code' => null, 'country' => null, 'city' => null];
const NO_CLIENT = ['browser' => null, 'os' => null, 'device_type' => null];

test('records a click with country and city', function () {
    $link = Link::factory()->create();
    $clickedAt = now()->subMinutes(10)->startOfSecond();

    RecordLinkClick::dispatchSync($link->id, $clickedAt, ['country_code' => 'PL', 'country' => 'Poland', 'city' => 'Warsaw'], NO_CLIENT);

    $click = LinkClick::sole();
    expect($click->link_id)->toBe($link->id)
        ->and($click->country_code)->toBe('PL')
        ->and($click->country)->toBe('Poland')
        ->and($click->city)->toBe('Warsaw')
        ->and($click->clicked_at->equalTo($clickedAt))->toBeTrue()
        ->and($click->clicks)->toBe(1)
        ->and($click->is_summary)->toBeFalse();
});

test('records a click with null geo when the lookup found nothing', function () {
    $link = Link::factory()->create();

    RecordLinkClick::dispatchSync($link->id, now(), NO_LOCATION, NO_CLIENT);

    $click = LinkClick::sole();
    expect($click->country_code)->toBeNull()
        ->and($click->country)->toBeNull()
        ->and($click->city)->toBeNull();
});

test('does nothing when the link no longer exists', function () {
    RecordLinkClick::dispatchSync((string) Str::uuid(), now(), NO_LOCATION, NO_CLIENT);

    expect(LinkClick::count())->toBe(0);
});

test('records browser, os and device type of the client', function () {
    $link = Link::factory()->create();

    RecordLinkClick::dispatchSync($link->id, now(), NO_LOCATION, ['browser' => 'Firefox', 'os' => 'Linux', 'device_type' => 'desktop']);

    $click = LinkClick::sole();
    expect($click->browser)->toBe('Firefox')
        ->and($click->os)->toBe('Linux')
        ->and($click->device_type)->toBe('desktop');
});

test('records which entry the click came through', function () {
    $link = Link::factory()->create();

    RecordLinkClick::dispatchSync($link->id, now(), NO_LOCATION, NO_CLIENT, LinkEntry::Alias);

    expect(LinkClick::sole()->entry)->toBe('Alias');
});

test('records the Code entry when none is given', function () {
    $link = Link::factory()->create();

    RecordLinkClick::dispatchSync($link->id, now(), NO_LOCATION, NO_CLIENT);

    expect(LinkClick::sole()->entry)->toBe('Code');
});

test('records the Link source by default and keeps a given QR source', function () {
    $link = Link::factory()->create();

    RecordLinkClick::dispatchSync($link->id, now(), NO_LOCATION, NO_CLIENT);
    RecordLinkClick::dispatchSync($link->id, now(), NO_LOCATION, NO_CLIENT, LinkEntry::Code, LinkSource::Qr);

    expect(LinkClick::orderBy('source')->pluck('source')->all())->toBe(['Link', 'QR']);
});
