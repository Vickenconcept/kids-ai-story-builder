<?php

namespace App\Data\Story;

readonly class GeneratedStoryOutline
{
    /**
     * @param  list<GeneratedStoryPageContent>  $pages
     */
    public function __construct(
        public array $pages,
        public ?string $characterVisualBible = null,
    ) {}
}
