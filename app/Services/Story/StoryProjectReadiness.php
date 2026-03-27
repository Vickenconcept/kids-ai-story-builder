<?php

namespace App\Services\Story;

use App\Enums\StoryProjectStatus;
use App\Models\StoryPage;

class StoryProjectReadiness
{
    public function markPagePipelineComplete(StoryPage $page): void
    {
        $project = $page->project;
        $project->increment('pages_completed');
        $project->refresh();

        if ($project->pages_completed >= $project->page_count) {
            $project->update(['status' => StoryProjectStatus::Ready]);
        }
    }
}
