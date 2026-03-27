<?php

namespace App\Services\Story;

use App\Enums\FeatureTier;
use App\Jobs\Story\GenerateStoryPageAudioJob;
use App\Jobs\Story\GenerateStoryPageImageJob;
use App\Jobs\Story\GenerateStoryPageVideoJob;
use App\Jobs\Story\GenerateStoryTextJob;
use App\Models\StoryPage;
use App\Models\StoryProject;

class StoryPipelineDispatcher
{
    public function __construct(
        private readonly StoryProjectReadiness $readiness,
    ) {}

    public function queueStoryText(StoryProject $project): void
    {
        GenerateStoryTextJob::dispatch($project->id)
            ->onQueue(config('story.queues.text'));
    }

    public function dispatchPageImages(StoryProject $project): void
    {
        foreach ($project->pages as $page) {
            GenerateStoryPageImageJob::dispatch($page->id)
                ->onQueue(config('story.queues.image'));
        }
    }

    public function afterImage(StoryPage $page): void
    {
        $project = $page->project->fresh(['user']);

        if ($project->include_narration) {
            GenerateStoryPageAudioJob::dispatch($page->id)
                ->onQueue(config('story.queues.audio'));

            return;
        }

        if ($this->shouldQueueVideo($project)) {
            GenerateStoryPageVideoJob::dispatch($page->id)
                ->onQueue(config('story.queues.video'));

            return;
        }

        $this->readiness->markPagePipelineComplete($page->fresh());
    }

    public function afterAudio(StoryPage $page): void
    {
        $project = $page->project->fresh(['user']);

        if ($this->shouldQueueVideo($project)) {
            GenerateStoryPageVideoJob::dispatch($page->id)
                ->onQueue(config('story.queues.video'));

            return;
        }

        $this->readiness->markPagePipelineComplete($page->fresh());
    }

    public function afterVideo(StoryPage $page): void
    {
        $this->readiness->markPagePipelineComplete($page->fresh());
    }

    private function shouldQueueVideo(StoryProject $project): bool
    {
        if (! $project->include_video) {
            return false;
        }

        return $project->user->feature_tier === FeatureTier::Pro;
    }
}
