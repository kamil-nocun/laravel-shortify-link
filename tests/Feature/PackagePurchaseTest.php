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

test('super admins see the package page but cannot buy', function () {
    $this->actingAs(User::factory()->superAdmin()->create())->get(route('billing.package.show'))->assertOk()->assertInertia(fn ($page) => $page->where('canBuy', false));
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
