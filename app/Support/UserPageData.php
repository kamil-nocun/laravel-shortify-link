<?php

namespace App\Support;

use App\Enums\BillingPeriod;
use App\Models\Domain;
use App\Models\Package;
use App\Models\Role;
use App\Models\User;

/**
 * Shapes users and their related data for the administration pages.
 */
class UserPageData
{
    /**
     * Data for the per-user permission overrides card; empty unless the actor may change the user's role.
     *
     * @return array{permissionOverrides: array{granted: list<string>, revoked: list<string>}, rolePermissions: list<string>, permissionGroups: list<array<string, mixed>>, roles: list<array{id: string, name: string}>}
     */
    public function permissionOverrideData(User $actor, User $user): array
    {
        if (! $actor->can('changeRole', $user)) {
            return ['permissionOverrides' => ['granted' => [], 'revoked' => []], 'rolePermissions' => [], 'permissionGroups' => [], 'roles' => []];
        }

        $overrides = $user->permissionOverrides()->get();

        return [
            'permissionOverrides' => [
                'granted' => $overrides->filter(fn ($permission) => $permission->pivot->granted)->pluck('key')->values()->all(),
                'revoked' => $overrides->reject(fn ($permission) => $permission->pivot->granted)->pluck('key')->values()->all(),
            ],
            'rolePermissions' => $user->role?->permissionKeys()->values()->all() ?? [],
            'permissionGroups' => PermissionCatalog::groups(),
            'roles' => Role::where('is_super', false)->orderBy('name')->get(['id', 'name'])->map->only(['id', 'name'])->all(),
        ];
    }

    /**
     * @return list<array{id: string, host: string, status: string, disabled_by_name: string|null, links_count: int}>
     */
    public function presentDomains(User $user): array
    {
        return $user->domains()->with('disabledBy:id,name')->withCount('links')->oldest()->get()
            ->map(fn (Domain $domain) => [
                'id' => $domain->id,
                'host' => $domain->host,
                'status' => $domain->status(),
                'disabled_by_name' => $domain->disabledBy?->name,
                'links_count' => $domain->links_count,
            ])->all();
    }

    /**
     * Whether the actor may assign administrators to the target, which must be a client.
     */
    public function isAssignable(User $actor, User $user): bool
    {
        return $actor->can('assignAdministrators', User::class) && $user->isClient();
    }

    /**
     * @return array{package_id: string, package_name: string, billing_period: string, price: int, starts_at: string, expires_at: string, active: bool, package_withdrawn: bool}|null
     */
    public function presentAssignment(User $user): ?array
    {
        $assignment = $user->packageAssignment()->with('package')->first();

        return $assignment ? [
            'package_id' => $assignment->package_id,
            'package_name' => $assignment->package->name,
            'billing_period' => $assignment->billing_period->value,
            'price' => $assignment->price,
            'starts_at' => $assignment->starts_at->toIso8601String(),
            'expires_at' => $assignment->expires_at->toIso8601String(),
            'active' => $assignment->isActive(),
            'package_withdrawn' => $assignment->package->trashed(),
        ] : null;
    }

    /**
     * Where the user's package stands: invoice unpaid, running, or ended.
     */
    public function paymentState(User $user): ?string
    {
        return match (true) {
            $user->subscriptions()->incomplete()->exists() => 'awaiting_payment',
            $user->activeAssignment() !== null => 'active',
            $user->packageAssignment()->exists() => 'expired',
            default => null,
        };
    }

    /**
     * Packages selectable for assignment, with the periods that have a price.
     *
     * @return list<array{id: string, name: string, link_limit: int, available_periods: list<string>}>
     */
    public function packageOptions(): array
    {
        return Package::where('is_default', false)->orderBy('name')->get()->filter(fn (Package $package) => array_filter($package->availablePeriods(), fn (BillingPeriod $period) => $package->stripePriceId($period) !== null) !== [])->map(fn (Package $package) => [
            'id' => $package->id,
            'name' => $package->name,
            'link_limit' => $package->link_limit,
            'available_periods' => array_values(array_map(fn (BillingPeriod $period) => $period->value, array_filter($package->availablePeriods(), fn (BillingPeriod $period) => $package->stripePriceId($period) !== null))),
        ])->values()->all();
    }

    /**
     * Administrators selectable for assignment; empty unless the actor is a super administrator.
     *
     * @return list<array{id: string, name: string, email: string}>
     */
    public function administratorOptions(User $actor): array
    {
        if (! $actor->isSuperAdmin()) {
            return [];
        }

        return User::query()->administrators()->orderBy('name')->get(['id', 'name', 'email'])
            ->map->only(['id', 'name', 'email'])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function present(User $user, User $actor): array
    {
        return [
            ...$user->only(['id', 'name', 'email', 'role_id', 'blocked_at', 'created_at', 'links_count']),
            'role' => $user->role?->name,
            'blocked_by_id' => $user->blocked_by,
            'blocked_by_name' => $user->blockedBy?->name,
            'can' => [
                'block' => $actor->can('block', $user),
                'update' => $actor->can('update', $user),
                'delete' => $actor->can('delete', $user),
                'createLink' => $actor->can('createLink', $user),
                'manageDomains' => $actor->can('manageDomains', $user),
                'createDomain' => $actor->can('createDomain', $user),
                'disableLinks' => $actor->can('disableLinks', $user),
                'disableDomains' => $actor->can('disableDomains', $user),
                'changeRole' => $actor->can('changeRole', $user),
                'assignAdministrators' => $this->isAssignable($actor, $user),
            ],
        ];
    }
}
