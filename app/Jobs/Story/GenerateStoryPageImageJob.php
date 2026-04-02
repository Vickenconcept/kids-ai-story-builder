<?php

namespace App\Jobs\Story;

use App\Contracts\Story\PageImageGenerator;
use App\Data\Story\PageImageInput;
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

class GenerateStoryPageImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [20, 90, 240];
    }

    public function __construct(
        public int $storyPageId,
    ) {
        $this->onQueue(config('story.queues.image'));
    }

    public function handle(
        PageImageGenerator $images,
        StoryCreditService $credits,
        StoryPipelineDispatcher $dispatcher,
        StoryAiJobRecorder $recorder,
    ): void {
        $page = StoryPage::query()->with(['project.user'])->findOrFail($this->storyPageId);
        $project = $page->project;
        Log::info('story.job.image.start', [
            'project_id' => $project->id,
            'page_id' => $page->id,
            'page_number' => $page->page_number,
            'queue' => config('story.queues.image'),
        ]);
        $jobRow = $recorder->begin($project, StoryAiJobType::PageImage, $page, ['stage' => 'image']);

        try {
            $credits->spendOnce('image:page:'.$page->id, $project->user, 'image');

            $dir = 'stories/'.$project->id;
            $input = new PageImageInput(
                storyTitle: $project->title,
                pageText: (string) $page->text_content,
                illustrationStyle: $project->illustration_style,
                ageGroup: $project->age_group,
            );

            $path = $images->generate($input, $dir);
            $page->update(['image_path' => $path]);
            $recorder->complete($jobRow);
            Log::info('story.job.image.success', [
                'project_id' => $project->id,
                'page_id' => $page->id,
                'path' => $path,
            ]);
        } catch (Throwable $e) {
            $recorder->fail($jobRow, $e);

            if ($this->shouldRetry($e)) {
                Log::warning('story.job.image.retrying', [
                    'project_id' => $project->id,
                    'page_id' => $page->id,
                    'attempt' => $this->attempts(),
                    'max_tries' => $this->tries,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }

            $errors = $page->asset_errors ?? [];
            $errors['image'] = $e->getMessage();
            $page->update(['asset_errors' => $errors]);
            Log::error('story.job.image.failed', [
                'project_id' => $project->id,
                'page_id' => $page->id,
                'error' => $e->getMessage(),
            ]);
        }

        $dispatcher->afterImage($page->fresh());
    }

    private function shouldRetry(Throwable $e): bool
    {
        if ($this->attempts() >= $this->tries) {
            return false;
        }

        $message = strtolower($e->getMessage());

        return str_contains($message, '502')
            || str_contains($message, '503')
            || str_contains($message, '504')
            || str_contains($message, 'bad gateway')
            || str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || str_contains($message, 'connection')
            || str_contains($message, 'temporarily unavailable')
            || str_contains($message, 'rate limit');
    }
}
