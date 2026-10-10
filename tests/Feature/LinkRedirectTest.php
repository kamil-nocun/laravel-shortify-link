<?php

use App\Enums\LinkEntry;
use App\Enums\LinkSource;
use App\Jobs\RecordLinkClick;
use App\Models\Link;
use App\Models\User;
use App\Support\ClientDetector;
use App\Support\GeoLocator;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

test('active link redirects with 302 to original_url', function () {
    $link = Link::factory()->create();

    $this->get(route('links.redirect', $link->code))
        ->assertStatus(302)
        ->assertRedirect($link->original_url);
});

test('link expiring in the future still redirects', function () {
    $link = Link::factory()->create(['expires_at' => now()->addDay()]);

    $this->get(route('links.redirect', $link->code))->assertRedirect($link->original_url);
});

test('inactive, expired and unknown codes return 404', function (?string $state) {
    $code = 'zzzzzz';

    if ($state !== null) {
        $code = Link::factory()->{$state}()->create()->code;
    }

    $this->get(route('links.redirect', $code))->assertNotFound();
})->with([
    'inactive' => 'inactive',
    'expired' => 'expired',
    'unknown' => null,
]);

test('code lookup is case sensitive', function () {
    Link::factory()->create(['code' => 'Abc123']);

    $this->get('/abc123')->assertNotFound();
});

test('code outside the alphabet returns 404', function () {
    $this->get('/ab-12')->assertNotFound();
});

test('catch-all route does not shadow existing pages', function () {
    $this->get('/login')->assertOk();
    $this->get('/dashboard')->assertRedirect(route('login'));
});

test('refused redirects render the NotFound page with 404', function () {
    $link = Link::factory()->inactive()->create();

    $this->get(route('links.redirect', $link->code))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('errors/NotFound'));
});

test('other 404s are not rendered as the NotFound page', function () {
    $this->get('/no/such/page')
        ->assertNotFound()
        ->assertDontSee('errors\/NotFound', false);
});

test('successful redirect dispatches RecordLinkClick', function () {
    Queue::fake();
    $link = Link::factory()->create();

    $this->get(route('links.redirect', $link->code))->assertRedirect($link->original_url);

    Queue::assertPushed(RecordLinkClick::class, fn (RecordLinkClick $job) => $job->linkId === $link->id);
});

test('refused redirects dispatch nothing', function (?string $state) {
    Queue::fake();
    $code = $state === null ? 'zzzzzz' : Link::factory()->{$state}()->create()->code;

    $this->get(route('links.redirect', $code))->assertNotFound();

    Queue::assertNothingPushed();
})->with([
    'inactive' => 'inactive',
    'expired' => 'expired',
    'unknown' => null,
]);

test('redirect passes the detected client to the job', function () {
    Queue::fake();
    $detector = Mockery::mock(ClientDetector::class);
    $detector->shouldReceive('detect')->with('TestAgent/1.0')->andReturn(['browser' => 'Firefox', 'os' => 'Linux', 'device_type' => 'desktop']);
    app()->instance(ClientDetector::class, $detector);
    $link = Link::factory()->create();

    $this->withHeader('User-Agent', 'TestAgent/1.0')->get(route('links.redirect', $link->code));

    Queue::assertPushed(RecordLinkClick::class, fn (RecordLinkClick $job) => $job->client === ['browser' => 'Firefox', 'os' => 'Linux', 'device_type' => 'desktop']);
});

test('forwarded ip is ignored unless proxies are trusted', function () {
    Queue::fake();
    config(['trustedproxy.proxies' => null]);
    $geoLocator = Mockery::mock(GeoLocator::class);
    $geoLocator->shouldReceive('locate')->with('127.0.0.1')->once()->andReturn(['country_code' => 'PL', 'country' => 'Poland', 'city' => null]);
    app()->instance(GeoLocator::class, $geoLocator);
    $link = Link::factory()->create();

    $this->withHeader('X-Forwarded-For', '89.64.0.1')->get(route('links.redirect', $link->code));

    Queue::assertPushed(RecordLinkClick::class, fn (RecordLinkClick $job) => $job->location['country_code'] === 'PL');
});

test('forwarded ip is used when proxies are trusted', function () {
    Queue::fake();
    config(['trustedproxy.proxies' => '*']);
    $geoLocator = Mockery::mock(GeoLocator::class);
    $geoLocator->shouldReceive('locate')->with('89.64.0.1')->once()->andReturn(['country_code' => 'DE', 'country' => 'Germany', 'city' => null]);
    app()->instance(GeoLocator::class, $geoLocator);
    $link = Link::factory()->create();

    $this->withHeader('X-Forwarded-For', '89.64.0.1')->get(route('links.redirect', $link->code));

    Queue::assertPushed(RecordLinkClick::class, fn (RecordLinkClick $job) => $job->location['country_code'] === 'DE');
});

test('links disabled by an admin or owned by a blocked user return 404 and record nothing', function (string $scenario) {
    Queue::fake();
    $link = $scenario === 'admin'
        ? Link::factory()->disabledByAdmin()->create()
        : Link::factory()->for(User::factory()->blocked())->create();

    $this->get(route('links.redirect', $link->code))->assertNotFound();
    Queue::assertNothingPushed();

    $scenario === 'admin'
        ? $link->forceFill(['disabled_by_admin_at' => null])->save()
        : $link->user->forceFill(['blocked_at' => null])->save();

    $this->get(route('links.redirect', $link->code))->assertRedirect($link->original_url);
})->with(['admin', 'blocked owner']);

test('redirect records whether the code or the alias was used', function () {
    Queue::fake();
    $link = Link::factory()->create(['alias' => 'my-alias']);

    $this->get('/'.$link->code);
    $this->get('/'.$link->alias);

    Queue::assertPushed(RecordLinkClick::class, fn (RecordLinkClick $job) => $job->entry === LinkEntry::Code);
    Queue::assertPushed(RecordLinkClick::class, fn (RecordLinkClick $job) => $job->entry === LinkEntry::Alias);
});

test('redirect marks qr scans on the code and on the alias', function () {
    Queue::fake();
    $link = Link::factory()->create(['alias' => 'my-alias']);

    $this->get('/'.$link->code.'?qr=1')->assertRedirect($link->original_url);
    $this->get('/'.$link->alias.'?qr=1')->assertRedirect($link->original_url);

    Queue::assertPushed(RecordLinkClick::class, fn (RecordLinkClick $job) => $job->entry === LinkEntry::Code && $job->source === LinkSource::Qr);
    Queue::assertPushed(RecordLinkClick::class, fn (RecordLinkClick $job) => $job->entry === LinkEntry::Alias && $job->source === LinkSource::Qr);
});

test('redirect without a qr marker records the Link source', function (string $query) {
    Queue::fake();
    $link = Link::factory()->create();

    $this->get('/'.$link->code.$query);

    Queue::assertPushed(RecordLinkClick::class, fn (RecordLinkClick $job) => $job->source === LinkSource::Link);
})->with(['no parameter' => '', 'qr=0' => '?qr=0', 'qr=abc' => '?qr=abc']);
