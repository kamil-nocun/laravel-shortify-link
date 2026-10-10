<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserPermissionsRequest;
use App\Models\PermissionRecord;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class UserPermissionController extends Controller
{
    /**
     * Replace all of the user's permission overrides.
     */
    public function update(UpdateUserPermissionsRequest $request, User $user): RedirectResponse
    {
        $ids = PermissionRecord::pluck('id', 'key');
        $overrides = collect($request->validated('granted'))->mapWithKeys(fn (string $key) => [$ids[$key] => ['granted' => true]])
            ->union(collect($request->validated('revoked'))->mapWithKeys(fn (string $key) => [$ids[$key] => ['granted' => false]]));

        DB::transaction(fn () => $user->permissionOverrides()->sync($overrides->all()));
        $user->unsetRelation('permissionOverrides')->forgetPermissions();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Permissions updated.')]);

        return back();
    }
}
