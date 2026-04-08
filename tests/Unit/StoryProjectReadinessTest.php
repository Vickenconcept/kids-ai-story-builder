<?php

namespace Tests\Unit;

use App\Enums\StoryProjectStatus;
use App\Models\StoryPage;
use App\Models\StoryProject;
use App\Models\User;
use App\Services\Story\StoryProjectReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoryProjectReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_pipeline_completion_is_idempotent_and_project_ready_is_correct(): void
    {
        $user = User::factory()->create();

        $project = StoryProject::query()->create([
            'user_id' => $user->id,
            'title' => 'Readiness test',
            'topic' => 'T',
            'lesson_type' => 'moral',
            'age_group' => '6-8',
            'page_count' => 2,
            'illustration_style' => 'cartoon',
            'include_quiz' => false,
            'include_narration' => false,
            'include_video' => false,
            'status' => StoryProjectStatus::Processing,
            'pages_completed' => 0,
        ]);

        $page1 = StoryPage::query()->create([
            'story_project_id' => $project->id,
            'page_number' => 1,
            'text_content' => 'p1',
        ]);

        $page2 = StoryPage::query()->create([
            'story_project_id' => $project->id,
            'page_number' => 2,
            'text_content' => 'p2',
        ]);

        $service = app(StoryProjectReadiness::class);

        $service->markPagePipelineComplete($page1);
        $service->markPagePipelineComplete($page1->fresh());

        $project->refresh();
        $this->assertSame(1, (int) $project->pages_completed);
        $this->assertSame(StoryProjectStatus::Processing, $project->status);

        $service->markPagePipelineComplete($page2);

        $project->refresh();
        $this->assertSame(2, (int) $project->pages_completed);
        $this->assertSame(StoryProjectStatus::Ready, $project->status);
    }
}
