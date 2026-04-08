<?php

namespace Tests\Unit;

use App\Exceptions\InsufficientStoryCreditsException;
use App\Models\User;
use App\Services\Story\StoryCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoryCreditServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_spend_is_atomic_and_throws_when_balance_is_insufficient(): void
    {
        config(['story.credit_costs.image' => 4]);

        $user = User::factory()->create(['story_credits' => 5]);
        $service = app(StoryCreditService::class);

        $service->spend($user, 'image');

        $this->assertSame(1, (int) $user->fresh()->story_credits);

        $this->expectException(InsufficientStoryCreditsException::class);
        $service->spend($user->fresh(), 'image');
    }

    public function test_spend_once_is_durable_and_idempotent(): void
    {
        config(['story.credit_costs.audio' => 3]);

        $user = User::factory()->create(['story_credits' => 10]);
        $service = app(StoryCreditService::class);

        $service->spendOnce('audio:page:123', $user, 'audio');
        $service->spendOnce('audio:page:123', $user->fresh(), 'audio');

        $this->assertSame(7, (int) $user->fresh()->story_credits);
        $this->assertDatabaseCount('story_credit_spend_events', 1);
        $this->assertDatabaseHas('story_credit_spend_events', [
            'idempotency_key' => 'audio:page:123',
            'kind' => 'audio',
            'cost' => 3,
        ]);
    }
}
