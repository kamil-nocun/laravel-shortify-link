<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class DomainDisableController extends Controller
{
    /**
     * Disable the domain; guarded by the admin middleware.
     */
    public function store(Domain $domain): RedirectResponse
    {
        Gate::authorize('manageDomains', $domain->user);
        $domain->forceFill(['disabled_by_admin_at' => now(), 'disabled_by' => auth()->id()])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Domain disabled.')]);

        return back();
    }

    /**
     * Enable the domain again.
     */
    public function destroy(Domain $domain): RedirectResponse
    {
        Gate::authorize('manageDomains', $domain->user);
        $domain->forceFill(['disabled_by_admin_at' => null, 'disabled_by' => null])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Domain enabled.')]);

        return back();
    }
}
