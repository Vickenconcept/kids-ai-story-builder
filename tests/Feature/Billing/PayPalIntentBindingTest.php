<?php

namespace Tests\Feature\Billing;

use App\Models\CreditPack;
use App\Models\StoryPlan;
use App\Models\User;
use App\Services\Billing\PayPalClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class PayPalIntentBindingTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_capture_requires_matching_checkout_intent(): void
    {
        $user = User::factory()->create();

        $pack = CreditPack::query()->create([
            'name' => 'Starter',
            'description' => 'Pack',
            'credits' => 100,
            'price_cents' => 1200,
            'currency' => 'USD',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->postJson(route('credits.paypal.capture'), [
                'pack_id' => $pack->id,
                'order_id' => 'ORDER-NOT-BOUND',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('paypal');
    }

    public function test_plan_capture_requires_matching_checkout_intent(): void
    {
        $user = User::factory()->create(['feature_tier' => 'basic']);

        $plan = StoryPlan::query()->where('tier', 'pro')->first();
        if (! $plan) {
            $plan = StoryPlan::query()->create([
                'name' => 'Pro',
                'description' => 'Pro plan',
                'tier' => 'pro',
                'included_credits' => 150,
                'price_cents' => 2900,
                'currency' => 'USD',
                'sort_order' => 20,
                'is_active' => true,
                'is_featured' => true,
                'feature_list' => ['Everything in Basic'],
            ]);
        }

        $this->actingAs($user)
            ->postJson(route('plans.paypal.capture'), [
                'plan_id' => $plan->id,
                'order_id' => 'ORDER-NOT-BOUND',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('paypal');
    }

    public function test_create_credit_order_persists_checkout_intent(): void
    {
        $user = User::factory()->create();

        $pack = CreditPack::query()->create([
            'name' => 'Starter',
            'description' => 'Pack',
            'credits' => 100,
            'price_cents' => 1200,
            'currency' => 'USD',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->mock(PayPalClient::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createOrder')->once()->andReturn(['id' => 'ORDER-ABC-123']);
        });

        $this->actingAs($user)
            ->postJson(route('credits.paypal.order'), [
                'pack_id' => $pack->id,
            ])
            ->assertOk()
            ->assertJsonPath('id', 'ORDER-ABC-123');

        $this->assertDatabaseHas('paypal_checkout_intents', [
            'provider_order_id' => 'ORDER-ABC-123',
            'domain' => 'credits',
            'target_id' => $pack->id,
            'user_id' => $user->id,
            'amount_cents' => 1200,
            'currency' => 'USD',
        ]);
    }
}
