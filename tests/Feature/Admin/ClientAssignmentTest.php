<?php

use App\Models\Link;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
