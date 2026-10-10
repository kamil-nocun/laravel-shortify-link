<?php

namespace App\Http\Controllers;

use App\Billing\PackageBilling;
use App\Billing\PackagePageData;
use App\Enums\BillingPeriod;
use App\Http\Requests\Billing\CheckoutRequest;
use App\Models\Package;
use Illuminate\Http\Request;
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
        abort_if($request->user()->isSuperAdmin(), 403);

        return Inertia::render('billing/Package', [
            ...$this->pageData->for($request->user()),
            'checkoutStatus' => $request->query('checkout'),
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
