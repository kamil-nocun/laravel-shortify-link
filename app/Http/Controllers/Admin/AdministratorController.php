<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdministratorRequest;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AccountCreatedNotification;
use App\Support\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdministratorController extends Controller
{
    /**
     * List administrators, optionally filtered by name or e-mail.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('manageAdministrators', User::class);

        $search = (string) $request->query('search', '');
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';

        $administrators = User::query()
            ->administrators()
            ->with(['role.permissions', 'permissionOverrides'])
            ->when($search !== '', fn ($query) => $query->where(
                fn ($query) => $query->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("email LIKE ? ESCAPE '!'", [$pattern])
            ))
            ->with('blockedBy:id,name')
            ->latest()
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user) => [
                ...$user->only(['id', 'name', 'email', 'blocked_at', 'created_at']),
                'role' => $user->role?->name,
                'blocked_by_id' => $user->blocked_by,
                'blocked_by_name' => $user->blockedBy?->name,
                'can' => [
                    'block' => $request->user()->can('block', $user),
                    'update' => $request->user()->can('update', $user),
                ],
            ]);

        return Inertia::render('admin/administrators/Index', ['administrators' => $administrators, 'search' => $search]);
    }

    /**
     * Show the create form.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('manageAdministrators', User::class);

        $roles = Role::assignableBy($request->user(), true);

        return Inertia::render('admin/administrators/Create', [
            'roles' => $roles->map->only(['id', 'name'])->all(),
            'defaultRoleId' => $roles->firstWhere('name', PermissionCatalog::ADMINISTRATOR_ROLE)?->id,
        ]);
    }

    /**
     * Create an administrator with an unknown random password and e-mail them a link to set their own.
     */
    public function store(StoreAdministratorRequest $request): RedirectResponse
    {
        $user = new User($request->safe()->except(['administrators', 'role_id']));
        $user->role_id = $request->validated('role_id') ?? Role::where('name', PermissionCatalog::ADMINISTRATOR_ROLE)->value('id');
        $user->password = Str::password(32);
        $user->email_verified_at = now();
        $user->save();

        $user->notify(new AccountCreatedNotification(Password::createToken($user)));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Administrator created. A link to set the password was e-mailed.')]);

        return to_route('admin.administrators.index');
    }
}
