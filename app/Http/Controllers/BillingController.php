<?php

namespace App\Http\Controllers;

use App\Billing\PackageBilling;
use App\Billing\PackagePageData;
use App\Enums\BillingPeriod;
use App\Enums\Permission;
use App\Http\Requests\Billing\CheckoutRequest;
use App\Models\Package;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class BillingController extends Controller
{
    public function __construct(private readonly PackageBilling $billing, private readonly PackagePageData $pageData) {}

    /**
     * Show the user's package and the packages they can buy.
     */
    public function show(Request $request): Response
    {
        abort_if($request->user()->isSuperAdmin() || ! $request->user()->hasPermission(Permission::BillingManage), 403);

        return Inertia::render('billing/Package', [
            ...$this->pageData->for($request->user()),
            'checkoutStatus' => $request->query('checkout'),
        ]);
    }

    /**
     * Cancel the user's own package: a subscription ends with the paid period, a one-off package is only marked cancelled and runs until it expires (no refund).
     */
    public function cancel(Request $request): RedirectResponse
    {
        abort_if($request->user()->isSuperAdmin() || ! $request->user()->hasPermission(Permission::BillingManage), 403);

        $assignment = $request->user()->activeAssignment();
        $subscription = $assignment?->subscription;

        if ($assignment === null || $assignment->cancelled_at !== null || $subscription?->onGracePeriod() || $subscription?->ended()) {
            throw ValidationException::withMessages(['package' => 'There is no package to cancel.']);
        }

        $subscription === null ? $assignment->update(['cancelled_at' => now()]) : $this->billing->cancelSubscription($subscription);

        return back();
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
