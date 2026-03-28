<?php

namespace Tests\Feature\Story;

use App\Enums\StoryProjectStatus;
use App\Jobs\Story\GenerateStoryTextJob;
use App\Models\StoryPage;
use App\Models\StoryProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StoryProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_story_and_redirects_to_show(): void
    {
        config(['story.use_fake_ai' => true]);
        Queue::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('stories.store'), [
            'title' => 'Moon Adventure',
            'topic' => 'Friendship on the moon',
            'lesson_type' => 'moral',
            'age_group' => '6-8',
            'page_count' => 3,
            'illustration_style' => 'cartoon',
            'include_quiz' => false,
            'include_narration' => false,
            'include_video' => false,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('story_projects', [
            'title' => 'Moon Adventure',
            'user_id' => $user->id,
        ]);

        $project = StoryProject::query()->where('title', 'Moon Adventure')->first();
        $this->assertNotNull($project);
        $this->assertSame(StoryProjectStatus::Processing, $project->status);

        Queue::assertPushed(GenerateStoryTextJob::class);

        $response->assertRedirect(route('stories.show', $project));
    }

    public function test_guest_cannot_access_stories(): void
    {
        $this->get('/stories')->assertRedirect();
    }

    public function test_owner_can_update_flip_presentation_and_page_quiz(): void
    {
        $user = User::factory()->create();
        $project = StoryProject::query()->create([
            'user_id' => $user->id,
            'title' => 'T',
            'topic' => 'Topic',
            'lesson_type' => 'moral',
            'age_group' => '6-8',
            'page_count' => 1,
            'illustration_style' => 'cartoon',
            'include_quiz' => true,
            'include_narration' => false,
            'include_video' => false,
            'status' => StoryProjectStatus::Ready,
            'pages_completed' => 1,
            'flip_gameplay_enabled' => true,
        ]);

        $page = StoryPage::query()->create([
            'story_project_id' => $project->id,
            'page_number' => 1,
            'text_content' => 'Hello',
            'quiz_questions' => [
                ['question' => 'Q?', 'choices' => ['A', 'B'], 'answer' => 'A'],
            ],
        ]);

        $this->actingAs($user)
            ->patch(route('stories.update', $project), [
                'flip_gameplay_enabled' => false,
                'cover_front' => ['kind' => 'solid', 'color' => '#112233'],
            ])
            ->assertRedirect();

        $project->refresh();
        $this->assertFalse($project->flip_gameplay_enabled);
        $this->assertSame('solid', $project->cover_front['kind']);
        $this->assertSame('#112233', $project->cover_front['color']);

        $this->actingAs($user)
            ->patch(route('stories.update', $project), [
                'cover_front' => [
                    'kind' => 'solid',
                    'color' => '#112233',
                    'frame' => 'minimal-gilt',
                ],
            ])
            ->assertRedirect();

        $project->refresh();
        $this->assertSame('minimal-gilt', $project->cover_front['frame']);

        $this->actingAs($user)
            ->patch(route('stories.update', $project), [
                'cover_front' => [
                    'kind' => 'solid',
                    'color' => '#112233',
                    'frame' => 'not-a-valid-frame',
                ],
            ])
            ->assertSessionHasErrors('cover_front.frame');

        $this->actingAs($user)
            ->patch(route('stories.pages.update', [$project, $page]), [
                'quiz_questions' => [
                    ['question' => 'New?', 'choices' => ['X', 'Y'], 'answer' => 'Y'],
                ],
            ])
            ->assertRedirect();

        $page->refresh();
        $this->assertSame('New?', $page->quiz_questions[0]['question']);
        $this->assertSame('Y', $page->quiz_questions[0]['answer']);
    }

    public function test_public_reader_route_is_404_when_sharing_disabled(): void
    {
        $user = User::factory()->create();
        $project = StoryProject::query()->create([
            'user_id' => $user->id,
            'title' => 'Public T',
            'topic' => 'T',
            'lesson_type' => 'moral',
            'age_group' => '6-8',
            'page_count' => 1,
            'illustration_style' => 'cartoon',
            'include_quiz' => false,
            'include_narration' => false,
            'include_video' => false,
            'status' => StoryProjectStatus::Ready,
            'pages_completed' => 1,
            'sharing_enabled' => false,
        ]);

        StoryPage::query()->create([
            'story_project_id' => $project->id,
            'page_number' => 1,
            'text_content' => 'Hi',
            'quiz_questions' => null,
        ]);

        $this->get(route('stories.public.show', $project))->assertNotFound();
    }

    public function test_guest_can_view_public_reader_when_sharing_enabled_and_ready(): void
    {
        $user = User::factory()->create();
        $project = StoryProject::query()->create([
            'user_id' => $user->id,
            'title' => 'Shared',
            'topic' => 'T',
            'lesson_type' => 'moral',
            'age_group' => '6-8',
            'page_count' => 1,
            'illustration_style' => 'cartoon',
            'include_quiz' => false,
            'include_narration' => false,
            'include_video' => false,
            'status' => StoryProjectStatus::Ready,
            'pages_completed' => 1,
            'sharing_enabled' => true,
        ]);

        StoryPage::query()->create([
            'story_project_id' => $project->id,
            'page_number' => 1,
            'text_content' => 'Hi',
            'quiz_questions' => null,
        ]);

        $this->get(route('stories.public.show', $project))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Stories/PublicShow')
                ->has('project', fn ($p) => $p
                    ->where('title', 'Shared')
                    ->etc())
                ->has('pages', 1));
    }
}
