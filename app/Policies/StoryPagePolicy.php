<?php

namespace App\Policies;

use App\Models\StoryPage;
use App\Models\User;

class StoryPagePolicy
{
    public function update(User $user, StoryPage $storyPage): bool
    {
        return $user->id === $storyPage->project->user_id;
    }
}
