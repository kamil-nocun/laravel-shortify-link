<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BillingPeriod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserPackageRequest;
use App\Models\Package;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class UserPackageController extends Controller
{
    /**
     * Assign a package to the user, replacing any previous assignment.
     */
    public function update(UpdateUserPackageRequest $request, User $user): RedirectResponse
    {
        $period = BillingPeriod::from($request->validated('billing_period'));
        $startsAt = $request->date('starts_at') ?? now();

        DB::transaction(function () use ($request, $user, $period, $startsAt) {
            $package = Package::lockForUpdate()->findOrFail($request->validated('package_id'));
            $user->packageAssignment()->lockForUpdate()->first();

            $user->packageAssignment()->updateOrCreate([], [
                'package_id' => $package->id,
                'billing_period' => $period,
                'price' => $package->priceFor($period),
                'link_limit' => $package->link_limit,
                'starts_at' => $startsAt,
                'expires_at' => $period->expiresAt($startsAt) ?? $request->date('expires_at'),
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Package assigned.')]);

        return back();
    }

    /**
     * Remove the assignment; the user falls back to the default package.
     */
    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('assignPackage', $user);

        $user->packageAssignment()->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Package removed.')]);

        return back();
    }
}
