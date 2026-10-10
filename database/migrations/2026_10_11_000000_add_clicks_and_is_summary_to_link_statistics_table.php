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
            $table->unsignedInteger('clicks')->default(1)->after('source');
            $table->boolean('is_summary')->default(false)->after('clicks');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('link_statistics', function (Blueprint $table) {
            $table->dropColumn(['clicks', 'is_summary']);
        });
    }
};
