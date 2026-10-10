<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserDomainRequest;
use App\Jobs\VerifyDomain;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;

class UserDomainController extends Controller
{
    /**
     * Add a domain owned by the user and start checking its DNS record.
     */
    public function store(StoreUserDomainRequest $request, User $user): RedirectResponse
    {
        $domain = $user->domains()->create([
            'host' => $request->validated('host'),
            'verification_token' => Str::random(40),
        ]);

        VerifyDomain::dispatch($domain->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Domain added.')]);

        return back();
    }

    /**
     * Delete a domain no link uses.
     */
    public function destroy(Domain $domain): RedirectResponse
    {
        Gate::authorize('manageDomains', $domain->user);
        abort_if($domain->links()->exists(), 403, __('The domain is used by links.'));

        $domain->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Domain deleted.')]);

        return back();
    }
}
