<?php

use App\Models\PermissionRecord;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Give the default Administrator role the client permissions it had before RBAC.
     * Keys are literals on purpose, so the migration does not change with the enum.
     */
    public function up(): void
    {
        $role = Role::where('name', 'Administrator')->first();

        $role?->permissions()->syncWithoutDetaching(
            PermissionRecord::whereIn('key', ['links.manage', 'links.stats', 'domains.manage', 'billing.manage'])->pluck('id'),
        );
    }

    /**
     * Intentionally a no-op: role permissions are user-editable, so nothing is detached.
     */
    public function down(): void
    {
        //
    }
};
