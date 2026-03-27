<?php

namespace App\Enums;

enum StoryProjectStatus: string
{
    case Draft = 'draft';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
}
