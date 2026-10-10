<?php

namespace App\Http\Controllers\Admin;

use App\Actions\DeleteUserAccount;
use App\Billing\PackagePageData;
use App\Enums\Permission;
use App\Exceptions\AccountDeletionFailed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminStatsRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Link;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AccountCreatedNotification;
use App\Support\AdminStatsReport;
use App\Support\UserPageData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private readonly AdminStatsReport $report, private readonly PackagePageData $packagePageData, private readonly UserPageData $page) {}

    /**
     * List regular users, optionally filtered by name or e-mail.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $search = (string) $request->query('search', '');
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';

        $users = User::query()
            ->visibleTo($request->user())
            ->clients()
            ->with(['role.permissions', 'permissionOverrides'])
            ->withCount('links')
            ->with('blockedBy:id,name')
            ->with(['assignedAdmins' => fn ($query) => $query->whereKey($request->user()->id)])
            ->when($search !== '', fn ($query) => $query->where(
                fn ($query) => $query->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("email LIKE ? ESCAPE '!'", [$pattern])
            ))
            ->latest()
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user) => $this->page->present($user, $request->user()));

        return Inertia::render('admin/users/Index', ['users' => $users, 'search' => $search]);
    }

    /**
     * Show the create form.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', User::class);

        $roles = $request->user()->hasPermission(Permission::UsersChangeRole) ? Role::assignableBy($request->user(), false) : collect();

        return Inertia::render('admin/users/Create', [
            'administrators' => $this->page->administratorOptions($request->user()),
            'roles' => $roles->map->only(['id', 'name'])->all(),
            'defaultRoleId' => $roles->firstWhere('is_default', true)?->id,
        ]);
    }

    /**
     * Create a user with an unknown random password and e-mail them a link to set their own.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = new User($request->safe()->except(['administrators', 'role_id']));
        $user->role_id = $request->validated('role_id');
        $user->password = Str::password(32);
        $user->email_verified_at = now();
        DB::transaction(function () use ($user, $request): void {
            $user->save();

            if ($request->user()->isSuperAdmin()) {
                $user->assignedAdmins()->sync($request->validated('administrators', []));
            } else {
                $user->assignedAdmins()->attach($request->user());
            }
        });

        $user->notify(new AccountCreatedNotification(Password::createToken($user)));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created. A link to set the password was e-mailed.')]);

        return to_route('admin.users.index');
    }

    /**
     * Show a user with their links and click statistics.
     */
    public function show(AdminStatsRequest $request, User $user): Response
    {
        Gate::authorize('view', $user);

        $actor = $request->user();
        $manages = $actor->can('manages', $user);
        $assignsPackage = $actor->can('assignPackage', $user);

        return Inertia::render('admin/users/Show', [
            'user' => $this->page->present($user->loadCount('links')->load('blockedBy:id,name'), $request->user()),
            'assignedAdministrators' => $this->page->isAssignable($request->user(), $user)
                ? $user->assignedAdmins()->orderBy('name')->get(['users.id', 'users.name', 'users.email'])->map->only(['id', 'name', 'email'])->all()
                : [],
            'effectiveLimit' => $user->linkLimit(),
            'paymentState' => $assignsPackage ? $this->page->paymentState($user) : null,
            'invoiceUrl' => $manages ? $this->packagePageData->invoiceUrlFor($user) : null,
            'payments' => $manages ? $this->packagePageData->paymentsFor($user) : null,
            'assignments' => $manages ? $this->packagePageData->assignmentsFor($user) : null,
            'packageAssignment' => $assignsPackage ? $this->page->presentAssignment($user) : null,
            'packages' => $assignsPackage ? $this->page->packageOptions() : [],
            'domains' => $manages ? $this->page->presentDomains($user) : [],
            'domainLimit' => $user->domainLimit(),
            'links' => $user->links()->withClickTotal()->with(['domain', 'disabledBy:id,name'])->latest()->paginate(15)->withQueryString()
                ->through(fn (Link $link) => [...$link->toArray(), 'disabled_by_name' => $link->disabledBy?->name]),
            ...$this->page->permissionOverrideData($request->user(), $user),
            ...$this->report->build($user, $request),
        ]);
    }

    /**
     * Show the profile edit form.
     */
    public function edit(Request $request, User $user): Response
    {
        Gate::authorize('update', $user);

        return Inertia::render('admin/users/Edit', [
            'user' => $user->only(['id', 'name', 'email']),
            'administrators' => $this->page->isAssignable($request->user(), $user) ? $this->page->administratorOptions($request->user()) : [],
            'assignedAdministratorIds' => $this->page->isAssignable($request->user(), $user)
                ? $user->assignedAdmins()->pluck('users.id')->all()
                : [],
        ]);
    }

    /**
     * Update a user's profile; the password changes only when provided.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->fill($request->safe()->only(['name', 'email']));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if ($request->filled('password')) {
            $user->password = $request->validated('password');
        }

        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return to_route('admin.users.show', $user);
    }

    /**
     * Delete a user together with their Stripe customer; their links and statistics cascade at the database level.
     */
    public function destroy(User $user, DeleteUserAccount $deleteUserAccount): RedirectResponse
    {
        Gate::authorize('delete', $user);

        try {
            $deleteUserAccount($user);
        } catch (AccountDeletionFailed $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->userMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deleted.')]);

        return to_route('admin.users.index');
    }
}
