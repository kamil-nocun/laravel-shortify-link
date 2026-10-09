<?php

use App\Models\Link;
use App\Models\LinkClick;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('attach creates pivot row with non-empty id', function () {
    $client = User::factory()->create();
    $client->assignedAdmins()->attach(User::factory()->admin()->create());

    expect(DB::table('client_admin')->first()->id)->not->toBeEmpty();
});

test('user visibleTo scopes by actor role', function () {
    $super = User::factory()->superAdmin()->create();
    $admin = User::factory()->admin()->create();
    $other = User::factory()->admin()->create();
    $mine = User::factory()->assignedTo($admin)->create();
    $theirs = User::factory()->assignedTo($other)->create();
    $plain = User::factory()->create();

    expect(User::visibleTo($super)->count())->toBe(6)
        ->and(User::visibleTo($admin)->pluck('id')->all())->toBe([$mine->id])
        ->and(User::visibleTo($plain)->count())->toBe(0);
    expect($admin->managedClients()->pluck('users.id')->all())->toBe([$mine->id]);
});

test('link visibleTo scopes by actor role', function () {
    $super = User::factory()->superAdmin()->create();
    $admin = User::factory()->admin()->create();
    $mine = User::factory()->assignedTo($admin)->create();
    $linkMine = Link::factory()->for($mine)->create();
    Link::factory()->for(User::factory()->create())->create();
    $plain = User::factory()->create();

    expect(Link::visibleTo($super)->count())->toBe(2)
        ->and(Link::visibleTo($admin)->pluck('id')->all())->toBe([$linkMine->id])
        ->and(Link::visibleTo($plain)->count())->toBe(0);
});

test('deleting administrator removes assignments but keeps client', function () {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->assignedTo($admin)->create();

    $admin->delete();

    $this->assertDatabaseMissing('client_admin', ['client_id' => $client->id]);
    $this->assertModelExists($client);
});

test('administrator gets 404 on unassigned client routes and access after assignment', function () {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->create();
    $this->actingAs($admin);

    $check = function (int $status, int $createStatus, int $storeStatus) use ($client) {
        $this->get(route('admin.users.show', $client))->assertStatus($status);
        $this->get(route('admin.users.edit', $client))->assertStatus($status);
        $this->patch(route('admin.users.block', $client))->assertStatus($status === 404 ? 404 : 302);
        $this->get(route('admin.users.links.create', $client))->assertStatus($createStatus);
        $this->post(route('admin.users.links.store', $client), [])->assertStatus($storeStatus);
    };

    $check(404, 404, 404);
    $this->delete(route('admin.users.destroy', $client))->assertNotFound();

    $client->assignedAdmins()->attach($admin);
    $check(200, 200, 302);
    $this->delete(route('admin.users.destroy', $client))->assertRedirect();
});

test('administrator gets 404 on other administrators and super administrators', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $this->get(route('admin.users.show', User::factory()->admin()->create()))->assertNotFound();
    $this->get(route('admin.users.show', User::factory()->superAdmin()->create()))->assertNotFound();
});

test('administrator gets 404 on links of unassigned clients', function () {
    $admin = User::factory()->admin()->create();
    $link = Link::factory()->for(User::factory()->create())->create();
    $this->actingAs($admin);

    $this->get(route('admin.links.stats', $link))->assertNotFound();
    $this->getJson(route('admin.links.stats.data', $link))->assertNotFound();
    $this->patch(route('admin.links.disable', $link))->assertNotFound();
});

test('super administrator sees unassigned client', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    $this->get(route('admin.users.show', User::factory()->create()))->assertOk();
});

test('regular user gets 403 not 404 on admin routes', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.users.show', User::factory()->create()))->assertForbidden();
});

test('policy manages requires assignment for administrators', function () {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->create();
    $policy = new UserPolicy;

    expect($policy->manages($admin, $client))->toBeFalse();
    $client->assignedAdmins()->attach($admin);
    expect($policy->manages($admin, $client))->toBeTrue()
        ->and($policy->manages(User::factory()->superAdmin()->create(), $client))->toBeTrue()
        ->and($policy->manages($client, $client))->toBeFalse();
});

test('dashboard and user list are scoped to assigned clients', function () {
    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create();
    $clientA = User::factory()->assignedTo($adminA)->create();
    $clientB = User::factory()->assignedTo($adminB)->create();
    $linkA = Link::factory()->for($clientA)->create();
    $linkB = Link::factory()->for($clientB)->create();
    LinkClick::factory()->count(2)->create(['link_id' => $linkA->id]);
    LinkClick::factory()->count(3)->create(['link_id' => $linkB->id]);

    $this->actingAs($adminA)->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('counters.users', 1)
            ->where('counters.links', 1)
            ->where('counters.clicks', 2)
            ->has('topLinks', 1)
            ->where('topLinks.0.id', $linkA->id)
            ->has('topUsers', 1)
            ->where('topUsers.0.id', $clientA->id));

    $this->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.id', $clientA->id));

    $clientB->assignedAdmins()->attach($adminA);
    $this->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 2));
    $this->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('counters.users', 2)->where('counters.clicks', 5));

    $this->actingAs(User::factory()->superAdmin()->create())->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('counters.clicks', 5)->has('topUsers', 2));
});

test('administrator creating a client is assigned and extra administrators are ignored', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $other = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'New', 'email' => 'new@example.com', 'administrators' => [$other->id],
    ])->assertSessionHasNoErrors();

    expect(User::where('email', 'new@example.com')->first()->assignedAdmins->pluck('id')->all())->toBe([$admin->id]);
});

test('super administrator assigns chosen administrators on creation', function () {
    Notification::fake();
    $super = User::factory()->superAdmin()->create();
    [$a, $b] = User::factory()->admin()->count(2)->create();

    $this->actingAs($super)->post(route('admin.users.store'), [
        'name' => 'One', 'email' => 'one@example.com', 'administrators' => [$a->id, $b->id],
    ])->assertSessionHasNoErrors();
    $this->actingAs($super)->post(route('admin.users.store'), ['name' => 'Two', 'email' => 'two@example.com']);

    expect(User::where('email', 'one@example.com')->first()->assignedAdmins->pluck('id')->sort()->values()->all())
        ->toBe(collect([$a->id, $b->id])->sort()->values()->all())
        ->and(User::where('email', 'two@example.com')->first()->assignedAdmins)->toBeEmpty();
});

test('super administrator cannot assign non-administrators', function (string $kind) {
    $super = User::factory()->superAdmin()->create();
    $id = $kind === 'client' ? User::factory()->create()->id : (string) Str::uuid();

    $this->actingAs($super)->post(route('admin.users.store'), [
        'name' => 'X', 'email' => 'x@example.com', 'administrators' => [$id],
    ])->assertSessionHasErrors('administrators.0');
})->with(['client', 'random']);

test('super administrator updates assignments and administrator loses access', function () {
    $super = User::factory()->superAdmin()->create();
    $admin = User::factory()->admin()->create();
    $client = User::factory()->assignedTo($admin)->create();
    $new = User::factory()->admin()->create();

    $this->actingAs($super)->put(route('admin.users.administrators.update', $client), ['administrators' => [$new->id]])
        ->assertRedirect();
    expect($client->assignedAdmins()->pluck('users.id')->all())->toBe([$new->id]);
    $this->actingAs($admin)->get(route('admin.users.show', $client))->assertNotFound();

    $this->actingAs($super)->put(route('admin.users.administrators.update', $client), ['administrators' => []])->assertRedirect();
    expect($client->assignedAdmins()->count())->toBe(0);
    $this->actingAs($super)->get(route('admin.users.show', $client))->assertOk();
});

test('administrator cannot update assignments', function () {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->assignedTo($admin)->create();

    $this->actingAs($admin)->put(route('admin.users.administrators.update', $client), ['administrators' => []])->assertForbidden();
});
