<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRoleRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class UserRoleController extends Controller
{
    /**
     * Change the user's role.
     */
    public function __invoke(UpdateUserRoleRequest $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user): void {
            $user->forceFill(['role' => $request->validated('role')])->save();
            $user->assignedAdmins()->detach();
            $user->managedClients()->detach();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return back();
    }
}
