<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The pages that were required before the flag existed keep being required.
     */
    public function up(): void
    {
        DB::table('pages')->whereIn('slug', ['terms', 'privacy-policy', 'cookies-policy'])->update(['is_legal_document' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
