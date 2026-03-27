<?php

namespace App\Services\Story\Image;

use App\Contracts\Story\PageImageGenerator;
use App\Data\Story\PageImageInput;
use App\Services\Media\StoryMediaStorage;

class FakePageImageGenerator implements PageImageGenerator
{
    public function __construct(
        private readonly StoryMediaStorage $media,
    ) {}

    public function generate(PageImageInput $input, string $storageDirectory): string
    {
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1024" height="1024">
  <rect width="100%" height="100%" fill="#e8f4fc"/>
  <text x="512" y="480" text-anchor="middle" font-family="sans-serif" font-size="28" fill="#1e3a5f">Fake AI image</text>
  <text x="512" y="540" text-anchor="middle" font-family="sans-serif" font-size="18" fill="#334155">{$this->escape($input->illustrationStyle)}</text>
</svg>
SVG;

        $name = 'page-'.uniqid('', true).'.svg';

        return $this->media->storeBytes($svg, $storageDirectory, $name, 'image');
    }

    private function escape(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
