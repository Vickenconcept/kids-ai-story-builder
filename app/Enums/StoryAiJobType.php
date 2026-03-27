<?php

namespace App\Enums;

enum StoryAiJobType: string
{
    case StoryText = 'story_text';
    case PageImage = 'page_image';
    case PageAudio = 'page_audio';
    case PageVideo = 'page_video';
}
