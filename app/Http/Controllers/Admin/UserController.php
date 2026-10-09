<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminStatsRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Notifications\AccountCreatedNotification;
use App\Support\AdminStatsReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    public function create(): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('admin/users/Create');
    }

    /**
     * Create a user with an unknown random password and e-mail them a link to set their own.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = new User($request->validated());
        $user->password = Str::password(32);
        $user->email_verified_at = now();
        $user->save();

        $user->notify(new AccountCreatedNotification(Password::createToken($user)));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created. A link to set the password was e-mailed.')]);

        return to_route('admin.users.show', $user);
    }

    /**
     * Show a user with their links and click statistics.
     */
    public function show(AdminStatsRequest $request, User $user): Response
    {
        return Inertia::render('admin/users/Show', [
            'user' => $this->present($user->loadCount('links'), $request->user()),
            'links' => $user->links()->withCount('clicks')->latest()->paginate(15)->withQueryString(),
            ...$this->report->build($user, $request),
        ]);
    }

    /**
     * Show the profile edit form.
     */
    public function edit(User $user): Response
    {
        Gate::authorize('update', $user);

        return Inertia::render('admin/users/Edit', ['user' => $user->only(['id', 'name', 'email'])]);
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
     * @return array<string, mixed>
     */
    private function present(User $user, User $actor): array
    {
        return [
            ...$user->only(['id', 'name', 'email', 'role', 'blocked_at', 'created_at', 'links_count']),
            'can' => [
                'block' => $actor->can('block', $user),
                'update' => $actor->can('update', $user),
                'delete' => $actor->can('delete', $user),
                'createLink' => $actor->can('createLink', $user),
                'changeRole' => $actor->can('changeRole', $user),
            ],
        ];
    }
}
