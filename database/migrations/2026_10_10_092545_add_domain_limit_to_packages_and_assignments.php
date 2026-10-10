<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->unsignedInteger('domain_limit')->default(0)->after('link_limit');
        });

        Schema::table('package_assignments', function (Blueprint $table) {
            $table->unsignedInteger('domain_limit')->default(0)->after('link_limit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('package_assignments', function (Blueprint $table) {
            $table->dropColumn('domain_limit');
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('domain_limit');
        });
    }
};
