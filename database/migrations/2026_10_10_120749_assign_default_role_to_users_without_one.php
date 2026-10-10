<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Give users left without a role by the RBAC backfill (an unknown legacy role) the default role.
     */
    public function up(): void
    {
        $defaultRoleId = DB::table('roles')->where('is_default', true)->value('id');

        if ($defaultRoleId !== null) {
            DB::table('users')->whereNull('role_id')->update(['role_id' => $defaultRoleId]);
        }
    }

    /**
     * Intentionally a no-op: which users had no role is not recorded.
     */
    public function down(): void
    {
        //
    }
};
