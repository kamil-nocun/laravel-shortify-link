<?php

use App\Models\Domain;
use App\Models\Link;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function listedIds(array $query = []): array
{
    return collect(test()->get(route('links.index', $query))->viewData('page')['props']['links']['data'])->pluck('id')->all();
}

function makeLink(array $attributes = []): Link
{
    return Link::factory()->for(test()->user)->create($attributes);
}

test('search matches title, code and part of the url', function () {
    $byTitle = makeLink(['title' => 'Summer sale']);
    $byCode = makeLink(['code' => 'zebra1']);
    $byUrl = makeLink(['original_url' => 'https://example.org/needle-path']);
    makeLink(['title' => 'Other', 'code' => 'qqqqqq', 'original_url' => 'https://other.test/x']);

    expect(listedIds(['search' => 'Summer']))->toBe([$byTitle->id])
        ->and(listedIds(['search' => 'zebra']))->toBe([$byCode->id])
        ->and(listedIds(['search' => 'needle']))->toBe([$byUrl->id]);
});

test('search escapes LIKE wildcards', function () {
    $literal = makeLink(['title' => '100%']);
    makeLink(['title' => '100x']);
    $underscore = makeLink(['title' => 'a_c']);
    makeLink(['title' => 'abc']);

    expect(listedIds(['search' => '100%']))->toBe([$literal->id])
        ->and(listedIds(['search' => 'a_c']))->toBe([$underscore->id]);
});

test('status filter returns only matching links', function () {
    $active = makeLink();
    $inactive = makeLink(['is_active' => false]);
    $expired = makeLink(['expires_at' => now()->subDay()]);
    $disabled = makeLink(['disabled_by_admin_at' => now()]);
    $disabledAndExpired = makeLink(['disabled_by_admin_at' => now(), 'expires_at' => now()->subDay()]);

    expect(listedIds(['status' => 'active']))->toBe([$active->id])
        ->and(listedIds(['status' => 'inactive']))->toBe([$inactive->id])
        ->and(listedIds(['status' => 'expired']))->toBe([$expired->id])
        ->and(listedIds(['status' => 'disabled']))->toEqualCanonicalizing([$disabled->id, $disabledAndExpired->id]);
});

test('link on an admin-disabled domain is listed but not active', function () {
    $domain = Domain::factory()->for($this->user)->create(['disabled_by_admin_at' => now()]);
    $link = makeLink(['domain_id' => $domain->id]);

    expect(listedIds())->toBe([$link->id])
        ->and(listedIds(['status' => 'active']))->toBe([]);
});

test('domain filter handles main, own domain and foreign domain', function () {
    $domain = Domain::factory()->for($this->user)->create();
    $main = makeLink();
    $onDomain = makeLink(['domain_id' => $domain->id]);

    expect(listedIds(['domain' => 'main']))->toBe([$main->id])
        ->and(listedIds(['domain' => $domain->id]))->toBe([$onDomain->id]);

    $this->get(route('links.index', ['domain' => Domain::factory()->create()->id]))
        ->assertSessionHasErrors('domain');
});

test('invalid status is rejected', function () {
    $this->get(route('links.index', ['status' => 'bogus']))->assertSessionHasErrors('status');
});

test('filters combine', function () {
    $domain = Domain::factory()->for($this->user)->create();
    $hit = makeLink(['title' => 'promo', 'domain_id' => $domain->id, 'is_active' => false]);
    makeLink(['title' => 'promo', 'domain_id' => $domain->id]);
    makeLink(['title' => 'promo', 'is_active' => false]);
    makeLink(['title' => 'nope', 'domain_id' => $domain->id, 'is_active' => false]);

    expect(listedIds(['search' => 'promo', 'status' => 'inactive', 'domain' => $domain->id]))->toBe([$hit->id]);
});

test('filters survive pagination and are echoed back', function () {
    Link::factory()->for($this->user)->count(16)->create(['title' => 'bulk']);

    $this->get(route('links.index', ['search' => 'bulk', 'status' => 'active']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters', ['search' => 'bulk', 'status' => 'active', 'domain' => null])
            ->where('links.next_page_url', fn ($url) => str_contains($url, 'search=bulk') && str_contains($url, 'status=active')));
});

test('other users links never show', function () {
    Link::factory()->create(['title' => 'secret']);

    expect(listedIds(['search' => 'secret']))->toBe([]);
});
