<?php

namespace Tests\Feature\Story;

use App\Enums\StoryProjectStatus;
use App\Models\StoryPage;
use App\Models\StoryProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoryVideoLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_basic_user_cannot_access_video_library(): void
    {
        $user = User::factory()->create(['feature_tier' => 'basic']);

        $this->actingAs($user)->get(route('video-library.index'))->assertForbidden();
    }

    public function test_pro_user_can_access_video_library(): void
    {
        $user = User::factory()->create(['feature_tier' => 'pro']);

        $this->actingAs($user)->get(route('video-library.index'))->assertOk();
    }

    public function test_elite_user_sees_only_their_page_videos(): void
    {
        $elite = User::factory()->create(['feature_tier' => 'elite']);
        $other = User::factory()->create(['feature_tier' => 'elite']);

        $mine = StoryProject::query()->create([
            'user_id' => $elite->id,
            'title' => 'My Book',
            'topic' => 'T',
            'lesson_type' => 'moral',
            'age_group' => '6-8',
            'page_count' => 2,
            'illustration_style' => 'cartoon',
            'include_quiz' => false,
            'include_narration' => false,
            'include_video' => true,
            'status' => StoryProjectStatus::Ready,
            'pages_completed' => 2,
        ]);

        $theirs = StoryProject::query()->create([
            'user_id' => $other->id,
            'title' => 'Their Book',
            'topic' => 'T',
            'lesson_type' => 'moral',
            'age_group' => '6-8',
            'page_count' => 1,
            'illustration_style' => 'cartoon',
            'include_quiz' => false,
            'include_narration' => false,
            'include_video' => true,
            'status' => StoryProjectStatus::Ready,
            'pages_completed' => 1,
        ]);

        StoryPage::query()->create([
            'story_project_id' => $mine->id,
            'page_number' => 1,
            'text_content' => 'A',
            'video_path' => 'https://example.com/mine.mp4',
        ]);
        StoryPage::query()->create([
            'story_project_id' => $mine->id,
            'page_number' => 2,
            'text_content' => 'B',
            'video_path' => null,
        ]);

        StoryPage::query()->create([
            'story_project_id' => $theirs->id,
            'page_number' => 1,
            'text_content' => 'C',
            'video_path' => 'https://example.com/theirs.mp4',
        ]);

        $response = $this->actingAs($elite)->get(route('video-library.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Stories/VideoLibrary')
            ->has('videos.data', 1)
            ->where('videos.data.0.story_title', 'My Book')
            ->where('videos.data.0.page_number', 1)
            ->where('videos.data.0.video_url', 'https://example.com/mine.mp4'));
    }
}
