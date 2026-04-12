<?php

namespace App\Http\Controllers\Story;

use App\Http\Controllers\Controller;
use App\Models\StoryPage;
use App\Support\StoryMediaUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class StoryVideoLibraryController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $perPage = min(48, max(1, (int) $request->input('per_page', 12)));

        $videos = StoryPage::query()
            ->select('story_pages.*')
            ->join('story_projects', 'story_projects.id', '=', 'story_pages.story_project_id')
            ->where('story_projects.user_id', $user->id)
            ->whereNotNull('story_pages.video_path')
            ->where('story_pages.video_path', '!=', '')
            ->with(['project:id,uuid,title,updated_at'])
            ->orderByDesc('story_projects.updated_at')
            ->orderBy('story_pages.page_number')
            ->paginate($perPage)
            ->through(function (StoryPage $page): array {
                $videoUrl = StoryMediaUrl::resolve($page->video_path) ?? '';
                $project = $page->project;
                if ($project === null) {
                    throw new \RuntimeException('Story page is missing its parent project.');
                }

                $slug = Str::slug($project->title);
                $downloadName = $slug !== '' ? "{$slug}-page-{$page->page_number}.mp4" : "story-{$project->uuid}-page-{$page->page_number}.mp4";

                return [
                    'id' => $page->id,
                    'page_uuid' => $page->uuid,
                    'page_number' => $page->page_number,
                    'story_uuid' => $project->uuid,
                    'story_title' => $project->title,
                    'video_url' => $videoUrl,
                    'image_url' => StoryMediaUrl::resolve($page->image_path),
                    'download_filename' => $downloadName,
                    'story_editor_url' => route('stories.show', $project),
                ];
            });

        return Inertia::render('Stories/VideoLibrary', [
            'videos' => $videos,
        ]);
    }
}
