<?php

use App\Jobs\VerifyDomain;
use App\Models\Domain;
use App\Models\Link;
use App\Models\Package;
use App\Models\PackageAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

function userWithDomainSlots(int $limit): User
{
    $user = User::factory()->create();
    PackageAssignment::factory()->for($user)->for(Package::factory()->create(['domain_limit' => $limit]))->create();

    return $user;
}

test('user adds a domain within the limit and the verification job is queued', function () {
    Queue::fake();
    $user = userWithDomainSlots(1);

    $this->actingAs($user)->post(route('domains.store'), ['host' => 'links.example.com'])
        ->assertRedirect(route('domains.index'));

    $domain = $user->domains()->first();
    expect($domain->host)->toBe('links.example.com')
        ->and($domain->verification_token)->toHaveLength(40)
        ->and($domain->verified_at)->toBeNull();
    Queue::assertPushed(VerifyDomain::class, fn (VerifyDomain $job) => $job->domainId === $domain->id);
});

test('host is lower-cased and a trailing dot is removed', function () {
    Queue::fake();
    $user = userWithDomainSlots(1);

    $this->actingAs($user)->post(route('domains.store'), ['host' => 'Example.COM.']);

    expect($user->domains()->value('host'))->toBe('example.com');
});

test('invalid hosts are rejected', function (string $host) {
    $user = userWithDomainSlots(1);

    $this->actingAs($user)->post(route('domains.store'), ['host' => $host])->assertSessionHasErrors('host');

    expect(Domain::count())->toBe(0);
})->with(['example.com:8080', 'https://example.com', 'example.com/path', 'exa mple.com', 'localhost', '']);

test('the application host cannot be added', function () {
    config(['app.url' => 'https://Shortify.test']);

    $this->actingAs(userWithDomainSlots(1))->post(route('domains.store'), ['host' => 'shortify.test'])
        ->assertSessionHasErrors('host');
});

test('duplicate host is rejected, even for another user', function () {
    Domain::factory()->create(['host' => 'taken.example.com']);

    $this->actingAs(userWithDomainSlots(1))->post(route('domains.store'), ['host' => 'Taken.example.com'])
        ->assertSessionHasErrors('host');
});

test('Free user cannot add a domain', function () {
    Package::where('is_default', true)->update(['domain_limit' => 0]);

    $this->actingAs(User::factory()->create())->post(route('domains.store'), ['host' => 'a.example.com'])
        ->assertForbidden();

    expect(Domain::count())->toBe(0);
});

test('user at the limit cannot add another domain', function () {
    $user = userWithDomainSlots(1);
    Domain::factory()->for($user)->create();

    $this->actingAs($user)->post(route('domains.store'), ['host' => 'b.example.com'])->assertForbidden();

    expect($user->domains()->count())->toBe(1);
});

test('owner verifies a domain manually', function () {
    $domain = Domain::factory()->create();
    $this->verifier->verified = [$domain->id];

    $this->actingAs($domain->user)->post(route('domains.verify', $domain))->assertRedirect();

    expect($domain->fresh()->verified_at)->not->toBeNull();
});

test('a domain without the token stays unverified and reports an error', function () {
    $domain = Domain::factory()->create();

    $this->actingAs($domain->user)->post(route('domains.verify', $domain))->assertSessionHasErrors('host');

    expect($domain->fresh()->verified_at)->toBeNull();
});

test("cannot verify or delete another user's domain", function () {
    $domain = Domain::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($other)->post(route('domains.verify', $domain))->assertForbidden();
    $this->actingAs($other)->delete(route('domains.destroy', $domain))->assertForbidden();

    expect($domain->fresh())->not->toBeNull();
});

test('a domain with links cannot be deleted; one without can', function () {
    $user = User::factory()->create();
    $used = Domain::factory()->verified()->for($user)->create();
    Link::factory()->for($user)->create(['domain_id' => $used->id]);
    $unused = Domain::factory()->for($user)->create();

    $this->actingAs($user)->delete(route('domains.destroy', $used))->assertForbidden();
    $this->actingAs($user)->delete(route('domains.destroy', $unused))->assertRedirect(route('domains.index'));

    expect($used->fresh())->not->toBeNull()
        ->and($unused->fresh())->toBeNull();
});

test('index lists only own domains with status and quota', function () {
    $user = userWithDomainSlots(5);
    $pending = Domain::factory()->for($user)->create(['created_at' => now()->subMinutes(3)]);
    $active = Domain::factory()->verified()->for($user)->create(['created_at' => now()->subMinutes(2)]);
    Domain::factory()->verified()->disabledByAdmin()->for($user)->create(['created_at' => now()->subMinute()]);
    Domain::factory()->create();

    $this->actingAs($user)->get(route('domains.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('domains/Index')
            ->has('domains', 3)
            ->where('domains.0.id', $pending->id)
            ->where('domains.0.status', 'pending')
            ->where('domains.0.txt_name', '_shortify-verify.'.$pending->host)
            ->where('domains.0.txt_value', 'shortify-verify='.$pending->verification_token)
            ->where('domains.1.status', 'active')
            ->where('domains.2.status', 'disabled')
            ->where('quota', ['used' => 3, 'limit' => 5])
            ->where('cnameTarget', config('shortify.cname_target')));
});

test('link props never leak a domain verification token', function () {
    $user = userWithDomainSlots(1);
    $domain = Domain::factory()->verified()->for($user)->create();
    Link::factory()->for($user)->create(['domain_id' => $domain->id]);

    $response = $this->actingAs($user)->get(route('links.index'));

    expect($response->getContent())->not->toContain($domain->verification_token);
});

test('an admin-disabled pending domain reports disabled', function () {
    $domain = Domain::factory()->disabledByAdmin()->create();

    expect($domain->status())->toBe('disabled');
});
