<?php

namespace Tests\Feature\Api;

use App\Enums\FeatureTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JvzooIpnHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_jvzoo_event_is_ignored(): void
    {
        config([
            'jvzoo.secret' => 'secret-1',
            'jvzoo.max_event_age_minutes' => 30,
            'jvzoo.product_tiers' => ['P1' => 'pro'],
        ]);

        $date = now()->subHours(3)->toDateTimeString();
        $payload = $this->payload('SALE', 'COMPLETED', 'TX-1', 'P1', 'user@example.com', $date);

        $this->post(route('api.ipn.jvzoo'), $payload)
            ->assertOk()
            ->assertSeeText('STALE EVENT');

        $this->assertDatabaseCount('jvzoo_ipn_events', 0);
    }

    public function test_duplicate_jvzoo_event_is_deduped(): void
    {
        config([
            'jvzoo.secret' => 'secret-1',
            'jvzoo.max_event_age_minutes' => 120,
            'jvzoo.product_tiers' => ['P1' => 'pro'],
        ]);

        $date = now()->subMinutes(5)->toDateTimeString();
        $payload = $this->payload('SALE', 'COMPLETED', 'TX-2', 'P1', 'dup@example.com', $date);

        $this->post(route('api.ipn.jvzoo'), $payload)
            ->assertOk()
            ->assertSeeText('OK');

        $this->post(route('api.ipn.jvzoo'), $payload)
            ->assertOk()
            ->assertSeeText('DUPLICATE');

        $this->assertDatabaseCount('jvzoo_ipn_events', 1);
        $this->assertDatabaseHas('jvzoo_ipn_events', [
            'transaction_id' => 'TX-2',
            'customer_email' => 'dup@example.com',
            'is_duplicate' => 1,
        ]);
    }

    public function test_reversal_does_not_downgrade_when_auto_downgrade_is_disabled(): void
    {
        config([
            'jvzoo.secret' => 'secret-1',
            'jvzoo.auto_downgrade_on_reversal' => false,
            'jvzoo.max_event_age_minutes' => 120,
            'jvzoo.product_tiers' => ['P1' => 'pro'],
        ]);

        $user = User::factory()->create([
            'email' => 'safe@example.com',
            'feature_tier' => FeatureTier::Pro,
            'story_credits' => 400,
        ]);

        $date = now()->subMinutes(2)->toDateTimeString();
        $payload = $this->payload('RFND', 'COMPLETED', 'TX-3', 'P1', 'safe@example.com', $date);

        $this->post(route('api.ipn.jvzoo'), $payload)
            ->assertOk()
            ->assertSeeText('OK');

        $user->refresh();
        $this->assertSame(FeatureTier::Pro, $user->feature_tier);
        $this->assertSame(400, (int) $user->story_credits);
    }

    private function payload(
        string $transactionType,
        string $status,
        string $transactionId,
        string $productId,
        string $email,
        string $date,
    ): array {
        $secret = (string) config('jvzoo.secret');
        $paykey = 'PK-123';
        $productName = 'Plan '.$productId;

        $signatureBase = $paykey
            .'|'.$email
            .'|'.$productName
            .'|'.$transactionType
            .'|'.$date
            .$secret;

        $cverify = strtoupper(substr(sha1(mb_convert_encoding($signatureBase, 'UTF-8')), 0, 8));

        return [
            'transaction_type' => $transactionType,
            'status' => $status,
            'transaction_id' => $transactionId,
            'product_id' => $productId,
            'product_name' => $productName,
            'customer_email' => $email,
            'paykey' => $paykey,
            'date' => $date,
            'cverify' => $cverify,
        ];
    }
}
