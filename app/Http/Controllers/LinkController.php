<?php

namespace App\Http\Controllers;

use App\Http\Requests\Links\StoreLinkRequest;
use App\Http\Requests\Links\UpdateLinkRequest;
use App\Models\Link;
use App\Models\Package;
use App\Models\User;
use App\Support\LinkCodeGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LinkController extends Controller
{
    /**
     * List the current user's links.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('links/Index', [
            'links' => $request->user()->links()->with('domain')->withCount('clicks')->latest()->paginate(15),
            'packageNotice' => $this->packageNotice($request->user()),
            'quota' => ['used' => $request->user()->links()->count(), 'limit' => $request->user()->linkLimit()],
        ]);
    }

    /**
     * Show the form for creating a link.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Link::class);

        return Inertia::render('links/Create', ['domains' => $request->user()->domains()->active()->get(['id', 'host'])]);
    }

    /**
     * Store a new link with a generated code.
     */
    public function store(StoreLinkRequest $request, LinkCodeGenerator $generator): RedirectResponse
    {
        Gate::authorize('create', Link::class);

        $generator->createFor($request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Link created.')]);

        return to_route('links.index');
    }

    /**
     * Show the form for editing a link.
     */
    public function edit(Link $link): Response
    {
        Gate::authorize('update', $link);

        return Inertia::render('links/Edit', ['link' => $link]);
    }

    /**
     * Update a link; its code never changes.
     */
    public function update(UpdateLinkRequest $request, Link $link): RedirectResponse
    {
        $link->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Link updated.')]);

        return to_route('links.index');
    }

    /**
     * Delete a link.
     */
    public function destroy(Link $link): RedirectResponse
    {
        Gate::authorize('delete', $link);

        $link->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Link deleted.')]);

        return to_route('links.index');
    }

    /**
     * Notice for a user whose current package has been withdrawn.
     *
     * @return array{package_name: string, ends_at: string, default_package_name: string|null}|null
     */
    private function packageNotice(User $user): ?array
    {
        $assignment = $user->activeAssignment();

        return $assignment?->package->trashed()
            ? ['package_name' => $assignment->package->name, 'ends_at' => $assignment->expires_at->toIso8601String(), 'default_package_name' => Package::where('is_default', true)->value('name')]
            : null;
    }
}
