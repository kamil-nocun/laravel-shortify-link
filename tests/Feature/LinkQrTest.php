<?php

use App\Enums\LinkEntry;
use App\Models\Domain;
use App\Models\Link;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->link = Link::factory()->for($this->user)->create(['alias' => 'moj-alias']);
});

test('qrUrl points to the code or alias address with the qr marker', function () {
    expect($this->link->qrUrl(LinkEntry::Code))->toBe($this->link->short_url.'?qr=1')
        ->and($this->link->qrUrl(LinkEntry::Alias))->toBe($this->link->alias_url.'?qr=1')
        ->and(Link::factory()->create()->qrUrl(LinkEntry::Alias))->toBeNull();
});

test('qrUrl uses the host of the custom domain', function () {
    $domain = Domain::factory()->create();
    $link = Link::factory()->create(['domain_id' => $domain->id, 'alias' => 'dom-alias']);

    expect($link->qrUrl(LinkEntry::Code))->toStartWith($domain->url().'/')
        ->and($link->qrUrl(LinkEntry::Alias))->toBe($domain->url().'/dom-alias?qr=1');
});

test('owner gets an svg for the code and the alias', function (string $type) {
    $this->actingAs($this->user)->get(route('links.qr', [$this->link, $type]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml')
        ->assertSee('<svg', false)
        ->assertHeaderMissing('Content-Disposition');
})->with(['code', 'alias']);

test('alias qr of a link without alias and unknown types return 404', function () {
    $plain = Link::factory()->for($this->user)->create();

    $this->actingAs($this->user)->get(route('links.qr', [$plain, 'alias']))->assertNotFound();
    $this->actingAs($this->user)->get(route('links.qr', [$this->link, 'other']))->assertNotFound();
});

test('download adds an attachment header named after the code or alias', function () {
    $this->actingAs($this->user)->get(route('links.qr', [$this->link, 'code', 'download' => 1]))
        ->assertHeader('Content-Disposition', 'attachment; filename="'.$this->link->code.'.svg"');
    $this->actingAs($this->user)->get(route('links.qr', [$this->link, 'alias', 'download' => 1]))
        ->assertHeader('Content-Disposition', 'attachment; filename="moj-alias.svg"');
});

test('other users cannot get the qr of a foreign link and guests are sent to login', function () {
    $this->actingAs(User::factory()->create())->get(route('links.qr', [$this->link, 'code']))->assertForbidden();
    auth()->logout();
    $this->get(route('links.qr', [$this->link, 'code']))->assertRedirect(route('login'));
});
