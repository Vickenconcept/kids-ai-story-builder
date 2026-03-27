<?php

namespace App\Contracts\Story;

use App\Data\Story\GeneratedStoryOutline;
use App\Data\Story\StoryTextInput;

interface TextStoryGenerator
{
    public function generate(StoryTextInput $input): GeneratedStoryOutline;
}
