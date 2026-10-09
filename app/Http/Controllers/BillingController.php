<?php

namespace App\Http\Controllers;

use App\Billing\PackageBilling;
use App\Enums\BillingPeriod;
use App\Http\Requests\Billing\CheckoutRequest;
use App\Models\Package;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class BillingController extends Controller
{
    public function __construct(private readonly PackageBilling $billing) {}

    /**
     * Show the user's package and the packages they can buy.
     */
    public function show(Request $request): Response
    {
        $user = $request->user();
        abort_if($user->isSuperAdmin(), 403);

        $assignment = $user->activeAssignment();

        return Inertia::render('billing/Package', [
            'current' => [
                'package_name' => $user->package()?->name,
                'link_limit' => $user->linkLimit(),
                'billing_period' => $assignment?->billing_period->value,
                'expires_at' => $assignment?->expires_at->toIso8601String(),
            ],
            'awaitingPayment' => $user->subscriptions()->incomplete()->exists(),
            'checkoutStatus' => $request->query('checkout'),
            'packages' => Package::where('is_default', false)->orderBy('name')->get()
                ->map(fn (Package $package) => [
                    'id' => $package->id,
                    'name' => $package->name,
                    'link_limit' => $package->link_limit,
                    'prices' => collect($package->availablePeriods())
                        ->filter(fn (BillingPeriod $period) => $package->stripePriceId($period) !== null)
                        ->mapWithKeys(fn (BillingPeriod $period) => [$period->value => $package->priceFor($period)])
                        ->all(),
                ])
                ->filter(fn (array $package) => $package['prices'] !== [])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Send the user to Stripe Checkout for the chosen package and period.
     */
    public function checkout(CheckoutRequest $request): SymfonyResponse
    {
        $url = $this->billing->checkout(
            $request->user(),
            Package::findOrFail($request->validated('package_id')),
            BillingPeriod::from($request->validated('billing_period')),
            route('billing.package.show', ['checkout' => 'success']),
            route('billing.package.show', ['checkout' => 'cancelled']),
        );

        return Inertia::location($url);
    }
}
