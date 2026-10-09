<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('link_clicks', 'link_statistics');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('link_statistics', 'link_clicks');
    }
};
