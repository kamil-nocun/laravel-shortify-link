<?php

namespace App\Http\Controllers;

use App\Http\Requests\Domains\StoreDomainRequest;
use App\Jobs\VerifyDomain;
use App\Models\Domain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DomainController extends Controller
{
    /**
     * List the current user's domains.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('domains/Index', [
            'domains' => $user->domains()->oldest()->get()->map(fn (Domain $domain) => [
                'id' => $domain->id,
                'host' => $domain->host,
                'status' => match (true) {
                    ! $domain->isVerified() => 'pending',
                    $domain->disabled_by_admin_at !== null => 'disabled',
                    default => 'active',
                },
                'verification_token' => $domain->verification_token,
            ]),
            'quota' => ['used' => $user->domains()->count(), 'limit' => $user->domainLimit()],
            'cnameTarget' => config('shortify.cname_target'),
        ]);
    }

    /**
     * Add a domain and start checking its DNS record.
     */
    public function store(StoreDomainRequest $request): RedirectResponse
    {
        $domain = $request->user()->domains()->create([
            'host' => $request->validated('host'),
            'verification_token' => Str::random(40),
        ]);

        VerifyDomain::dispatch($domain->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Domain added. Publish the DNS records to verify it.')]);

        return to_route('domains.index');
    }

    /**
     * Delete a domain no link uses.
     */
    public function destroy(Domain $domain): RedirectResponse
    {
        Gate::authorize('delete', $domain);

        $domain->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Domain deleted.')]);

        return to_route('domains.index');
    }
}
