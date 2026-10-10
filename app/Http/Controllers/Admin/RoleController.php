<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Models\PermissionRecord;
use App\Models\Role;
use App\Support\PermissionCatalog;
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
     * Show the edit form.
     */
    public function edit(Role $role): Response
    {
        Gate::authorize('update', $role);

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
     * Delete an unused role.
     */
    public function destroy(Role $role): RedirectResponse
    {
        Gate::authorize('delete', $role);

        $role->delete();

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
