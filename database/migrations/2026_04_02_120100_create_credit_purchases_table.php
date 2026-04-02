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
        Schema::create('credit_purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('credit_pack_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pack_name');
            $table->unsignedInteger('credits_awarded');
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3);
            $table->string('provider', 50)->default('paypal');
            $table->string('provider_order_id')->unique();
            $table->string('provider_capture_id')->nullable()->unique();
            $table->string('status', 30);
            $table->json('raw_payload')->nullable();
            $table->timestamp('purchased_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_purchases');
    }
};
