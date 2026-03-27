<?php

namespace App\Services\Story\Video;

use App\Contracts\Story\PageVideoGenerator;
use App\Services\Media\StoryMediaStorage;

/**
 * Placeholder for Runway / Kaiber — keeps pipeline independent from unfinished integrations.
 */
class NullPageVideoGenerator implements PageVideoGenerator
{
    public function __construct(
        private readonly StoryMediaStorage $media,
    ) {}

    public function generate(
        string $pageText,
        ?string $relativeImagePath,
        ?string $relativeAudioPath,
        string $storageDirectory,
    ): string {
        $note = json_encode([
            'message' => 'Video provider not configured; stub asset.',
            'has_image' => $relativeImagePath !== null,
            'has_audio' => $relativeAudioPath !== null,
        ], JSON_THROW_ON_ERROR);

        $name = 'video-'.uniqid('', true).'.json';

        return $this->media->storeBytes($note, $storageDirectory, $name, 'raw');
    }
}
