<?php

namespace App\Jobs\Story;

use App\Contracts\Story\TextStoryGenerator;
use App\Data\Story\StoryTextInput;
use App\Enums\StoryAiJobType;
use App\Enums\StoryProjectStatus;
use App\Models\StoryPage;
use App\Models\StoryProject;
use App\Services\Story\StoryAiJobRecorder;
use App\Services\Story\StoryCreditService;
use App\Services\Story\StoryPipelineDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class GenerateStoryTextJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [15, 60, 180];
    }

    public function __construct(
        public int $storyProjectId,
    ) {
        $this->onQueue(config('story.queues.text'));
    }

    public function handle(
        TextStoryGenerator $textGenerator,
        StoryCreditService $credits,
        StoryPipelineDispatcher $dispatcher,
        StoryAiJobRecorder $recorder,
    ): void {
        $project = StoryProject::query()->with('user')->findOrFail($this->storyProjectId);
        $jobRow = $recorder->begin($project, StoryAiJobType::StoryText, null, ['stage' => 'text']);

        try {
            $credits->spendOnce('text:project:'.$project->id, $project->user, 'text');

            $input = new StoryTextInput(
                title: $project->title,
                topic: $project->topic,
                lessonType: $project->lesson_type,
                ageGroup: $project->age_group,
                pageCount: $project->page_count,
                includeQuiz: $project->include_quiz,
                illustrationStyle: $project->illustration_style,
            );

            $outline = $textGenerator->generate($input);

            DB::transaction(function () use ($project, $outline): void {
                $project->pages()->delete();
                foreach ($outline->pages as $page) {
                    StoryPage::query()->create([
                        'story_project_id' => $project->id,
                        'page_number' => $page->pageNumber,
                        'text_content' => $page->text,
                        'quiz_questions' => $page->quizQuestions,
                    ]);
                }
                $project->update([
                    'pages_completed' => 0,
                ]);
            });

            $recorder->complete($jobRow);
            $dispatcher->dispatchPageImages($project->fresh(['pages', 'user']));
        } catch (Throwable $e) {
            $recorder->fail($jobRow, $e);
            $project->update(['status' => StoryProjectStatus::Failed]);
            throw $e;
        }
    }
}
