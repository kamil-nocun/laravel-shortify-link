<?php

use App\Jobs\RecordLinkClick;
use App\Models\Link;
use App\Models\LinkClick;
use App\Support\ClientDetector;
use App\Support\GeoLocator;
use Illuminate\Support\Str;

function fakeGeoLocator(array $result): void
{
    $geoLocator = Mockery::mock(GeoLocator::class);
    $geoLocator->shouldReceive('locate')->with('203.0.113.7')->andReturn($result);
    app()->instance(GeoLocator::class, $geoLocator);
}

test('records a click with country and city', function () {
    fakeGeoLocator(['country_code' => 'PL', 'country' => 'Poland', 'city' => 'Warsaw']);
    $link = Link::factory()->create();
    $clickedAt = now()->subMinutes(10)->startOfSecond();

    RecordLinkClick::dispatchSync($link->id, '203.0.113.7', $clickedAt);

    $click = LinkClick::sole();
    expect($click->link_id)->toBe($link->id)
        ->and($click->country_code)->toBe('PL')
        ->and($click->country)->toBe('Poland')
        ->and($click->city)->toBe('Warsaw')
        ->and($click->clicked_at->equalTo($clickedAt))->toBeTrue();
});

test('records a click with null geo when lookup finds nothing', function () {
    fakeGeoLocator(['country_code' => null, 'country' => null, 'city' => null]);
    $link = Link::factory()->create();

    RecordLinkClick::dispatchSync($link->id, '203.0.113.7', now());

    $click = LinkClick::sole();
    expect($click->country_code)->toBeNull()
        ->and($click->country)->toBeNull()
        ->and($click->city)->toBeNull();
});

test('does nothing when the link no longer exists', function () {
    fakeGeoLocator(['country_code' => 'PL', 'country' => 'Poland', 'city' => 'Warsaw']);

    RecordLinkClick::dispatchSync((string) Str::uuid(), '203.0.113.7', now());

    expect(LinkClick::count())->toBe(0);
});

test('does not persist the ip', function () {
    fakeGeoLocator(['country_code' => 'PL', 'country' => 'Poland', 'city' => 'Warsaw']);
    $link = Link::factory()->create();

    RecordLinkClick::dispatchSync($link->id, '203.0.113.7', now());

    expect(json_encode(LinkClick::sole()->getAttributes()))->not->toContain('203.0.113.7');
});

test('records browser, os and device type of the client', function () {
    fakeGeoLocator(['country_code' => null, 'country' => null, 'city' => null]);
    $detector = Mockery::mock(ClientDetector::class);
    $detector->shouldReceive('detect')->with('UA-STRING')->andReturn(['browser' => 'Firefox', 'os' => 'Linux', 'device_type' => 'desktop']);
    app()->instance(ClientDetector::class, $detector);
    $link = Link::factory()->create();

    RecordLinkClick::dispatchSync($link->id, '203.0.113.7', now(), 'UA-STRING');

    $click = LinkClick::sole();
    expect($click->browser)->toBe('Firefox')
        ->and($click->os)->toBe('Linux')
        ->and($click->device_type)->toBe('desktop');
});

test('records null client fields when there is no user agent', function () {
    fakeGeoLocator(['country_code' => null, 'country' => null, 'city' => null]);
    $link = Link::factory()->create();

    RecordLinkClick::dispatchSync($link->id, '203.0.113.7', now());

    $click = LinkClick::sole();
    expect($click->browser)->toBeNull()
        ->and($click->os)->toBeNull()
        ->and($click->device_type)->toBeNull();
});

test('does not persist the user agent', function () {
    fakeGeoLocator(['country_code' => null, 'country' => null, 'city' => null]);
    $link = Link::factory()->create();

    RecordLinkClick::dispatchSync($link->id, '203.0.113.7', now(), 'SecretAgent/1.0');

    expect(json_encode(LinkClick::sole()->getAttributes()))->not->toContain('SecretAgent');
});
