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
        Schema::table('link_statistics', function (Blueprint $table) {
            $table->index(['clicked_at', 'is_summary']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('link_statistics', function (Blueprint $table) {
            $table->dropIndex(['clicked_at', 'is_summary']);
        });
    }
};
