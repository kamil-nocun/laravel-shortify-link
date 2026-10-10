<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['packages', 'package_assignments'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->boolean('allows_custom_alias')->default(false);
            });
        }
    }

    public function down(): void
    {
        foreach (['packages', 'package_assignments'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('allows_custom_alias');
            });
        }
    }
};
