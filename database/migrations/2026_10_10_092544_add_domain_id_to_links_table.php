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
            $table->foreignUuid('domain_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
            $table->dropUnique('links_code_unique');
        });

        DB::statement("ALTER TABLE links ADD domain_key CHAR(36) AS (IFNULL(domain_id, '')) VIRTUAL");
        DB::statement('ALTER TABLE links ADD UNIQUE links_domain_key_code_unique (domain_key, code)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->dropUnique('links_domain_key_code_unique');
            $table->dropColumn('domain_key');
            $table->dropConstrainedForeignId('domain_id');
            $table->unique('code');
        });
    }
};
