<?php

namespace App\Services\Story\Audio;

use App\Contracts\Story\PageNarrationSynthesizer;
use App\Services\Media\StoryMediaStorage;
use OpenAI\Client;

class OpenAiPageNarrationSynthesizer implements PageNarrationSynthesizer
{
    public function __construct(
        private readonly Client $client,
        private readonly StoryMediaStorage $media,
    ) {}

    public function synthesize(string $text, string $storageDirectory): string
    {
        $binary = $this->client->audio()->speech([
            'model' => config('story.models.tts'),
            'voice' => config('story.models.tts_voice'),
            'input' => mb_substr($text, 0, 4096),
        ]);

        $name = 'audio-'.uniqid('', true).'.mp3';

        return $this->media->storeBytes($binary, $storageDirectory, $name, 'auto');
    }
}
