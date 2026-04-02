<?php

namespace App\Services\Story;

use App\Enums\FeatureTier;
use App\Enums\StoryProjectStatus;
use App\Jobs\Story\GenerateStoryPageAudioJob;
use App\Jobs\Story\GenerateStoryPageImageJob;
use App\Jobs\Story\GenerateStoryPageVideoJob;
use App\Jobs\Story\GenerateStoryTextJob;
use App\Models\StoryPage;
use App\Models\StoryProject;
use Illuminate\Support\Facades\Log;

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

    public function dispatchPageAudio(StoryProject $project): void
    {
        foreach ($project->pages as $page) {
            GenerateStoryPageAudioJob::dispatch($page->id)
                ->onQueue(config('story.queues.audio'));
        }
    }

    public function dispatchSelectedMedia(StoryProject $project, bool $generateImages, bool $generateAudio, bool $generateVideo): void
    {
        Log::info('story.pipeline.media.selected', [
            'project_id' => $project->id,
            'project_uuid' => $project->uuid,
            'user_id' => $project->user_id,
            'requested_generate_images' => $generateImages,
            'requested_generate_audio' => $generateAudio,
            'requested_generate_video' => $generateVideo,
            'user_feature_tier' => $project->user?->feature_tier?->value,
        ]);

        $project->update([
            'include_narration' => $generateAudio,
            'include_video' => $generateVideo,
        ]);

        Log::info('story.pipeline.media.persisted', [
            'project_id' => $project->id,
            'include_narration' => $project->include_narration,
            'include_video' => $project->include_video,
        ]);

        if ($generateImages) {
            Log::info('story.pipeline.dispatch.images', [
                'project_id' => $project->id,
                'page_count' => $project->pages->count(),
            ]);
            $this->dispatchPageImages($project);

            return;
        }

        if ($generateAudio) {
            Log::info('story.pipeline.dispatch.audio', [
                'project_id' => $project->id,
                'page_count' => $project->pages->count(),
            ]);
            $this->dispatchPageAudio($project);

            return;
        }

        $project->update([
            'pages_completed' => $project->page_count,
            'status' => StoryProjectStatus::Ready,
        ]);
    }

    public function afterImage(StoryPage $page): void
    {
        $project = $page->project->fresh(['user']);

        Log::info('story.pipeline.after_image', [
            'project_id' => $project->id,
            'page_id' => $page->id,
            'page_number' => $page->page_number,
            'include_narration' => $project->include_narration,
            'include_video' => $project->include_video,
            'user_feature_tier' => $project->user?->feature_tier?->value,
        ]);

        if ($project->include_narration) {
            Log::info('story.pipeline.queue_audio_after_image', [
                'project_id' => $project->id,
                'page_id' => $page->id,
            ]);
            GenerateStoryPageAudioJob::dispatch($page->id)
                ->onQueue(config('story.queues.audio'));

            return;
        }

        if ($this->shouldQueueVideo($project)) {
            Log::info('story.pipeline.queue_video_after_image', [
                'project_id' => $project->id,
                'page_id' => $page->id,
            ]);
            GenerateStoryPageVideoJob::dispatch($page->id)
                ->onQueue(config('story.queues.video'));

            return;
        }

        Log::info('story.pipeline.complete_after_image', [
            'project_id' => $project->id,
            'page_id' => $page->id,
        ]);

        $this->readiness->markPagePipelineComplete($page->fresh());
    }

    public function afterAudio(StoryPage $page): void
    {
        $project = $page->project->fresh(['user']);
        $shouldQueueVideo = $this->shouldQueueVideo($project);

        Log::info('story.pipeline.after_audio', [
            'project_id' => $project->id,
            'page_id' => $page->id,
            'page_number' => $page->page_number,
            'include_video' => $project->include_video,
            'user_feature_tier' => $project->user?->feature_tier?->value,
        ]);

        if ($shouldQueueVideo && filled($page->image_path)) {
            Log::info('story.pipeline.queue_video_after_audio', [
                'project_id' => $project->id,
                'page_id' => $page->id,
            ]);
            GenerateStoryPageVideoJob::dispatch($page->id)
                ->onQueue(config('story.queues.video'));

            return;
        }

        if ($shouldQueueVideo && blank($page->image_path)) {
            Log::warning('story.pipeline.video_skip_missing_image_after_audio', [
                'project_id' => $project->id,
                'page_id' => $page->id,
                'page_number' => $page->page_number,
            ]);
        }

        Log::info('story.pipeline.complete_after_audio', [
            'project_id' => $project->id,
            'page_id' => $page->id,
        ]);

        $this->readiness->markPagePipelineComplete($page->fresh());
    }

    public function afterVideo(StoryPage $page): void
    {
        $this->readiness->markPagePipelineComplete($page->fresh());
    }

    private function shouldQueueVideo(StoryProject $project): bool
    {
        if (! $project->include_video) {
            Log::warning('story.pipeline.video_skip_include_video_disabled', [
                'project_id' => $project->id,
                'user_id' => $project->user_id,
            ]);
            return false;
        }

        $isPro = $project->user->feature_tier?->isPro();

        if (! $isPro) {
            Log::warning('story.pipeline.video_skip_user_not_pro', [
                'project_id' => $project->id,
                'user_id' => $project->user_id,
                'user_feature_tier' => $project->user?->feature_tier?->value,
            ]);
        }

        return $isPro;
    }
}
