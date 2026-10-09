<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePackageRequest;
use App\Http\Requests\Admin\UpdatePackageRequest;
use App\Models\Package;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PackageController extends Controller
{
    /**
     * List packages with the number of users assigned to each.
     */
    public function index(): Response
    {
        Gate::authorize('viewAny', Package::class);

        return Inertia::render('admin/packages/Index', [
            'packages' => Package::withCount(['assignments' => fn ($query) => $query->where('expires_at', '>', now())])
                ->orderByDesc('is_default')->orderBy('name')->get(),
            'defaultPackageName' => Package::where('is_default', true)->value('name'),
        ]);
    }

    /**
     * Show the create form.
     */
    public function create(): Response
    {
        Gate::authorize('create', Package::class);

        return Inertia::render('admin/packages/Create');
    }

    /**
     * Store a package.
     */
    public function store(StorePackageRequest $request): RedirectResponse
    {
        $this->save(new Package, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Package created.')]);

        return to_route('admin.packages.index');
    }

    /**
     * Show the edit form.
     */
    public function edit(Package $package): Response
    {
        Gate::authorize('update', $package);

        return Inertia::render('admin/packages/Edit', ['package' => $package]);
    }

    /**
     * Update a package.
     */
    public function update(UpdatePackageRequest $request, Package $package): RedirectResponse
    {
        $this->save($package, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Package updated.')]);

        return to_route('admin.packages.index');
    }

    /**
     * Delete a package without running assignments; withdraw one that has them so current periods run out; never touch the default.
     */
    public function destroy(Package $package): RedirectResponse
    {
        Gate::authorize('delete', $package);

        if ($package->is_default) {
            throw ValidationException::withMessages(['package' => __('The default package cannot be deleted.')]);
        }

        $withdrawn = DB::transaction(function () use ($package) {
            Package::whereKey($package->id)->lockForUpdate()->first();
            $package->assignments()->where('expires_at', '<=', now())->delete();

            if ($package->assignments()->exists()) {
                $package->delete();

                return true;
            }

            $package->forceDelete();

            return false;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => $withdrawn ? __('Package withdrawn.') : __('Package deleted.')]);

        return to_route('admin.packages.index');
    }

    /**
     * Save the package, keeping at most one default.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function save(Package $package, array $attributes): void
    {
        DB::transaction(function () use ($package, $attributes) {
            if ($attributes['is_default'] ?? false) {
                Package::where('is_default', true)->lockForUpdate()->get()->each->update(['is_default' => false]);
            }

            $package->fill($attributes)->save();
        });
    }
}
