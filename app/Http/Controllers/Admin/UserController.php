<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminStatsRequest;
use App\Models\User;
use App\Support\AdminStatsReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private readonly AdminStatsReport $report) {}

    /**
     * List users, optionally filtered by name or e-mail.
     */
    public function index(Request $request): Response
    {
        $search = (string) $request->query('search', '');
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';

        $users = User::query()
            ->withCount('links')
            ->when($search !== '', fn ($query) => $query->where(
                fn ($query) => $query->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("email LIKE ? ESCAPE '!'", [$pattern])
            ))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user) => $this->present($user, $request->user()));

        return Inertia::render('admin/users/Index', ['users' => $users, 'search' => $search]);
    }

    /**
     * Show a user with their links and click statistics.
     */
    public function show(AdminStatsRequest $request, User $user): Response
    {
        return Inertia::render('admin/users/Show', [
            'user' => $this->present($user->loadCount('links'), $request->user()),
            'links' => $user->links()->withCount('clicks')->latest()->paginate(15),
            ...$this->report->build($user, $request),
        ]);
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
                'delete' => $actor->can('delete', $user),
                'changeRole' => $actor->can('changeRole', $user),
            ],
        ];
    }
}
