<?php

namespace App\Contracts\Story;

interface PageNarrationSynthesizer
{
    /**
     * @return string Public URL (e.g. Cloudinary) or legacy relative path on the public disk
     */
    public function synthesize(string $text, string $storageDirectory, ?string $voice = null): string;
}
