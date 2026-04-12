<?php

namespace App\Data\Story;

readonly class PageImageInput
{
    public function __construct(
        public string $storyTitle,
        public string $pageText,
        public string $illustrationStyle,
        public string $ageGroup,
        /** Story-wide cast / species lock for illustration consistency (from `meta.character_visual_bible`). */
        public ?string $characterVisualBible = null,
    ) {}
}
