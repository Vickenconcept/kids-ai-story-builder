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
        Schema::create('plan_purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('story_plan_id')->nullable()->constrained('story_plans')->nullOnDelete();
            $table->string('plan_name');
            $table->string('tier', 20);
            $table->unsignedInteger('credits_floor');
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
        Schema::dropIfExists('plan_purchases');
    }
};
