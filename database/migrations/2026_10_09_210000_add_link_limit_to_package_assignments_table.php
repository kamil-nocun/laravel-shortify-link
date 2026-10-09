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
        Schema::table('package_assignments', function (Blueprint $table) {
            $table->unsignedInteger('link_limit')->default(0)->after('price');
        });

        DB::statement('UPDATE package_assignments SET link_limit = (SELECT link_limit FROM packages WHERE packages.id = package_assignments.package_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('package_assignments', function (Blueprint $table) {
            $table->dropColumn('link_limit');
        });
    }
};
