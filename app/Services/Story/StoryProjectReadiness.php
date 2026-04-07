<?php

namespace App\Services\Story;

use App\Enums\StoryProjectStatus;
use App\Models\StoryPage;

class StoryProjectReadiness
{
    public function markPagePipelineComplete(StoryPage $page): void
    {
        $project = $page->project;
        $project->refresh();

        $current = (int) $project->pages_completed;
        $target = (int) $project->page_count;

        if ($current < $target) {
            $project->update(['pages_completed' => min($target, $current + 1)]);
            $project->refresh();
        }

        if ($project->pages_completed >= $project->page_count) {
            $project->update(['status' => StoryProjectStatus::Ready]);
        }
    }
}
