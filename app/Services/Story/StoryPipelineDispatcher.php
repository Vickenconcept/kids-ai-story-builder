<?php

namespace App\Services\Story;

use App\Enums\FeatureTier;
use App\Enums\StoryAiJobStatus;
use App\Enums\StoryAiJobType;
use App\Enums\StoryProjectStatus;
use App\Jobs\Story\GenerateStoryPageAudioJob;
use App\Jobs\Story\GenerateStoryPageImageJob;
use App\Jobs\Story\GenerateStoryPageVideoJob;
use App\Jobs\Story\GenerateStoryTextJob;
use App\Models\StoryAiJob;
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
        $voice = $this->resolveNarrationVoice($project);

        foreach ($project->pages as $page) {
            GenerateStoryPageAudioJob::dispatch($page->id, $voice)
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

            if ($generateAudio) {
                Log::info('story.pipeline.dispatch.audio_parallel', [
                    'project_id' => $project->id,
                    'page_count' => $project->pages->count(),
                ]);
                $this->dispatchPageAudio($project);
            }

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

        if ($project->include_narration && $this->shouldQueueAudioForPage($page)) {
            Log::info('story.pipeline.queue_audio_after_image', [
                'project_id' => $project->id,
                'page_id' => $page->id,
            ]);
            GenerateStoryPageAudioJob::dispatch($page->id, $this->resolveNarrationVoice($project))
                ->onQueue(config('story.queues.audio'));
        }

        if ($this->maybeQueueVideoWhenReady($page, $project)) {
            return;
        }

        if ($this->pageAwaitingCompanionAssets($page, $project)) {
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

        Log::info('story.pipeline.after_audio', [
            'project_id' => $project->id,
            'page_id' => $page->id,
            'page_number' => $page->page_number,
            'include_video' => $project->include_video,
            'user_feature_tier' => $project->user?->feature_tier?->value,
        ]);

        if ($this->maybeQueueVideoWhenReady($page, $project)) {
            return;
        }

        if ($this->shouldQueueVideo($project) && blank($page->image_path)) {
            Log::warning('story.pipeline.video_skip_missing_image_after_audio', [
                'project_id' => $project->id,
                'page_id' => $page->id,
                'page_number' => $page->page_number,
            ]);
        }

        if ($this->pageAwaitingCompanionAssets($page, $project)) {
            return;
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

    /**
     * Queue video only when required inputs exist (image, and audio when narration is on).
     */
    private function maybeQueueVideoWhenReady(StoryPage $page, StoryProject $project): bool
    {
        if (! $this->shouldQueueVideo($project)) {
            return false;
        }

        if (filled($page->video_path)) {
            return false;
        }

        if ($this->hasPendingOrRunningJob($page, StoryAiJobType::PageVideo)) {
            return true;
        }

        if (blank($page->image_path)) {
            Log::info('story.pipeline.video_wait_for_image', [
                'project_id' => $project->id,
                'page_id' => $page->id,
            ]);

            return false;
        }

        if ($project->include_narration && blank($page->audio_path)) {
            Log::info('story.pipeline.video_wait_for_audio', [
                'project_id' => $project->id,
                'page_id' => $page->id,
            ]);

            return false;
        }

        Log::info('story.pipeline.queue_video_when_ready', [
            'project_id' => $project->id,
            'page_id' => $page->id,
        ]);
        GenerateStoryPageVideoJob::dispatch($page->id)
            ->onQueue(config('story.queues.video'));

        return true;
    }

    private function shouldQueueAudioForPage(StoryPage $page): bool
    {
        if (filled($page->audio_path)) {
            return false;
        }

        return ! $this->hasPendingOrRunningJob($page, StoryAiJobType::PageAudio);
    }

    private function pageAwaitingCompanionAssets(StoryPage $page, StoryProject $project): bool
    {
        if ($this->shouldQueueVideo($project) && $this->pageNeedsVideoPrerequisites($page, $project)) {
            return true;
        }

        return $project->include_narration && $this->pageAwaitingAudio($page);
    }

    private function pageNeedsVideoPrerequisites(StoryPage $page, StoryProject $project): bool
    {
        if (blank($page->image_path)) {
            return true;
        }

        if ($project->include_narration && blank($page->audio_path)) {
            return true;
        }

        return false;
    }

    private function pageAwaitingAudio(StoryPage $page): bool
    {
        if (filled($page->audio_path)) {
            return false;
        }

        return $this->hasPendingOrRunningJob($page, StoryAiJobType::PageAudio);
    }

    private function hasPendingOrRunningJob(StoryPage $page, StoryAiJobType $type): bool
    {
        return StoryAiJob::query()
            ->where('story_project_id', $page->story_project_id)
            ->where('story_page_id', $page->id)
            ->where('type', $type)
            ->whereIn('status', [StoryAiJobStatus::Pending, StoryAiJobStatus::Running])
            ->exists();
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

    private function resolveNarrationVoice(StoryProject $project): ?string
    {
        $voice = is_array($project->meta) ? ($project->meta['tts_voice'] ?? null) : null;

        return is_string($voice) && $voice !== '' ? $voice : null;
    }
}
