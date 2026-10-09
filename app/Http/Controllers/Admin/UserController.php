<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminStatsRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Link;
use App\Models\User;
use App\Notifications\AccountCreatedNotification;
use App\Support\AdminStatsReport;
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
    public function __construct(private readonly AdminStatsReport $report) {}

    /**
     * List regular users, optionally filtered by name or e-mail.
     */
    public function index(Request $request): Response
    {
        $search = (string) $request->query('search', '');
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';

        $users = User::query()
            ->visibleTo($request->user())
            ->where('role', UserRole::User)
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
            ->through(fn (User $user) => $this->present($user, $request->user()));

        return Inertia::render('admin/users/Index', ['users' => $users, 'search' => $search]);
    }

    /**
     * Show the create form.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('admin/users/Create', ['administrators' => $this->administratorOptions($request->user())]);
    }

    /**
     * Create a user with an unknown random password and e-mail them a link to set their own.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = new User($request->safe()->except('administrators'));
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
        return Inertia::render('admin/users/Show', [
            'user' => $this->present($user->loadCount('links')->load('blockedBy:id,name'), $request->user()),
            'assignedAdministrators' => $this->isAssignable($request->user(), $user)
                ? $user->assignedAdmins()->orderBy('name')->get(['users.id', 'users.name', 'users.email'])->map->only(['id', 'name', 'email'])->all()
                : [],
            'links' => $user->links()->withCount('clicks')->with('disabledBy:id,name')->latest()->paginate(15)->withQueryString()
                ->through(fn (Link $link) => [...$link->toArray(), 'disabled_by_name' => $link->disabledBy?->name]),
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
            'administrators' => $this->isAssignable($request->user(), $user) ? $this->administratorOptions($request->user()) : [],
            'assignedAdministratorIds' => $this->isAssignable($request->user(), $user)
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
     * Delete a user; their links and statistics cascade at the database level.
     */
    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deleted.')]);

        return to_route('admin.users.index');
    }

    /**
     * Whether the actor may assign administrators to the target, which must be a client.
     */
    private function isAssignable(User $actor, User $user): bool
    {
        return $actor->can('assignAdministrators', User::class) && $user->role === UserRole::User;
    }

    /**
     * Administrators selectable for assignment; empty unless the actor is a super administrator.
     *
     * @return list<array{id: string, name: string, email: string}>
     */
    private function administratorOptions(User $actor): array
    {
        if (! $actor->isSuperAdmin()) {
            return [];
        }

        return User::query()->where('role', UserRole::Admin)->orderBy('name')->get(['id', 'name', 'email'])
            ->map->only(['id', 'name', 'email'])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(User $user, User $actor): array
    {
        return [
            ...$user->only(['id', 'name', 'email', 'role', 'blocked_at', 'created_at', 'links_count']),
            'blocked_by_name' => $user->blockedBy?->name,
            'can' => [
                'block' => $actor->can('block', $user),
                'update' => $actor->can('update', $user),
                'delete' => $actor->can('delete', $user),
                'createLink' => $actor->can('createLink', $user),
                'changeRole' => $actor->can('changeRole', $user),
                'assignAdministrators' => $this->isAssignable($actor, $user),
            ],
        ];
    }
}
