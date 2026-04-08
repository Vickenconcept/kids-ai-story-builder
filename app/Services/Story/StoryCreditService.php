<?php

namespace App\Services\Story;

use App\Exceptions\InsufficientStoryCreditsException;
use App\Models\StoryCreditSpendEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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

        $updated = User::query()
            ->whereKey($user->id)
            ->where('story_credits', '>=', $cost)
            ->decrement('story_credits', $cost);

        if ($updated === 0) {
            throw InsufficientStoryCreditsException::for($kind);
        }
    }

    /**
     * Charge once per idempotency key (queue retries must not double-bill).
     */
    public function spendOnce(string $idempotencyKey, User $user, string $kind): void
    {
        $cost = $this->cost($kind);

        DB::transaction(function () use ($idempotencyKey, $user, $kind, $cost): void {
            $existing = StoryCreditSpendEvent::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return;
            }

            if ($cost > 0) {
                $updated = User::query()
                    ->whereKey($user->id)
                    ->where('story_credits', '>=', $cost)
                    ->decrement('story_credits', $cost);

                if ($updated === 0) {
                    throw InsufficientStoryCreditsException::for($kind);
                }
            }

            StoryCreditSpendEvent::query()->create([
                'user_id' => $user->id,
                'idempotency_key' => $idempotencyKey,
                'kind' => $kind,
                'cost' => $cost,
                'spent_at' => now(),
            ]);
        }, 3);
    }
}
