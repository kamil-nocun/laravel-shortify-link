<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    /**
     * Reverse the migrations: restore the legacy column from each user's role kind.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('user');
        });

        $adminRoleIds = DB::table('roles')->where('is_super', false)
            ->whereExists(fn ($query) => $query->from('permission_role')
                ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
                ->whereColumn('permission_role.role_id', 'roles.id')
                ->where('permissions.key', 'admin.access'))
            ->pluck('id');

        DB::table('users')->whereIn('role_id', DB::table('roles')->where('is_super', true)->pluck('id'))->update(['role' => 'super_admin']);
        DB::table('users')->whereIn('role_id', $adminRoleIds)->update(['role' => 'admin']);
    }
};
