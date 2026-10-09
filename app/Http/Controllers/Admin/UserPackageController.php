<?php

namespace App\Http\Controllers\Admin;

use App\Billing\PackageBilling;
use App\Enums\BillingPeriod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserPackageRequest;
use App\Models\Package;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;

class UserPackageController extends Controller
{
    public function __construct(private readonly PackageBilling $billing) {}

    /**
     * Invoice the user for the package; it activates when Stripe reports the payment.
     */
    public function update(UpdateUserPackageRequest $request, User $user): RedirectResponse
    {
        try {
            $this->billing->invoice(
                $user,
                Package::findOrFail($request->validated('package_id')),
                BillingPeriod::from($request->validated('billing_period')),
            );
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages(['stripe' => __('The invoice could not be created in Stripe.')]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invoice sent. The package activates after payment.')]);

        return back();
    }
}
