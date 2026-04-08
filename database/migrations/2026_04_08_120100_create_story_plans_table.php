<?php

use App\Enums\FeatureTier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('story_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('tier', 20)->unique();
            $table->unsignedInteger('included_credits');
            $table->unsignedInteger('price_cents');
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->json('feature_list')->nullable();
            $table->timestamps();
        });

        $now = now();

        DB::table('story_plans')->insert([
            [
                'name' => 'Basic',
                'description' => 'Great for getting started with AI story creation.',
                'tier' => FeatureTier::Basic->value,
                'included_credits' => (int) config('jvzoo.tier_credits.basic', 30),
                'price_cents' => 0,
                'currency' => 'USD',
                'sort_order' => 10,
                'is_active' => true,
                'is_featured' => false,
                'feature_list' => json_encode([
                    'AI story writer',
                    'Page illustrations',
                    'Flipbook reader',
                ], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Pro',
                'description' => 'Best for creators publishing consistently.',
                'tier' => FeatureTier::Pro->value,
                'included_credits' => (int) config('jvzoo.tier_credits.pro', 150),
                'price_cents' => 2900,
                'currency' => 'USD',
                'sort_order' => 20,
                'is_active' => true,
                'is_featured' => true,
                'feature_list' => json_encode([
                    'Everything in Basic',
                    'Priority generation queues',
                    'Pro media workflow',
                ], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Elite',
                'description' => 'Maximum output for power users and agencies.',
                'tier' => FeatureTier::Elite->value,
                'included_credits' => (int) config('jvzoo.tier_credits.elite', 500),
                'price_cents' => 7900,
                'currency' => 'USD',
                'sort_order' => 30,
                'is_active' => true,
                'is_featured' => false,
                'feature_list' => json_encode([
                    'Everything in Pro',
                    'Highest usage limits',
                    'Fastest processing priority',
                ], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('story_plans');
    }
};
