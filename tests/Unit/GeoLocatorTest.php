<?php

use App\Support\GeoLocator;
use Tests\TestCase;

uses(TestCase::class);

test('returns nulls when the database file is missing', function () {
    config(['services.geoip.database' => '/nonexistent/GeoLite2-City.mmdb']);

    expect(app(GeoLocator::class)->locate('8.8.8.8'))->toBe([
        'country_code' => null,
        'country' => null,
        'city' => null,
    ]);
});

test('returns nulls for an invalid ip', function () {
    expect(app(GeoLocator::class)->locate('not-an-ip'))->toBe([
        'country_code' => null,
        'country' => null,
        'city' => null,
    ]);
});

test('returns nulls when the database is of an corrupt', function () {
    $path = tempnam(sys_get_temp_dir(), 'mmdb');
    file_put_contents($path, 'not a maxmind database');
    config(['services.geoip.database' => $path]);

    expect(app(GeoLocator::class)->locate('8.8.8.8'))->toBe([
        'country_code' => null,
        'country' => null,
        'city' => null,
    ]);

    unlink($path);
});
