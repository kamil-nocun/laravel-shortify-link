<?php

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
