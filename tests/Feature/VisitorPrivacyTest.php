<?php

use App\Jobs\RecordLinkClick;
use App\Models\Link;
use App\Models\LinkClick;
use App\Support\GeoLocator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

const VISITOR_IP = '203.0.113.7';
const VISITOR_AGENT = 'SecretAgent/1.0';

function visitAs(Link $link)
{
    return test()->withServerVariables(['REMOTE_ADDR' => VISITOR_IP])->withHeader('User-Agent', VISITOR_AGENT)->get(route('links.redirect', $link->code));
}

beforeEach(function () {
    $geoLocator = Mockery::mock(GeoLocator::class);
    $geoLocator->shouldReceive('locate')->with(VISITOR_IP)->andReturn(['country_code' => 'PL', 'country' => 'Poland', 'city' => 'Warsaw']);
    app()->instance(GeoLocator::class, $geoLocator);
});

test('a redirect leaves no ip or user agent in any table', function () {
    $link = Link::factory()->create();

    visitAs($link)->assertRedirect($link->original_url);

    expect(LinkClick::sole()->country_code)->toBe('PL');

    foreach (Schema::getTables() as $table) {
        $rows = json_encode(DB::table($table['name'])->get());

        expect($rows)->not->toContain(VISITOR_IP)->not->toContain('SecretAgent');
    }
});

test('the dispatched job carries neither ip nor user agent', function () {
    Queue::fake();
    $link = Link::factory()->create();

    visitAs($link);

    Queue::assertPushed(RecordLinkClick::class, fn (RecordLinkClick $job) => ! str_contains(serialize($job), VISITOR_IP) && ! str_contains(serialize($job), 'SecretAgent'));
});

test('the redirect sets no cookies', function () {
    visitAs(Link::factory()->create())->assertHeaderMissing('Set-Cookie');
});

test('unknown codes still render the NotFound page without cookies', function () {
    $this->get('/zzzzzz')->assertNotFound()->assertHeaderMissing('Set-Cookie');
    $this->get('/zzzzzz')->assertNotFound()->assertSee('errors\/NotFound', false);
});
