<?php

use App\Models\Subscription;
use App\Models\User;
use App\Support\StripeSandboxGuard;
use Illuminate\Support\Str;

test('users are billable', function () {
    expect(method_exists(User::factory()->create(), 'createOrGetStripeCustomer'))->toBeTrue();
});

test('subscriptions use uuid keys and belong to a user', function () {
    $user = User::factory()->create();

    $subscription = $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_'.Str::random(10),
        'stripe_status' => 'active',
        'stripe_price' => 'price_test',
        'quantity' => 1,
    ]);

    expect($subscription)->toBeInstanceOf(Subscription::class)
        ->and(Str::isUuid($subscription->id))->toBeTrue()
        ->and($subscription->user->is($user))->toBeTrue();

    $item = $subscription->items()->create([
        'stripe_id' => 'si_'.Str::random(10),
        'stripe_product' => 'prod_test',
        'stripe_price' => 'price_test',
        'quantity' => 1,
    ]);

    expect(Str::isUuid($item->id))->toBeTrue()
        ->and($item->subscription_id)->toBe($subscription->id);
});

test('sandbox guard accepts test keys only', function () {
    config(['cashier.secret' => 'sk_test_abc']);
    StripeSandboxGuard::assertTestMode();

    config(['cashier.secret' => 'sk_live_abc']);
    expect(fn () => StripeSandboxGuard::assertTestMode())->toThrow(RuntimeException::class);
});

test('sandbox guard is skipped in production', function () {
    config(['cashier.secret' => 'sk_live_abc']);
    app()->detectEnvironment(fn () => 'production');

    StripeSandboxGuard::assertTestMode();

    expect(true)->toBeTrue();
});
