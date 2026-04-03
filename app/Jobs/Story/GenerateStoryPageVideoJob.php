<?php

namespace App\Jobs\Story;

use App\Contracts\Story\PageVideoGenerator;
use App\Enums\StoryAiJobStatus;
use App\Enums\StoryAiJobType;
use App\Models\StoryAiJob;
use App\Models\StoryPage;
use App\Services\Story\StoryAiJobRecorder;
use App\Services\Story\StoryCreditService;
use App\Services\Story\StoryPipelineDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateStoryPageVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Runway polling + download + FFmpeg mux can exceed default Forge worker timeouts (~60s).
     * Queue worker --timeout must be >= this value (Forge: daemon timeout in Supervisor).
     */
    public int $timeout = 900;

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
            'attempt' => $this->attempts(),
            'max_tries' => $this->tries,
            'has_image_path' => filled($page->image_path),
            'has_audio_path' => filled($page->audio_path),
        ]);

        if (blank($page->image_path)) {
            StoryAiJob::query()
                ->where('story_project_id', $project->id)
                ->where('story_page_id', $page->id)
                ->where('type', StoryAiJobType::PageVideo)
                ->where('status', StoryAiJobStatus::Pending)
                ->update([
                    'status' => StoryAiJobStatus::Failed,
                    'error_message' => 'Skipped: page image missing, video requires a generated page image.',
                    'finished_at' => now(),
                ]);

            $errors = $page->asset_errors ?? [];
            $errors['video'] = 'Skipped: page image missing, video requires a generated page image.';
            $page->update(['asset_errors' => $errors]);

            Log::warning('story.job.video.skipped_missing_image', [
                'project_id' => $project->id,
                'page_id' => $page->id,
                'page_number' => $page->page_number,
            ]);

            $dispatcher->afterVideo($page->fresh());

            return;
        }

        $jobRow = StoryAiJob::query()
            ->where('story_project_id', $project->id)
            ->where('story_page_id', $page->id)
            ->where('type', StoryAiJobType::PageVideo)
            ->where('status', StoryAiJobStatus::Pending)
            ->orderByDesc('id')
            ->first();

        if ($jobRow !== null) {
            $jobRow->update([
                'status' => StoryAiJobStatus::Running,
                'started_at' => now(),
                'payload' => array_merge($jobRow->payload ?? [], ['stage' => 'video']),
            ]);
        } else {
            $jobRow = $recorder->begin($project, StoryAiJobType::PageVideo, $page, ['stage' => 'video']);
        }

        try {
            $credits->spendOnce('video:page:'.$page->id, $project->user, 'video');
            $dir = 'stories/'.$project->id;
            $pageId = $page->id;
            $resumeTaskId = filled($page->runway_video_task_id) ? (string) $page->runway_video_task_id : null;
            $path = $video->generate(
                (string) $page->text_content,
                $page->image_path,
                $page->audio_path,
                $dir,
                $resumeTaskId,
                static function (?string $taskId) use ($pageId): void {
                    StoryPage::query()->whereKey($pageId)->update(['runway_video_task_id' => $taskId]);
                },
            );
            if ($path !== '') {
                StoryPage::query()->whereKey($pageId)->update([
                    'video_path' => $path,
                    'runway_video_task_id' => null,
                ]);
            }
            $recorder->complete($jobRow);
            Log::info('story.job.video.success', [
                'project_id' => $project->id,
                'page_id' => $page->id,
                'path' => $path,
            ]);
        } catch (Throwable $e) {
            $recorder->fail($jobRow, $e);

            if ($this->shouldRetry($e)) {
                Log::warning('story.job.video.retrying', [
                    'project_id' => $project->id,
                    'page_id' => $page->id,
                    'attempt' => $this->attempts(),
                    'max_tries' => $this->tries,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }

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

    private function shouldRetry(Throwable $e): bool
    {
        if ($this->attempts() >= $this->tries) {
            return false;
        }

        if ($this->isNonRetryableRunwayHttpError($e)) {
            return false;
        }

        $message = strtolower($e->getMessage());

        if (str_contains($message, 'not enough credits')) {
            return false;
        }

        if (str_contains($message, 'runway video task failed:')) {
            return false;
        }

        return str_contains($message, '502')
            || str_contains($message, '503')
            || str_contains($message, '504')
            || str_contains($message, '500')
            || str_contains($message, 'bad gateway')
            || str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || str_contains($message, 'connection')
            || str_contains($message, 'temporarily unavailable')
            || str_contains($message, 'rate limit')
            || str_contains($message, 'stale task id was cleared')
            || str_contains($message, 'task not found');
    }

    /**
     * Do not queue-retry client errors (4xx except 429): repeating the same bad payload wastes queue time and may confuse operators.
     */
    private function isNonRetryableRunwayHttpError(Throwable $e): bool
    {
        if (! $e instanceof RequestException) {
            return false;
        }

        $response = $e->response;
        if ($response === null) {
            return false;
        }

        $status = $response->status();

        return $status >= 400 && $status < 500 && $status !== 429;
    }
}
