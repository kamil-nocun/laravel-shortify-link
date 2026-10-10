<?php

use App\Jobs\VerifyDomain;
use App\Models\Domain;
use App\Models\Link;
use App\Models\Package;
use App\Models\PackageAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

function clientWithDomainSlots(int $limit, ?User $admin = null): User
{
    $user = $admin ? User::factory()->assignedTo($admin)->create() : User::factory()->create();
    PackageAssignment::factory()->for($user)->for(Package::factory()->create(['domain_limit' => $limit]))->create();

    return $user;
}

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('admin adds a domain for a managed user within the limit', function () {
    Queue::fake();
    $client = clientWithDomainSlots(1, $this->admin);

    $this->actingAs($this->admin)->post(route('admin.users.domains.store', $client), ['host' => 'Links.Example.com'])
        ->assertRedirect();

    $domain = $client->domains()->sole();
    expect($domain->host)->toBe('links.example.com')->and($domain->verified_at)->toBeNull();
    Queue::assertPushed(VerifyDomain::class, fn (VerifyDomain $job) => $job->domainId === $domain->id);
});

test('admin cannot add a domain beyond the user\'s limit', function () {
    Queue::fake();
    $client = clientWithDomainSlots(1, $this->admin);
    Domain::factory()->for($client)->create();

    $this->actingAs($this->admin)->post(route('admin.users.domains.store', $client), ['host' => 'second.example.com'])
        ->assertForbidden();

    expect($client->domains()->count())->toBe(1);
    Queue::assertNothingPushed();
});

test('admin validates the host like the owner does', function () {
    $client = clientWithDomainSlots(2, $this->admin);

    $this->actingAs($this->admin)->post(route('admin.users.domains.store', $client), ['host' => 'example.com:8080'])
        ->assertSessionHasErrors('host');
});

test('admin outside the user\'s scope gets 404 for add, delete, disable and enable', function () {
    $client = clientWithDomainSlots(1);
    $domain = Domain::factory()->for($client)->create();

    $this->actingAs($this->admin)->post(route('admin.users.domains.store', $client), ['host' => 'x.example.com'])->assertNotFound();
    $this->actingAs($this->admin)->delete(route('admin.domains.destroy', $domain))->assertNotFound();
    $this->actingAs($this->admin)->patch(route('admin.domains.disable', $domain))->assertNotFound();
    $this->actingAs($this->admin)->delete(route('admin.domains.enable', $domain))->assertNotFound();

    expect($domain->fresh())->not->toBeNull()->disabled_by_admin_at->toBeNull();
});

test('super admin can manage any user\'s domains', function () {
    Queue::fake();
    $super = User::factory()->superAdmin()->create();
    $client = clientWithDomainSlots(2);

    $this->actingAs($super)->post(route('admin.users.domains.store', $client), ['host' => 'a.example.com'])->assertRedirect();
    $domain = $client->domains()->sole();

    $this->actingAs($super)->patch(route('admin.domains.disable', $domain))->assertRedirect();
    expect($domain->fresh()->disabled_by)->toBe($super->id);

    $this->actingAs($super)->delete(route('admin.domains.destroy', $domain))->assertRedirect();
    expect(Domain::count())->toBe(0);
});

test('admin disables and enables a domain, which stops and restores redirecting', function () {
    config(['app.url' => 'http://shortify.test']);
    $client = User::factory()->assignedTo($this->admin)->create();
    $domain = Domain::factory()->verified()->for($client)->create();
    Link::factory()->for($client)->create(['domain_id' => $domain->id, 'code' => 'abc123', 'original_url' => 'https://a.test']);

    $this->actingAs($this->admin)->patch(route('admin.domains.disable', $domain))->assertRedirect();
    expect($domain->fresh())->disabled_by->toBe($this->admin->id)->disabled_by_admin_at->not->toBeNull();
    $this->get('http://'.$domain->host.'/abc123')->assertNotFound();

    $this->actingAs($this->admin)->delete(route('admin.domains.enable', $domain))->assertRedirect();
    expect($domain->fresh())->disabled_by->toBeNull()->disabled_by_admin_at->toBeNull();
    $this->get('http://'.$domain->host.'/abc123')->assertRedirect('https://a.test');
});

test('admin deletes an unused domain but not one that has links', function () {
    $client = User::factory()->assignedTo($this->admin)->create();
    $used = Domain::factory()->verified()->for($client)->create();
    Link::factory()->for($client)->create(['domain_id' => $used->id]);
    $unused = Domain::factory()->for($client)->create();

    $this->actingAs($this->admin)->delete(route('admin.domains.destroy', $used))->assertForbidden();
    expect($used->fresh())->not->toBeNull();

    $this->actingAs($this->admin)->delete(route('admin.domains.destroy', $unused))->assertRedirect();
    expect($unused->fresh())->toBeNull();
});

test('a regular user cannot reach the admin domain routes', function () {
    $user = clientWithDomainSlots(1);
    $domain = Domain::factory()->for($user)->create();
    $this->actingAs($user);

    $this->post(route('admin.users.domains.store', $user), ['host' => 'x.example.com'])->assertForbidden();
    $this->delete(route('admin.domains.destroy', $domain))->assertForbidden();
    $this->patch(route('admin.domains.disable', $domain))->assertForbidden();
    $this->delete(route('admin.domains.enable', $domain))->assertForbidden();
});

test('show lists the user\'s domains only for admins who manage them', function () {
    $client = clientWithDomainSlots(3, $this->admin);
    $disabler = User::factory()->superAdmin()->create(['name' => 'Boss']);
    $pending = Domain::factory()->for($client)->create(['host' => 'p.example.com']);
    $disabled = Domain::factory()->verified()->for($client)->create(['host' => 'd.example.com', 'disabled_by_admin_at' => now(), 'disabled_by' => $disabler->id]);
    Link::factory()->for($client)->create(['domain_id' => $disabled->id]);

    $this->actingAs($this->admin)->get(route('admin.users.show', $client))
        ->assertInertia(fn (Assert $page) => $page
            ->where('domainLimit', 3)
            ->has('domains', 2)
            ->where('domains.0', ['id' => $pending->id, 'host' => 'p.example.com', 'status' => 'pending', 'disabled_by_name' => null, 'links_count' => 0])
            ->where('domains.1.status', 'disabled')
            ->where('domains.1.disabled_by_name', 'Boss')
            ->where('domains.1.links_count', 1)
            ->missing('domains.0.verification_token'));

    $other = User::factory()->admin()->create();
    $client->assignedAdmins()->detach($this->admin);
    $client->assignedAdmins()->attach($other);
    $this->actingAs($this->admin)->get(route('admin.users.show', $client))->assertNotFound();
});
