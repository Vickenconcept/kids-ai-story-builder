<?php

namespace App\Services\Story;

use App\Exceptions\InsufficientStoryCreditsException;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class StoryCreditService
{
    public function assertCanSpend(User $user, string $kind): void
    {
        $cost = (int) config("story.credit_costs.$kind", 0);
        if ($cost <= 0) {
            return;
        }
        if ($user->story_credits < $cost) {
            throw InsufficientStoryCreditsException::for($kind);
        }
    }

    public function spend(User $user, string $kind): void
    {
        $cost = (int) config("story.credit_costs.$kind", 0);
        if ($cost <= 0) {
            return;
        }
        $this->assertCanSpend($user, $kind);
        $user->decrement('story_credits', $cost);
    }

    /**
     * Charge once per idempotency key (queue retries must not double-bill).
     */
    public function spendOnce(string $idempotencyKey, User $user, string $kind): void
    {
        $cacheKey = 'story:credits:'.hash('sha256', $idempotencyKey);
        if (! Cache::add($cacheKey, 1, now()->addDays(14))) {
            return;
        }

        try {
            $this->spend($user, $kind);
        } catch (\Throwable $e) {
            Cache::forget($cacheKey);

            throw $e;
        }
    }
}
