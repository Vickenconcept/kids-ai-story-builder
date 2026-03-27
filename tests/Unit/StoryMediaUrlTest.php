<?php

namespace Tests\Unit;

use App\Support\StoryMediaUrl;
use Tests\TestCase;

class StoryMediaUrlTest extends TestCase
{
    public function test_passes_through_https_urls(): void
    {
        $u = 'https://res.cloudinary.com/demo/image/upload/sample.png';
        $this->assertSame($u, StoryMediaUrl::resolve($u));
    }

    public function test_resolves_legacy_disk_path(): void
    {
        $resolved = StoryMediaUrl::resolve('stories/1/page.png');
        $this->assertNotNull($resolved);
        $this->assertStringContainsString('stories/1/page.png', $resolved);
    }
}
