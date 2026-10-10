<?php

use App\Models\Domain;
use App\Models\Link;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['app.url' => 'http://shortify.test']);
    $this->mainHost = 'shortify.test';
});

test('main host serves only links without a domain', function () {
    $domain = Domain::factory()->verified()->create();
    Link::factory()->for($domain->user)->create(['domain_id' => $domain->id, 'code' => 'onlydm', 'original_url' => 'https://a.test']);

    $this->get('http://shortify.test/onlydm')->assertNotFound();
});

test('a verified domain serves its own links', function () {
    $domain = Domain::factory()->verified()->create();
    Link::factory()->for($domain->user)->create(['domain_id' => $domain->id, 'code' => 'abc123', 'original_url' => 'https://a.test']);

    $this->get('http://'.$domain->host.'/abc123')->assertRedirect('https://a.test');
});

test('the same code works independently on the main host and on a user domain', function () {
    $domain = Domain::factory()->verified()->create();
    Link::factory()->create(['code' => 'same12', 'original_url' => 'https://main.test']);
    Link::factory()->for($domain->user)->create(['domain_id' => $domain->id, 'code' => 'same12', 'original_url' => 'https://dom.test']);

    $this->get('http://shortify.test/same12')->assertRedirect('https://main.test');
    $this->get('http://'.$domain->host.'/same12')->assertRedirect('https://dom.test');
});

test('unverified, admin-disabled and blocked-owner domains return 404', function (callable $make) {
    $domain = $make();
    Link::factory()->for($domain->user)->create(['domain_id' => $domain->id, 'code' => 'abc123']);

    $this->get('http://'.$domain->host.'/abc123')->assertNotFound();
})->with([
    'unverified' => fn () => Domain::factory()->create(),
    'disabled' => fn () => Domain::factory()->verified()->disabledByAdmin()->create(),
    'blocked owner' => fn () => Domain::factory()->verified()->for(User::factory()->state(['blocked_at' => now()]))->create(),
]);

test('unknown host returns 404', function () {
    $this->get('http://nope.example/abc123')->assertNotFound();
});

test('a link on a domain stops redirecting when its domain is disabled', function () {
    $domain = Domain::factory()->verified()->create();
    Link::factory()->for($domain->user)->create(['domain_id' => $domain->id, 'code' => 'abc123']);

    $this->get('http://'.$domain->host.'/abc123')->assertRedirect();

    $domain->forceFill(['disabled_by_admin_at' => now()])->save();

    $this->get('http://'.$domain->host.'/abc123')->assertNotFound();
});

test('short_url uses the domain host', function () {
    $domain = Domain::factory()->verified()->create(['host' => 'go.example.com']);
    $link = Link::factory()->for($domain->user)->create(['domain_id' => $domain->id, 'code' => 'abc123']);

    expect($link->short_url)->toBe('http://go.example.com/abc123')
        ->and(Link::factory()->create(['code' => 'main12'])->short_url)->toBe('http://shortify.test/main12');
});

test('host matching ignores case', function () {
    $domain = Domain::factory()->verified()->create(['host' => 'example.com']);
    Link::factory()->for($domain->user)->create(['domain_id' => $domain->id, 'code' => 'abc123']);

    $this->get('http://Example.COM/abc123')->assertRedirect();
});

test('admin user page lists domain short urls without a query per link', function () {
    $admin = User::factory()->superAdmin()->create();
    $domain = Domain::factory()->verified()->create(['host' => 'go.example.com']);
    $count = function () use ($admin, $domain): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin)->get(route('admin.users.show', $domain->user))->assertOk();

        return count(DB::getQueryLog());
    };

    Link::factory()->for($domain->user)->create(['domain_id' => $domain->id]);
    $before = $count();
    Link::factory()->count(5)->for($domain->user)->create(['domain_id' => $domain->id]);

    expect($count())->toBe($before)
        ->and($domain->user->links()->with('domain')->first()->short_url)->toStartWith('http://go.example.com/');
});
