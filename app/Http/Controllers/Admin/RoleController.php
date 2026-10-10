<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Models\PermissionRecord;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    /**
     * List roles with the number of permissions and users of each.
     */
    public function index(): Response
    {
        Gate::authorize('viewAny', Role::class);

        return Inertia::render('admin/roles/Index', [
            'roles' => Role::withCount(['permissions', 'users'])
                ->orderByDesc('is_super')->orderBy('name')->get()
                ->map(fn (Role $role) => $role->only(['id', 'name', 'description', 'is_super', 'is_default', 'permissions_count', 'users_count'])),
        ]);
    }

    /**
     * Show the create form.
     */
    public function create(): Response
    {
        Gate::authorize('create', Role::class);

        return Inertia::render('admin/roles/Create', ['permissionGroups' => PermissionCatalog::groups()]);
    }

    /**
     * Store a role with its permissions.
     */
    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $this->save(new Role, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created.')]);

        return to_route('admin.roles.index');
    }

    /**
     * Show the edit form; the super role is shown read-only to anyone who may list roles.
     */
    public function edit(Role $role): Response
    {
        $role->is_super ? Gate::authorize('viewAny', Role::class) : Gate::authorize('update', $role);

        return Inertia::render('admin/roles/Edit', [
            'role' => [...$role->only(['id', 'name', 'description', 'is_super', 'is_default']), 'permissions' => $role->permissions()->pluck('key')->all()],
            'permissionGroups' => PermissionCatalog::groups(),
        ]);
    }

    /**
     * Update a role and replace its permissions.
     */
    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $this->save($role, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return to_route('admin.roles.index');
    }

    /**
     * Delete an unused role; the restricting foreign key backs the policy up against a concurrent assignment.
     */
    public function destroy(Role $role): RedirectResponse
    {
        Gate::authorize('delete', $role);

        try {
            $role->delete();
        } catch (QueryException) {
            abort(403, __('A role held by users cannot be deleted.')); // a user took the role after the policy check
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return to_route('admin.roles.index');
    }

    /**
     * @param  array{name: string, description?: string|null, permissions: list<string>}  $attributes
     */
    private function save(Role $role, array $attributes): void
    {
        DB::transaction(function () use ($role, $attributes) {
            $role->fill($attributes)->save();
            $role->permissions()->sync(PermissionRecord::whereIn('key', $attributes['permissions'])->pluck('id'));
        });
    }
}
