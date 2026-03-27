<?php

namespace App\Services\Story\Audio;

use App\Contracts\Story\PageNarrationSynthesizer;
use App\Services\Media\StoryMediaStorage;

class FakePageNarrationSynthesizer implements PageNarrationSynthesizer
{
    public function __construct(
        private readonly StoryMediaStorage $media,
    ) {}

    public function synthesize(string $text, string $storageDirectory): string
    {
        $body = "Fake narration (not playable audio).\n\n".$text;
        $name = 'audio-'.uniqid('', true).'.txt';

        return $this->media->storeBytes($body, $storageDirectory, $name, 'raw');
    }
}
