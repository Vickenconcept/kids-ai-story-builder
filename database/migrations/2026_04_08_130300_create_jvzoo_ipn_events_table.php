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
        Schema::create('jvzoo_ipn_events', function (Blueprint $table): void {
            $table->id();
            $table->string('fingerprint', 64)->unique();
            $table->string('transaction_id')->nullable()->index();
            $table->string('paykey')->nullable()->index();
            $table->string('customer_email')->nullable()->index();
            $table->string('product_id')->nullable()->index();
            $table->string('transaction_type', 20)->nullable();
            $table->string('status', 30)->nullable();
            $table->timestamp('event_at')->nullable();
            $table->boolean('is_duplicate')->default(false);
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jvzoo_ipn_events');
    }
};
