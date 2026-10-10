<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        PermissionCatalog::sync();
    }

    public function down(): void
    {
        // Syncing the catalog is idempotent and the permission rows stay harmlessly unused after a rollback.
    }
};
