<?php

use App\Billing\PackageBilling;
use App\Enums\BillingPeriod;
use App\Models\Package;
use App\Models\PackageAssignment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakePackageBilling;

beforeEach(function () {
    $this->super = User::factory()->superAdmin()->create();
    $this->client = User::factory()->create();
    $this->package = Package::factory()->withPrices()->create([
        'link_limit' => 50, 'stripe_price_id_monthly' => 'price_m', 'stripe_price_id_one_time' => 'price_o',
    ]);
});

test('assigning a package sends a Stripe invoice without granting the limit', function (string $period) {
    $this->actingAs($this->super)->put(route('admin.users.package.update', $this->client), [
        'package_id' => $this->package->id, 'billing_period' => $period,
    ])->assertRedirect();

    expect($this->billing->calls)->toBe([['invoice', [$this->client->id, $this->package->id, $period]]])
        ->and($this->client->packageAssignment()->exists())->toBeFalse()
        ->and($this->client->linkLimit())->toBe(10);
})->with(['monthly', 'one_time']);

test('a period without a Stripe price is rejected', function () {
    $this->actingAs($this->super)->put(route('admin.users.package.update', $this->client), [
        'package_id' => $this->package->id, 'billing_period' => 'yearly',
    ])->assertSessionHasErrors('billing_period');

    expect($this->billing->called())->toBe([]);
});

test('the default and withdrawn packages cannot be assigned', function () {
    $free = Package::where('is_default', true)->firstOrFail();
    $this->package->delete();

    foreach ([$free, $this->package] as $package) {
        $this->actingAs($this->super)->put(route('admin.users.package.update', $this->client), [
            'package_id' => $package->id, 'billing_period' => 'monthly',
        ])->assertSessionHasErrors('package_id');
    }

    expect($this->billing->called())->toBe([]);
});

test('a user who already holds a package cannot be invoiced for another', function () {
    PackageAssignment::factory()->for($this->client)->for($this->package)->create();

    $this->actingAs($this->super)->put(route('admin.users.package.update', $this->client), [
        'package_id' => $this->package->id, 'billing_period' => 'monthly',
    ])->assertSessionHasErrors('package');

    expect($this->billing->called())->toBe([]);
});

test('a failing Stripe call is reported', function () {
    $this->app->instance(PackageBilling::class, new class extends FakePackageBilling
    {
        public function invoice(User $user, Package $package, BillingPeriod $period): void
        {
            throw new RuntimeException('Stripe is unavailable.');
        }
    });

    $this->actingAs($this->super)->put(route('admin.users.package.update', $this->client), [
        'package_id' => $this->package->id, 'billing_period' => 'monthly',
    ])->assertSessionHasErrors('stripe');
});

test('administrators and users cannot assign packages', function () {
    $payload = ['package_id' => $this->package->id, 'billing_period' => 'monthly'];
    $admin = User::factory()->admin()->create();
    $managed = User::factory()->assignedTo($admin)->create();

    $this->actingAs($admin)->put(route('admin.users.package.update', $managed), $payload)->assertForbidden();
    $this->actingAs($this->client)->put(route('admin.users.package.update', $this->client), $payload)->assertForbidden();

    expect($this->billing->called())->toBe([]);
});

test('a package cannot be assigned to a super administrator', function () {
    $other = User::factory()->superAdmin()->create();

    $this->actingAs($this->super)->put(route('admin.users.package.update', $other), [
        'package_id' => $this->package->id, 'billing_period' => 'monthly',
    ])->assertForbidden();
});

test('removing an assignment is no longer possible from the admin panel', function () {
    expect(Route::has('admin.users.package.destroy'))->toBeFalse();
});

test('show exposes package data and the payment state to super admin only', function () {
    PackageAssignment::factory()->for($this->client)->for($this->package)->create();

    $this->actingAs($this->super)->get(route('admin.users.show', $this->client))
        ->assertInertia(fn (Assert $page) => $page
            ->where('packageAssignment.package_id', $this->package->id)
            ->where('packageAssignment.active', true)
            ->where('paymentState', 'active')
            ->where('effectiveLimit', 50)
            ->has('packages'));

    $admin = User::factory()->admin()->create();
    $managed = User::factory()->assignedTo($admin)->create();

    $this->actingAs($admin)->get(route('admin.users.show', $managed))
        ->assertInertia(fn (Assert $page) => $page
            ->where('packageAssignment', null)
            ->where('packages', [])
            ->where('effectiveLimit', 10));
});

test('payment state is awaiting payment while a subscription is incomplete and expired after the period', function () {
    Subscription::create(['user_id' => $this->client->id, 'type' => 'default', 'stripe_id' => 'sub_i', 'stripe_status' => 'incomplete', 'stripe_price' => 'price_m', 'quantity' => 1]);

    $this->actingAs($this->super)->get(route('admin.users.show', $this->client))
        ->assertInertia(fn (Assert $page) => $page->where('paymentState', 'awaiting_payment'));

    $this->client->subscriptions()->delete();
    PackageAssignment::factory()->for($this->client)->for($this->package)->expired()->create();

    $this->actingAs($this->super)->get(route('admin.users.show', $this->client))
        ->assertInertia(fn (Assert $page) => $page->where('paymentState', 'expired'));
});

test('only packages with Stripe prices are offered for assignment', function () {
    Package::factory()->create(['name' => 'Unsynced', 'price_monthly' => 500]);

    $this->actingAs($this->super)->get(route('admin.users.show', $this->client))
        ->assertInertia(fn (Assert $page) => $page->where('packages', fn ($packages) => collect($packages)->pluck('name')->all() === [$this->package->name]
            && $packages[0]['available_periods'] === ['one_time', 'monthly']));
});

test('payment links are visible only for accounts within the actor scope', function () {
    $this->billing->pendingInvoiceUrl = 'https://invoice.stripe.test/i/abc';
    $admin = User::factory()->admin()->create();
    $managed = User::factory()->assignedTo($admin)->create();
    $showsLink = fn (Assert $page) => $page->where('invoiceUrl', 'https://invoice.stripe.test/i/abc');

    $this->actingAs($this->super)->get(route('admin.users.show', $this->client))->assertInertia($showsLink);
    $this->actingAs($admin)->get(route('admin.users.show', $managed))->assertInertia($showsLink);
    $this->actingAs($admin)->get(route('admin.users.show', $this->client))->assertNotFound();
    $this->actingAs($this->client)->get(route('billing.package.show'))
        ->assertInertia(fn (Assert $page) => $page->where('invoiceUrl', 'https://invoice.stripe.test/i/abc'));
    $this->actingAs($this->client)->get(route('admin.users.show', $this->client))->assertForbidden();
});
