<?php

namespace App\Services\Story;

use App\Enums\StoryProjectStatus;
use App\Models\StoryPage;
use App\Models\StoryProject;
use Illuminate\Support\Facades\DB;

class StoryProjectReadiness
{
    public function markPagePipelineComplete(StoryPage $page): void
    {
        DB::transaction(function () use ($page): void {
            $marked = StoryPage::query()
                ->whereKey($page->id)
                ->whereNull('pipeline_completed_at')
                ->update(['pipeline_completed_at' => now()]);

            if ($marked === 0) {
                return;
            }

            /** @var StoryProject $project */
            $project = StoryProject::query()
                ->whereKey($page->story_project_id)
                ->lockForUpdate()
                ->firstOrFail();

            $completed = StoryPage::query()
                ->where('story_project_id', $project->id)
                ->whereNotNull('pipeline_completed_at')
                ->count();

            $target = (int) $project->page_count;
            $payload = [
                'pages_completed' => min($target, $completed),
            ];

            if ($completed >= $target) {
                $payload['status'] = StoryProjectStatus::Ready;
            }

            $project->update($payload);
        }, 3);
    }
}
