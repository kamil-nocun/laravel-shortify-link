<?php

use App\Models\Link;
use App\Models\User;

test('a user blocked mid-session is logged out on the next request', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->forceFill(['blocked_at' => now()])->save();

    $this->get('/dashboard')->assertRedirect(route('login'));

    $this->assertGuest();
});

test('a blocked user gets 401 on json requests', function () {
    $user = User::factory()->blocked()->create();

    $this->actingAs($user)->getJson('/dashboard')->assertUnauthorized();

    $this->assertGuest();
});

test('a blocked user cannot log in', function () {
    $user = User::factory()->blocked()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('an unblocked user logs in normally', function () {
    $user = User::factory()->blocked()->create();
    $user->forceFill(['blocked_at' => null])->save();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    $this->assertAuthenticatedAs($user);
});

test('a blocked user gets 303 on inertia delete requests', function () {
    $user = User::factory()->blocked()->create();
    $link = Link::factory()->for($user)->create();

    $this->actingAs($user)->withHeader('X-Inertia', 'true')->delete(route('links.destroy', $link))->assertStatus(303);

    $this->assertGuest();
});
