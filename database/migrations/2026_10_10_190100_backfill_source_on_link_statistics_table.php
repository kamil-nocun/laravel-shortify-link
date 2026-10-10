<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Clicks recorded before QR codes existed could only come through the plain address.
     */
    public function up(): void
    {
        DB::table('link_statistics')->whereNull('source')->update(['source' => 'Link']);
    }

    /**
     * The backfilled rows cannot be told apart from recorded ones, so nothing is reverted.
     */
    public function down(): void
    {
        //
    }
};
