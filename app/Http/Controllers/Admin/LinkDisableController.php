<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Link;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class LinkDisableController extends Controller
{
    /**
     * Disable the link; guarded by the admin middleware.
     */
    public function store(Link $link): RedirectResponse
    {
        $link->forceFill(['disabled_by_admin_at' => now()])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Link disabled.')]);

        return back();
    }

    /**
     * Enable the link again.
     */
    public function destroy(Link $link): RedirectResponse
    {
        $link->forceFill(['disabled_by_admin_at' => null])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Link enabled.')]);

        return back();
    }
}
