<?php

namespace App\Data\Story;

readonly class PageImageInput
{
    public function __construct(
        public string $storyTitle,
        public string $pageText,
        public string $illustrationStyle,
        public string $ageGroup,
    ) {}
}
