<?php

use App\Models\Domain;

beforeEach(function () {
    config(['shortify.domain_check_secret' => 'proxy-secret']);
});

function checkDomain(string $host, ?string $secret = 'proxy-secret')
{
    return test()->withHeaders($secret === null ? [] : ['X-Domain-Check-Secret' => $secret])
        ->get(route('domains.check', ['domain' => $host]));
}

test('active domain returns 200', function () {
    Domain::factory()->verified()->create(['host' => 'links.example.com']);

    checkDomain('links.example.com')->assertOk()->assertHeaderMissing('Set-Cookie');
});

test('unknown, unverified and disabled domains return 404', function () {
    Domain::factory()->create(['host' => 'pending.example.com']);
    Domain::factory()->verified()->disabledByAdmin()->create(['host' => 'off.example.com']);

    checkDomain('unknown.example.com')->assertNotFound();
    checkDomain('pending.example.com')->assertNotFound();
    checkDomain('off.example.com')->assertNotFound();
    test()->withHeaders(['X-Domain-Check-Secret' => 'proxy-secret'])->get(route('domains.check'))->assertNotFound();
});

test('missing or wrong secret returns 403', function () {
    Domain::factory()->verified()->create(['host' => 'links.example.com']);

    checkDomain('links.example.com', null)->assertForbidden();
    checkDomain('links.example.com', 'wrong')->assertForbidden();
});

test('an unset secret rejects every request', function (mixed $configured) {
    config(['shortify.domain_check_secret' => $configured]);
    Domain::factory()->verified()->create(['host' => 'links.example.com']);

    checkDomain('links.example.com', '')->assertForbidden();
    checkDomain('links.example.com', null)->assertForbidden();
})->with([null, '']);

test('host is matched case-insensitively', function () {
    Domain::factory()->verified()->create(['host' => 'links.example.com']);

    checkDomain('  Links.EXAMPLE.com ')->assertOk();
});
