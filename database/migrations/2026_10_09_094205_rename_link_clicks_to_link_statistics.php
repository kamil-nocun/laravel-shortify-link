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
        Schema::table('link_clicks', function (Blueprint $table) {
            $table->dropForeign('link_clicks_link_id_foreign');
        });

        Schema::rename('link_clicks', 'link_statistics');

        Schema::table('link_statistics', function (Blueprint $table) {
            $table->renameIndex('link_clicks_link_id_clicked_at_index', 'link_statistics_link_id_clicked_at_index');
            $table->foreign('link_id', 'link_statistics_link_id_foreign')->references('id')->on('links')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('link_statistics', function (Blueprint $table) {
            $table->dropForeign('link_statistics_link_id_foreign');
            $table->renameIndex('link_statistics_link_id_clicked_at_index', 'link_clicks_link_id_clicked_at_index');
        });

        Schema::rename('link_statistics', 'link_clicks');

        Schema::table('link_clicks', function (Blueprint $table) {
            $table->foreign('link_id', 'link_clicks_link_id_foreign')->references('id')->on('links')->cascadeOnDelete();
        });
    }
};
