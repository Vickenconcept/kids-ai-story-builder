<?php

namespace App\Http\Controllers\Story;

use App\Enums\StoryProjectStatus;
use App\Http\Controllers\Controller;
use App\Models\StoryProject;
use App\Support\StoryMediaUrl;
use Inertia\Inertia;
use Inertia\Response;

class PublicStoryController extends Controller
{
    public function show(StoryProject $story): Response
    {
        if (! $story->sharing_enabled || $story->status !== StoryProjectStatus::Ready) {
            abort(404);
        }

        $story->load('pages');

        $pages = $story->pages->map(fn ($page) => [
            'id' => $page->id,
            'uuid' => $page->uuid,
            'page_number' => $page->page_number,
            'text_content' => $page->text_content,
            'quiz_questions' => $page->quiz_questions,
            'asset_errors' => null,
            'image_url' => StoryMediaUrl::resolve($page->image_path),
            'audio_url' => StoryMediaUrl::resolve($page->audio_path),
            'video_url' => StoryMediaUrl::resolve($page->video_path),
        ]);

        return Inertia::render('Stories/PublicShow', [
            'project' => [
                'uuid' => $story->uuid,
                'title' => $story->title,
                'topic' => $story->topic,
                'include_quiz' => $story->include_quiz,
                'include_narration' => $story->include_narration,
                'flip_gameplay_enabled' => $story->flip_gameplay_enabled,
                'cover_front' => $this->hydrateCover($story->cover_front),
                'cover_back' => $this->hydrateCover($story->cover_back),
                'flip_settings' => $story->flip_settings,
            ],
            'pages' => $pages,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $config
     * @return array<string, mixed>|null
     */
    private function hydrateCover(?array $config): ?array
    {
        if ($config === null || $config === []) {
            return null;
        }

        $out = $config;
        $kind = $config['kind'] ?? '';

        if (in_array($kind, ['image', 'gif', 'ai_image'], true) && ! empty($config['path']) && is_string($config['path'])) {
            $out['url'] = StoryMediaUrl::resolve($config['path']);
        }

        return $out;
    }
}
