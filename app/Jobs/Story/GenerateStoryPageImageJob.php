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
        } catch (Throwable $e) {
            $recorder->fail($jobRow, $e);
            $errors = $page->asset_errors ?? [];
            $errors['image'] = $e->getMessage();
            $page->update(['asset_errors' => $errors]);
        }

        $dispatcher->afterImage($page->fresh());
    }
}
