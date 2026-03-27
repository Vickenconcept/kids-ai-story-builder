<?php

namespace App\Http\Controllers\Story;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateStoryPageRequest;
use App\Models\StoryPage;
use App\Models\StoryProject;
use Illuminate\Http\RedirectResponse;

class StoryPageController extends Controller
{
    public function update(UpdateStoryPageRequest $request, StoryProject $story, StoryPage $page): RedirectResponse
    {
        if ($page->story_project_id !== $story->id) {
            abort(404);
        }

        $page->update($request->validated());

        return back();
    }
}
