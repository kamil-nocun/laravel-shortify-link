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
            $table->string('source', 4)->nullable()->after('entry');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('link_statistics', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
