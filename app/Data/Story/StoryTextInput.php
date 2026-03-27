<?php

namespace App\Data\Story;

readonly class StoryTextInput
{
    public function __construct(
        public string $title,
        public string $topic,
        public string $lessonType,
        public string $ageGroup,
        public int $pageCount,
        public bool $includeQuiz,
        public string $illustrationStyle,
    ) {}
}
