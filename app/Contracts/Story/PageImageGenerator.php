<?php

namespace App\Contracts\Story;

use App\Data\Story\PageImageInput;

interface PageImageGenerator
{
    /**
     * Generate an illustration and persist; returns a public HTTPS URL (e.g. Cloudinary) or a legacy public-disk path.
     */
    public function generate(PageImageInput $input, string $storageDirectory): string;
}
