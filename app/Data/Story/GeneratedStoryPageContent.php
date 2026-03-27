<?php

namespace App\Data\Story;

readonly class GeneratedStoryPageContent
{
    /**
     * @param  list<array<string, mixed>>|null  $quizQuestions
     */
    public function __construct(
        public int $pageNumber,
        public string $text,
        public ?array $quizQuestions,
    ) {}
}
