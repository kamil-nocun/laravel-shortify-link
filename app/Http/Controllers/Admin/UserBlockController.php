<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class UserBlockController extends Controller
{
    /**
     * Block the user's account.
     */
    public function store(User $user): RedirectResponse
    {
        Gate::authorize('block', $user);

        $user->forceFill(['blocked_at' => now(), 'blocked_by' => auth()->id()])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User blocked.')]);

        return back();
    }

    /**
     * Unblock the user's account.
     */
    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('block', $user);

        $user->forceFill(['blocked_at' => null, 'blocked_by' => null])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User unblocked.')]);

        return back();
    }
}
