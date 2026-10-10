<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CreateLink;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserLinkRequest;
use App\Models\Link;
use App\Models\User;
use App\Support\MainHost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserLinkController extends Controller
{
    /**
     * Show the form for creating a link owned by the user.
     */
    public function create(User $user): Response
    {
        Gate::authorize('createLink', $user);

        return Inertia::render('admin/users/links/Create', ['user' => $user->only(['id', 'name']), 'domains' => $user->domains()->active()->get(['id', 'host']), 'canSetAlias' => $user->allowsCustomAlias(), 'mainHost' => MainHost::withPort()]);
    }

    /**
     * Store a new link with a generated code, owned by the user.
     */
    public function store(StoreUserLinkRequest $request, User $user, CreateLink $createLink): RedirectResponse
    {
        $createLink($user, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Link created.')]);

        return to_route('admin.users.show', $user);
    }
}
