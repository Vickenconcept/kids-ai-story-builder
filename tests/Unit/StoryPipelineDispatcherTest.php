<?php

namespace Tests\Unit;

use App\Enums\StoryAiJobStatus;
use App\Enums\StoryAiJobType;
use App\Enums\StoryProjectStatus;
use App\Models\StoryAiJob;
use App\Jobs\Story\GenerateStoryPageAudioJob;
use App\Jobs\Story\GenerateStoryPageImageJob;
use App\Jobs\Story\GenerateStoryPageVideoJob;
use App\Models\StoryPage;
use App\Models\StoryProject;
use App\Models\User;
use App\Services\Story\StoryPipelineDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class StoryPipelineDispatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatching_images_and_audio_queues_both_in_parallel(): void
    {
        Bus::fake();

        $user = User::factory()->create(['feature_tier' => 'elite']);
        $project = $this->makeProject($user, pages: 2);

        app(StoryPipelineDispatcher::class)->dispatchSelectedMedia(
            $project->fresh(['pages', 'user']),
            generateImages: true,
            generateAudio: true,
            generateVideo: true,
        );

        Bus::assertDispatched(GenerateStoryPageImageJob::class, 2);
        Bus::assertDispatched(GenerateStoryPageAudioJob::class, 2);
    }

    public function test_after_image_waits_for_audio_before_video_when_both_in_flight(): void
    {
        Bus::fake();

        $user = User::factory()->create(['feature_tier' => 'elite']);
        $project = $this->makeProject($user, pages: 1, includeNarration: true, includeVideo: true);
        $page = $project->pages->first();

        $page->update(['image_path' => 'https://example.com/page.png']);

        StoryAiJob::query()->create([
            'story_project_id' => $project->id,
            'story_page_id' => $page->id,
            'type' => StoryAiJobType::PageAudio,
            'status' => StoryAiJobStatus::Running,
            'payload' => ['stage' => 'audio'],
        ]);

        app(StoryPipelineDispatcher::class)->afterImage($page->fresh());

        Bus::assertNotDispatched(GenerateStoryPageVideoJob::class);
        Bus::assertNotDispatched(GenerateStoryPageAudioJob::class);
    }

    public function test_after_audio_queues_video_when_image_and_audio_are_ready(): void
    {
        Bus::fake();

        $user = User::factory()->create(['feature_tier' => 'elite']);
        $project = $this->makeProject($user, pages: 1, includeNarration: true, includeVideo: true);
        $page = $project->pages->first();

        $page->update([
            'image_path' => 'https://example.com/page.png',
            'audio_path' => 'https://example.com/page.mp3',
        ]);

        app(StoryPipelineDispatcher::class)->afterAudio($page->fresh());

        Bus::assertDispatched(GenerateStoryPageVideoJob::class, 1);
    }

    public function test_after_image_queues_video_when_narration_off_and_image_ready(): void
    {
        Bus::fake();

        $user = User::factory()->create(['feature_tier' => 'pro']);
        $project = $this->makeProject($user, pages: 1, includeNarration: false, includeVideo: true);
        $page = $project->pages->first();

        $page->update(['image_path' => 'https://example.com/page.png']);

        app(StoryPipelineDispatcher::class)->afterImage($page->fresh());

        Bus::assertDispatched(GenerateStoryPageVideoJob::class, 1);
        Bus::assertNotDispatched(GenerateStoryPageAudioJob::class);
    }

    private function makeProject(
        User $user,
        int $pages,
        bool $includeNarration = false,
        bool $includeVideo = false,
    ): StoryProject {
        $project = StoryProject::query()->create([
            'user_id' => $user->id,
            'title' => 'Pipeline test',
            'topic' => 'T',
            'lesson_type' => 'moral',
            'age_group' => '6-8',
            'page_count' => $pages,
            'illustration_style' => 'cartoon',
            'include_quiz' => false,
            'include_narration' => $includeNarration,
            'include_video' => $includeVideo,
            'status' => StoryProjectStatus::Processing,
            'pages_completed' => 0,
        ]);

        for ($i = 1; $i <= $pages; $i++) {
            StoryPage::query()->create([
                'story_project_id' => $project->id,
                'page_number' => $i,
                'text_content' => "Page {$i}",
            ]);
        }

        return $project->fresh(['pages', 'user']);
    }
}
