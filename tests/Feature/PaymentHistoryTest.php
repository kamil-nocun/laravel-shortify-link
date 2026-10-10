<?php

use App\Enums\BillingPeriod;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->super = User::factory()->superAdmin()->create();
    $this->admin = User::factory()->admin()->create();
    $this->client = User::factory()->assignedTo($this->admin)->create();
    $this->stranger = User::factory()->create();
    $this->package = Package::factory()->create(['name' => 'Pro']);
});

test('a user sees only their own payments on the package page', function () {
    $own = Payment::factory()->paid()->create(['user_id' => $this->client->id, 'package_id' => $this->package->id, 'billing_period' => BillingPeriod::Monthly]);
    Payment::factory()->paid()->create(['user_id' => $this->stranger->id]);

    $this->actingAs($this->client)->get(route('billing.package.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('payments.data', 1)
            ->where('payments.data.0.id', $own->id)
            ->where('payments.data.0.package_name', 'Pro')
            ->where('payments.data.0.billing_period', 'monthly')
            ->where('payments.data.0.status', 'paid')
            ->where('payments.data.0.amount', 1900));
});

test('an administrator sees the payments of their client but not of a stranger', function () {
    Payment::factory()->paid()->create(['user_id' => $this->client->id]);

    $this->actingAs($this->admin)->get(route('admin.users.show', $this->client))
        ->assertInertia(fn (Assert $page) => $page->has('payments.data', 1));
    $this->actingAs($this->admin)->get(route('admin.users.show', $this->stranger))->assertNotFound();
});

test('a super administrator sees the payments of every account', function () {
    Payment::factory()->paid()->create(['user_id' => $this->stranger->id]);

    $this->actingAs($this->super)->get(route('admin.users.show', $this->stranger))
        ->assertInertia(fn (Assert $page) => $page->has('payments.data', 1));
});

test('a plain user cannot open the admin user page', function () {
    $this->actingAs($this->stranger)->get(route('admin.users.show', $this->stranger))->assertForbidden();
});

test('payments are newest first and paginated by ten', function () {
    foreach (range(1, 22) as $day) {
        Payment::factory()->create(['user_id' => $this->client->id, 'issued_at' => now()->subDays($day)]);
    }

    $this->actingAs($this->client)->get(route('billing.package.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('payments.data', 10)
            ->where('payments.data.0.issued_at', now()->subDay()->toIso8601String())
            ->where('payments.last_page', 3)
            ->where('payments.next_page_url', fn ($url) => str_contains($url, 'payments_page=2')));

    $this->actingAs($this->client)->get(route('billing.package.show', ['payments_page' => 3]))
        ->assertInertia(fn (Assert $page) => $page->has('payments.data', 2)->where('payments.next_page_url', null));
});

test('the pay link comes from the table without calling stripe', function () {
    Payment::factory()->open()->create(['user_id' => $this->client->id, 'invoice_url' => 'https://invoice.stripe.test/i/table']);

    $this->actingAs($this->client)->get(route('billing.package.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('invoiceUrl', 'https://invoice.stripe.test/i/table')
            ->where('awaitingPayment', true));

    expect($this->billing->called())->not->toContain('pendingInvoiceUrl');
});

test('an open payment without a link falls back to stripe', function () {
    Payment::factory()->open()->create(['user_id' => $this->client->id, 'invoice_url' => null]);
    $this->billing->pendingInvoiceUrl = 'https://invoice.stripe.test/i/live';

    $this->actingAs($this->client)->get(route('billing.package.show'))
        ->assertInertia(fn (Assert $page) => $page->where('invoiceUrl', 'https://invoice.stripe.test/i/live'));

    expect($this->billing->called())->toContain('pendingInvoiceUrl');
});

test('an incomplete subscription without a payment row falls back to stripe', function () {
    Subscription::create(['user_id' => $this->client->id, 'type' => 'default', 'stripe_id' => 'sub_x', 'stripe_status' => 'incomplete', 'stripe_price' => 'price_x', 'quantity' => 1]);
    $this->billing->pendingInvoiceUrl = 'https://invoice.stripe.test/i/sub';

    $this->actingAs($this->client)->get(route('billing.package.show'))
        ->assertInertia(fn (Assert $page) => $page->where('invoiceUrl', 'https://invoice.stripe.test/i/sub')->where('awaitingPayment', true));
});

test('an account without a pending payment never calls stripe', function () {
    Payment::factory()->paid()->create(['user_id' => $this->client->id]);

    $this->actingAs($this->client)->get(route('billing.package.show'))
        ->assertInertia(fn (Assert $page) => $page->where('invoiceUrl', null)->where('awaitingPayment', false));

    expect($this->billing->called())->not->toContain('pendingInvoiceUrl');
});

test('a stripe failure in the fallback leaves the page without a link', function () {
    Payment::factory()->open()->create(['user_id' => $this->client->id, 'invoice_url' => null]);
    $this->billing->failPendingInvoiceUrl = true;

    $this->actingAs($this->client)->get(route('billing.package.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('invoiceUrl', null));
});

test('an administrator sees their own payments on the package page and their clients on the user page', function () {
    Payment::factory()->paid()->create(['user_id' => $this->admin->id]);
    Payment::factory()->paid()->create(['user_id' => $this->client->id]);

    $this->actingAs($this->admin)->get(route('billing.package.show'))
        ->assertInertia(fn (Assert $page) => $page->has('payments.data', 1)->where('payments.data.0.id', $this->admin->payments()->value('id')));
    $this->actingAs($this->admin)->get(route('admin.users.show', $this->client))
        ->assertInertia(fn (Assert $page) => $page->has('payments.data', 1)->where('payments.data.0.id', $this->client->payments()->value('id')));
});

test('a super administrator sees the history of administrators on the user page', function () {
    Payment::factory()->paid()->create(['user_id' => $this->admin->id]);

    $this->actingAs($this->super)->get(route('admin.users.show', $this->admin))
        ->assertInertia(fn (Assert $page) => $page->has('payments.data', 1));
});
