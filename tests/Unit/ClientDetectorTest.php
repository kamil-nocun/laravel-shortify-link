<?php

use App\Support\ClientDetector;

const CHROME_WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';
const IPHONE_SAFARI = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1';
const GOOGLEBOT = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

test('detects browser, os and device type of a desktop browser', function () {
    expect((new ClientDetector)->detect(CHROME_WINDOWS))->toBe([
        'browser' => 'Chrome',
        'os' => 'Windows',
        'device_type' => 'desktop',
    ]);
});

test('detects a smartphone', function () {
    $client = (new ClientDetector)->detect(IPHONE_SAFARI);

    expect($client['os'])->toBe('iOS')
        ->and($client['device_type'])->toBe('smartphone')
        ->and($client['browser'])->not->toBeEmpty();
});

test('marks bots as bot without browser and os', function () {
    expect((new ClientDetector)->detect(GOOGLEBOT))->toBe([
        'browser' => null,
        'os' => null,
        'device_type' => 'bot',
    ]);
});

test('returns nulls for a missing or empty user agent', function (?string $userAgent) {
    expect((new ClientDetector)->detect($userAgent))->toBe([
        'browser' => null,
        'os' => null,
        'device_type' => null,
    ]);
})->with([null, '', '   ']);
