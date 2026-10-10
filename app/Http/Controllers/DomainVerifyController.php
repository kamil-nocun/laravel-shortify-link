<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Support\DomainVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class DomainVerifyController extends Controller
{
    /**
     * Re-check the domain's TXT record right away.
     */
    public function __invoke(Domain $domain, DomainVerifier $verifier): RedirectResponse
    {
        Gate::authorize('verify', $domain);

        if (! $domain->isVerified()) {
            if (! $verifier->hasToken($domain)) {
                throw ValidationException::withMessages(['host' => __('The verification record was not found yet. DNS changes can take a while to propagate.')]);
            }

            $domain->markVerified();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Domain verified.')]);

        return to_route('domains.index');
    }
}
