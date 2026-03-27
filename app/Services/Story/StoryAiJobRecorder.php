<?php

namespace App\Services\Story;

use App\Enums\StoryAiJobStatus;
use App\Enums\StoryAiJobType;
use App\Models\StoryAiJob;
use App\Models\StoryPage;
use App\Models\StoryProject;
use Throwable;

class StoryAiJobRecorder
{
    public function begin(
        StoryProject $project,
        StoryAiJobType $type,
        ?StoryPage $page = null,
        ?array $payload = null,
    ): StoryAiJob {
        return StoryAiJob::create([
            'story_project_id' => $project->id,
            'story_page_id' => $page?->id,
            'type' => $type,
            'status' => StoryAiJobStatus::Running,
            'payload' => $payload,
            'started_at' => now(),
        ]);
    }

    public function markRetryAttempt(StoryAiJob $job): void
    {
        $job->increment('attempts');
    }

    public function complete(StoryAiJob $job): void
    {
        $job->update([
            'status' => StoryAiJobStatus::Succeeded,
            'finished_at' => now(),
        ]);
    }

    public function fail(StoryAiJob $job, Throwable $e): void
    {
        $job->update([
            'status' => StoryAiJobStatus::Failed,
            'error_message' => $e->getMessage(),
            'finished_at' => now(),
        ]);
    }
}
