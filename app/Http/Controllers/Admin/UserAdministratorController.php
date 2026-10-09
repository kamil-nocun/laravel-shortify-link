<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserAdministratorsRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class UserAdministratorController extends Controller
{
    /**
     * Replace the administrators assigned to a client.
     */
    public function update(UpdateUserAdministratorsRequest $request, User $user): RedirectResponse
    {
        $user->assignedAdmins()->sync($request->validated('administrators'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assigned administrators updated.')]);

        return back();
    }
}
