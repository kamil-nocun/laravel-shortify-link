<?php

namespace App\Http\Controllers;

use App\Models\Link;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class LinkToggleController extends Controller
{
    /**
     * Flip the link's is_active flag.
     */
    public function __invoke(Link $link): RedirectResponse
    {
        Gate::authorize('toggle', $link);

        $link->update(['is_active' => ! $link->is_active]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $link->is_active ? __('Link activated.') : __('Link deactivated.'),
        ]);

        return back();
    }
}
