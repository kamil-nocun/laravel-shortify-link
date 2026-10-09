<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->unsignedInteger('link_limit');
            $table->unsignedInteger('price_one_time')->nullable();
            $table->unsignedInteger('price_monthly')->nullable();
            $table->unsignedInteger('price_quarterly')->nullable();
            $table->unsignedInteger('price_yearly')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        DB::table('packages')->insert([
            'id' => (string) Str::uuid(),
            'name' => 'Free',
            'link_limit' => 10,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
