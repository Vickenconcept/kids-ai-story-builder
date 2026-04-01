<?php

namespace App\Services\Story;

use App\Exceptions\InsufficientStoryCreditsException;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class StoryCreditService
{
    public function cost(string $kind): int
    {
        return max(0, (int) config("story.credit_costs.$kind", 0));
    }

    /**
     * @return array{text:int,image:int,audio:int,video:int,total:int}
     */
    public function estimateForProject(
        int $pageCount,
        bool $includeImages,
        bool $includeNarration,
        bool $includeVideo,
        bool $includeText = true,
    ): array {
        $pages = max(0, $pageCount);

        $text = $includeText ? $this->cost('text') : 0;
        $image = $includeImages ? $pages * $this->cost('image') : 0;
        $audio = $includeNarration ? $pages * $this->cost('audio') : 0;
        $video = $includeVideo ? $pages * $this->cost('video') : 0;

        return [
            'text' => $text,
            'image' => $image,
            'audio' => $audio,
            'video' => $video,
            'total' => $text + $image + $audio + $video,
        ];
    }

    public function assertCanSpend(User $user, string $kind): void
    {
        $cost = $this->cost($kind);
        if ($cost <= 0) {
            return;
        }
        if ($user->story_credits < $cost) {
            throw InsufficientStoryCreditsException::for($kind);
        }
    }

    public function spend(User $user, string $kind): void
    {
        $cost = $this->cost($kind);
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
