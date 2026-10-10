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
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_invoice_id')->unique();
            $table->foreignUuid('package_id')->nullable()->constrained()->nullOnDelete();
            $table->string('billing_period')->nullable();
            $table->string('status');
            $table->unsignedInteger('amount');
            $table->string('currency', 3);
            $table->text('invoice_url')->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('stripe_event_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
