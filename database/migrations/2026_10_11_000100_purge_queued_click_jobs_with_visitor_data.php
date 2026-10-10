<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Click jobs queued before the visitor's IP and User-Agent were dropped from the payload still carry them
     * and cannot run with the new job shape, so pending and failed ones are removed (unrecorded clicks are lost).
     */
    public function up(): void
    {
        foreach (['jobs', 'failed_jobs'] as $table) {
            DB::table($table)->where('payload', 'like', '%RecordLinkClick%')->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
