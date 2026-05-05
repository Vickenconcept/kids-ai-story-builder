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
        Schema::create('affiliate_tracking_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_partner_id')->constrained('affiliate_partners')->cascadeOnDelete();
            $table->string('event_type', 20)->index(); // click, optin, sale
            $table->string('visitor_token', 64)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('landing_url')->nullable();
            $table->text('referrer')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('utm_term')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->timestamps();

            $table->index(['affiliate_partner_id', 'event_type']);
            $table->index(['affiliate_partner_id', 'email', 'event_type'], 'affiliate_events_partner_email_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliate_tracking_events');
    }
};
