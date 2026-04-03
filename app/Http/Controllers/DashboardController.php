<?php

namespace App\Http\Controllers;

use App\Enums\StoryProjectStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user()->load('storyProjects');

        $projects = $user->storyProjects()->latest()->get([
            'id', 'uuid', 'title', 'topic', 'status', 'page_count',
            'pages_completed', 'include_video', 'include_narration', 'created_at',
        ]);

        $stats = [
            'total'      => $projects->count(),
            'ready'      => $projects->where('status', StoryProjectStatus::Ready)->count(),
            'processing' => $projects->where('status', StoryProjectStatus::Processing)->count(),
            'draft'      => $projects->where('status', StoryProjectStatus::Draft)->count(),
            'failed'     => $projects->where('status', StoryProjectStatus::Failed)->count(),
        ];

        $recentProjects = $projects->take(3)->map(fn ($p) => [
            'uuid'            => $p->uuid,
            'title'           => $p->title,
            'topic'           => $p->topic,
            'status'          => $p->status->value,
            'page_count'      => $p->page_count,
            'pages_completed' => $p->pages_completed,
            'include_video'   => $p->include_video,
            'include_narration' => $p->include_narration,
            'created_at'      => $p->created_at->diffForHumans(),
        ]);

        return Inertia::render('Dashboard', [
            'stats'          => $stats,
            'recentProjects' => $recentProjects,
            'credits'        => $user->story_credits ?? 0,
            'tier'           => $user->feature_tier?->value ?? 'basic',
        ]);
    }
}
