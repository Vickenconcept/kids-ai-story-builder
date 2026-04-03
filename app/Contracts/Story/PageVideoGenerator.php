<?php

namespace App\Contracts\Story;

use Closure;

interface PageVideoGenerator
{
    /**
     * Optional provider: combine image + narration into a short clip.
     * Image/audio may be full URLs (Cloudinary) or legacy disk paths.
     *
     * @param  (Closure(?string): void)|null  $onRunwayTaskIdChanged  Persist or clear Runway task id (resume-safe retries).
     *
     * @return string Public URL, relative disk path, or empty string if skipped
     */
    public function generate(
        string $pageText,
        ?string $relativeImagePath,
        ?string $relativeAudioPath,
        string $storageDirectory,
        ?string $resumeRunwayTaskId = null,
        ?Closure $onRunwayTaskIdChanged = null,
    ): string;
}
