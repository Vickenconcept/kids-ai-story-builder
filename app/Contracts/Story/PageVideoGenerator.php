<?php

namespace App\Contracts\Story;

interface PageVideoGenerator
{
    /**
     * Optional provider: combine image + narration into a short clip.
     * Image/audio may be full URLs (Cloudinary) or legacy disk paths.
     *
     * @return string Public URL, relative disk path, or empty string if skipped
     */
    public function generate(
        string $pageText,
        ?string $relativeImagePath,
        ?string $relativeAudioPath,
        string $storageDirectory,
    ): string;
}
