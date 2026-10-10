<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Clicks recorded before aliases existed could only come through the code.
     */
    public function up(): void
    {
        DB::table('link_statistics')->whereNull('entry')->update(['entry' => 'Code']);
    }

    /**
     * The backfilled rows cannot be told apart from recorded ones, so nothing is reverted.
     */
    public function down(): void
    {
        //
    }
};
