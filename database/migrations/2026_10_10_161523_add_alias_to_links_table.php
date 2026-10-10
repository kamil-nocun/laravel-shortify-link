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
        Schema::table('links', function (Blueprint $table) {
            $table->string('alias', 50)->nullable()->collation('utf8mb4_bin')->after('code');
        });

        DB::statement('ALTER TABLE links ADD UNIQUE links_domain_key_alias_unique (domain_key, alias)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->dropUnique('links_domain_key_alias_unique');
            $table->dropColumn('alias');
        });
    }
};
