<?php

namespace App\Policies;

use App\Models\StoryProject;
use App\Models\User;

class StoryProjectPolicy
{
    public function view(User $user, StoryProject $storyProject): bool
    {
        return $user->id === $storyProject->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, StoryProject $storyProject): bool
    {
        return $user->id === $storyProject->user_id;
    }
}
