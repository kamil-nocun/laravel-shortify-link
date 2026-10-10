<?php

use App\Models\Package;
use App\Models\PackageAssignment;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->package = Package::factory()->create([
        'name' => 'Pro', 'link_limit' => 50, 'price_monthly' => 1900, 'price_yearly' => 19000,
        'stripe_price_id_monthly' => 'price_m', 'stripe_price_id_yearly' => 'price_y',
    ]);
});

test('guests are sent to login', function () {
    $this->get(route('billing.package.show'))->assertRedirect(route('login'));
    $this->post(route('billing.package.checkout'))->assertRedirect(route('login'));
});

test('the page lists sellable packages with their priced periods and the current package', function () {
    Package::factory()->create(['name' => 'Hidden', 'price_monthly' => 100, 'stripe_price_id_monthly' => 'price_h'])->delete();

    $this->actingAs($this->user)->get(route('billing.package.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('billing/Package')
            ->where('current.package_name', 'Free')
            ->where('current.link_limit', 10)
            ->where('packages', fn ($packages) => collect($packages)->pluck('name')->all() === ['Pro']
                && $packages[0]['prices'] === ['monthly' => 1900, 'yearly' => 19000]));
});

test('the page shows the active assignment and awaiting payment', function () {
    PackageAssignment::factory()->for($this->user)->for($this->package)->create();
    Subscription::create(['user_id' => $this->user->id, 'type' => 'default', 'stripe_id' => 'sub_x', 'stripe_status' => 'incomplete', 'stripe_price' => 'price_m', 'quantity' => 1]);

    $this->actingAs($this->user)->get(route('billing.package.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('current.package_name', $this->package->name)
            ->where('awaitingPayment', true));
});

test('checkout redirects to Stripe', function () {
    $this->actingAs($this->user)->withHeaders(['X-Inertia' => 'true'])->post(route('billing.package.checkout'), ['package_id' => $this->package->id, 'billing_period' => 'monthly'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://checkout.stripe.test/session');

    expect($this->billing->calls)->toBe([['checkout', [$this->user->id, $this->package->id, 'monthly']]]);
});

test('a period the package does not sell is rejected', function () {
    $this->actingAs($this->user)->post(route('billing.package.checkout'), ['package_id' => $this->package->id, 'billing_period' => 'quarterly'])
        ->assertSessionHasErrors('billing_period');

    expect($this->billing->called())->toBe([]);
});

test('a missing period is rejected', function () {
    $this->actingAs($this->user)->post(route('billing.package.checkout'), ['package_id' => $this->package->id])
        ->assertSessionHasErrors('billing_period');
});

test('the default and withdrawn packages cannot be bought', function () {
    $default = Package::where('is_default', true)->firstOrFail();
    $this->package->delete();

    $this->actingAs($this->user)->post(route('billing.package.checkout'), ['package_id' => $default->id, 'billing_period' => 'monthly'])->assertSessionHasErrors('package_id');
    $this->actingAs($this->user)->post(route('billing.package.checkout'), ['package_id' => $this->package->id, 'billing_period' => 'monthly'])->assertSessionHasErrors('package_id');
});

test('a user with an active package cannot buy another one', function () {
    PackageAssignment::factory()->for($this->user)->for($this->package)->create();

    $this->actingAs($this->user)->post(route('billing.package.checkout'), ['package_id' => $this->package->id, 'billing_period' => 'monthly'])
        ->assertSessionHasErrors('package');

    expect($this->billing->called())->toBe([]);
});

test('super admins have no package page', function () {
    $this->actingAs(User::factory()->superAdmin()->create())->get(route('billing.package.show'))->assertForbidden();
});

test('super admins cannot start a checkout', function () {
    $package = Package::factory()->create(['is_default' => false]);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->post(route('billing.package.checkout'), ['package_id' => $package->id, 'billing_period' => 'monthly'])
        ->assertForbidden();
});

test('the package page links to the open Stripe invoice', function () {
    Payment::factory()->open()->create(['user_id' => $this->user->id, 'invoice_url' => null]);
    $this->billing->pendingInvoiceUrl = 'https://invoice.stripe.test/i/abc';

    $this->actingAs($this->user)->get(route('billing.package.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('invoiceUrl', 'https://invoice.stripe.test/i/abc')
            ->where('awaitingPayment', true));
});

test('the package page has no invoice link when nothing is open', function () {
    $this->actingAs($this->user)->get(route('billing.package.show'))
        ->assertInertia(fn (Assert $page) => $page->where('invoiceUrl', null)->where('awaitingPayment', false));
});

test('a user cancels their own subscription', function () {
    $subscription = Subscription::create(['user_id' => $this->user->id, 'type' => 'default', 'stripe_id' => 'sub_c', 'stripe_status' => 'active', 'stripe_price' => 'price_m', 'quantity' => 1]);
    PackageAssignment::factory()->for($this->user)->for($this->package)->create(['subscription_id' => $subscription->id]);

    $this->actingAs($this->user)->get(route('billing.package.show'))->assertInertia(fn (Assert $page) => $page->where('current.cancellable', true));
    $this->actingAs($this->user)->delete(route('billing.package.cancel'))->assertRedirect();

    expect($this->billing->calls)->toBe([['cancelSubscription', [$subscription->id]]]);
});

test('an admin cancels only their own subscription', function () {
    $admin = User::factory()->admin()->create();
    $subscription = Subscription::create(['user_id' => $admin->id, 'type' => 'default', 'stripe_id' => 'sub_a', 'stripe_status' => 'active', 'stripe_price' => 'price_m', 'quantity' => 1]);
    PackageAssignment::factory()->for($admin)->for($this->package)->create(['subscription_id' => $subscription->id]);
    $clientSubscription = Subscription::create(['user_id' => $this->user->id, 'type' => 'default', 'stripe_id' => 'sub_u', 'stripe_status' => 'active', 'stripe_price' => 'price_m', 'quantity' => 1]);
    PackageAssignment::factory()->for($this->user)->for($this->package)->create(['subscription_id' => $clientSubscription->id]);

    $this->actingAs($admin)->delete(route('billing.package.cancel'))->assertRedirect();

    expect($this->billing->calls)->toBe([['cancelSubscription', [$subscription->id]]]);
});

test('cancelling a one-off package keeps it until it expires', function () {
    PackageAssignment::factory()->for($this->user)->for($this->package)->create(['subscription_id' => null]);

    $this->actingAs($this->user)->delete(route('billing.package.cancel'))->assertRedirect();

    $this->actingAs($this->user)->get(route('billing.package.show'))->assertInertia(fn (Assert $page) => $page->where('current.cancellable', false)->where('current.cancels', true));
    $this->actingAs($this->user)->delete(route('billing.package.cancel'))->assertSessionHasErrors('package');

    expect($this->user->fresh()->activeAssignment()->cancelled_at)->not->toBeNull()->and($this->billing->called())->toBe([]);
});

test('nothing is cancelled without a package', function () {
    $this->actingAs($this->user)->delete(route('billing.package.cancel'))->assertSessionHasErrors('package');
});

test('guests and super admins cannot cancel', function () {
    $this->delete(route('billing.package.cancel'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->superAdmin()->create())->delete(route('billing.package.cancel'))->assertForbidden();
});

test('a cancelled package can be followed by a new purchase, but only one', function () {
    $current = PackageAssignment::factory()->for($this->user)->for($this->package)->create(['cancelled_at' => now()]);
    $data = ['package_id' => $this->package->id, 'billing_period' => 'monthly'];

    $this->actingAs($this->user)->get(route('billing.package.show'))->assertInertia(fn (Assert $page) => $page->where('current.can_buy', true));
    $this->actingAs($this->user)->withHeaders(['X-Inertia' => 'true'])->post(route('billing.package.checkout'), $data)->assertStatus(409);

    PackageAssignment::factory()->for($this->user)->for($this->package)->create(['starts_at' => $current->expires_at, 'expires_at' => $current->expires_at->copy()->addMonth()]);

    $this->actingAs($this->user)->post(route('billing.package.checkout'), $data)->assertSessionHasErrors('package');
});

test('the page lists the package history, newest first', function () {
    PackageAssignment::factory()->for($this->user)->for($this->package)->create(['starts_at' => now()->subMonths(2), 'expires_at' => now()->subMonth()]);
    PackageAssignment::factory()->for($this->user)->for($this->package)->create(['starts_at' => now()->subDay(), 'expires_at' => now()->addMonth(), 'cancelled_at' => now()]);
    PackageAssignment::factory()->for(User::factory()->create())->for($this->package)->create();
    Payment::factory()->open()->create(['user_id' => $this->user->id, 'package_id' => $this->package->id]);

    $this->actingAs($this->user)->get(route('billing.package.show'))
        ->assertInertia(fn (Assert $page) => $page->where('assignments.data', fn ($rows) => collect($rows)->pluck('status')->all() === ['awaiting_payment', 'cancelled', 'expired']));
});

test('a subscription not yet activated by a payment is listed as awaiting payment', function () {
    Subscription::create(['user_id' => $this->user->id, 'type' => 'default', 'stripe_id' => 'sub_d', 'stripe_status' => 'active', 'stripe_price' => 'price_m', 'quantity' => 1]);

    $this->actingAs($this->user)->get(route('billing.package.show'))
        ->assertInertia(fn (Assert $page) => $page->where('assignments.data.0.status', 'awaiting_payment')->where('assignments.data.0.package_name', 'Pro')->where('assignments.data.0.price', 1900));
});

test('an awaiting package starts when the current one ends', function () {
    $current = PackageAssignment::factory()->for($this->user)->for($this->package)->create(['cancelled_at' => now()]);
    Payment::factory()->open()->create(['user_id' => $this->user->id, 'package_id' => $this->package->id]);

    $this->actingAs($this->user)->get(route('billing.package.show'))
        ->assertInertia(fn (Assert $page) => $page->where('assignments.data.0.status', 'awaiting_payment')->where('assignments.data.0.starts_at', $current->expires_at->toIso8601String()));
});
