<?php

use App\Models\Link;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserConsent;
use App\Support\ConsentDocuments;

function customerWithData(): User
{
    $user = User::factory()->create(['stripe_id' => 'cus_123']);
    Link::factory()->for($user)->create();
    Payment::factory()->for($user)->create();

    return $user;
}

test('deleting the account removes the stripe customer and then the data', function () {
    documentPage('terms');
    $user = customerWithData();
    ConsentDocuments::accept($user);

    $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect(route('front_app'));

    expect($this->billing->calls)->toBe([['deleteCustomer', [$user->id]]])
        ->and(User::whereKey($user->id)->exists())->toBeFalse()
        ->and(Link::count())->toBe(0)
        ->and(Payment::count())->toBe(0)
        ->and(UserConsent::count())->toBe(0);
    $this->assertGuest();
});

test('a stripe failure keeps the account and its data', function () {
    $this->billing->failDeleteCustomer = true;
    $user = customerWithData();

    $this->actingAs($user)->from(route('profile.edit'))->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect(User::whereKey($user->id)->exists())->toBeTrue()->and(Link::count())->toBe(1)->and(Payment::count())->toBe(1);
    $this->assertAuthenticatedAs($user);
});

test('an account without stripe is deleted without calling stripe', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect(route('front_app'));

    expect(User::whereKey($user->id)->exists())->toBeFalse();
});

test('profile deletion still requires the password', function () {
    $user = customerWithData();

    $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'wrong'])->assertSessionHasErrors('password');

    expect($this->billing->calls)->toBe([])->and(User::whereKey($user->id)->exists())->toBeTrue();
});

test('an admin deleting a user goes through the same action', function () {
    $admin = User::factory()->superAdmin()->create();
    $user = customerWithData();

    $this->actingAs($admin)->delete(route('admin.users.destroy', $user))->assertRedirect(route('admin.users.index'));

    expect($this->billing->calls)->toBe([['deleteCustomer', [$user->id]]])
        ->and(User::whereKey($user->id)->exists())->toBeFalse();
});

test('a stripe failure stops an admin deleting a user', function () {
    $this->billing->failDeleteCustomer = true;
    $admin = User::factory()->superAdmin()->create();
    $user = customerWithData();

    $this->actingAs($admin)->delete(route('admin.users.destroy', $user))->assertRedirect();

    expect(User::whereKey($user->id)->exists())->toBeTrue()->and(Link::count())->toBe(1);
});
