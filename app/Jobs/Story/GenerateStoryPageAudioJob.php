<?php

namespace App\Jobs\Story;

use App\Contracts\Story\PageNarrationSynthesizer;
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

class GenerateStoryPageAudioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 45, 120];
    }

    public function __construct(
        public int $storyPageId,
    ) {
        $this->onQueue(config('story.queues.audio'));
    }

    public function handle(
        PageNarrationSynthesizer $tts,
        StoryCreditService $credits,
        StoryPipelineDispatcher $dispatcher,
        StoryAiJobRecorder $recorder,
    ): void {
        $page = StoryPage::query()->with(['project.user'])->findOrFail($this->storyPageId);
        $project = $page->project;

        if (! $project->include_narration) {
            $dispatcher->afterAudio($page);

            return;
        }

        $jobRow = $recorder->begin($project, StoryAiJobType::PageAudio, $page, ['stage' => 'audio']);

        try {
            $credits->spendOnce('audio:page:'.$page->id, $project->user, 'audio');
            $dir = 'stories/'.$project->id;
            $voice = is_array($project->meta) ? ($project->meta['tts_voice'] ?? null) : null;
            $path = $tts->synthesize((string) $page->text_content, $dir, is_string($voice) ? $voice : null);
            $page->update(['audio_path' => $path]);
            $recorder->complete($jobRow);
        } catch (Throwable $e) {
            $recorder->fail($jobRow, $e);
            $errors = $page->asset_errors ?? [];
            $errors['audio'] = $e->getMessage();
            $page->update(['asset_errors' => $errors]);
        }

        $dispatcher->afterAudio($page->fresh());
    }
}
