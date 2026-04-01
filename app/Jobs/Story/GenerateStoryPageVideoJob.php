<?php

namespace App\Jobs\Story;

use App\Contracts\Story\PageVideoGenerator;
use App\Enums\StoryAiJobType;
use App\Models\StoryPage;
use App\Services\Story\StoryAiJobRecorder;
use App\Services\Story\StoryCreditService;
use App\Services\Story\StoryPipelineDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateStoryPageVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function __construct(
        public int $storyPageId,
    ) {
        $this->onQueue(config('story.queues.video'));
    }

    public function handle(
        PageVideoGenerator $video,
        StoryCreditService $credits,
        StoryPipelineDispatcher $dispatcher,
        StoryAiJobRecorder $recorder,
    ): void {
        $page = StoryPage::query()->with(['project.user'])->findOrFail($this->storyPageId);
        $project = $page->project;

        Log::info('story.job.video.start', [
            'project_id' => $project->id,
            'page_id' => $page->id,
            'page_number' => $page->page_number,
            'queue' => config('story.queues.video'),
            'has_image_path' => filled($page->image_path),
            'has_audio_path' => filled($page->audio_path),
        ]);

        $jobRow = $recorder->begin($project, StoryAiJobType::PageVideo, $page, ['stage' => 'video']);

        try {
            $credits->spendOnce('video:page:'.$page->id, $project->user, 'video');
            $dir = 'stories/'.$project->id;
            $path = $video->generate(
                (string) $page->text_content,
                $page->image_path,
                $page->audio_path,
                $dir,
            );
            if ($path !== '') {
                $page->update(['video_path' => $path]);
            }
            $recorder->complete($jobRow);
            Log::info('story.job.video.success', [
                'project_id' => $project->id,
                'page_id' => $page->id,
                'path' => $path,
            ]);
        } catch (Throwable $e) {
            $recorder->fail($jobRow, $e);
            $errors = $page->asset_errors ?? [];
            $errors['video'] = $e->getMessage();
            $page->update(['asset_errors' => $errors]);
            Log::error('story.job.video.failed', [
                'project_id' => $project->id,
                'page_id' => $page->id,
                'error' => $e->getMessage(),
            ]);
        }

        $dispatcher->afterVideo($page->fresh());
    }
}
