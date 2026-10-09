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
        Schema::table('packages', function (Blueprint $table) {
            $table->string('stripe_product_id')->nullable();
            $table->string('stripe_price_id_one_time')->nullable();
            $table->string('stripe_price_id_monthly')->nullable();
            $table->string('stripe_price_id_quarterly')->nullable();
            $table->string('stripe_price_id_yearly')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['stripe_product_id', 'stripe_price_id_one_time', 'stripe_price_id_monthly', 'stripe_price_id_quarterly', 'stripe_price_id_yearly']);
        });
    }
};
