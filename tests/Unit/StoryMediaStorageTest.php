<?php

namespace Tests\Unit;

use App\Services\Media\StoryMediaStorage;
use Tests\TestCase;

class StoryMediaStorageTest extends TestCase
{
    public function test_uses_cloudinary_when_config_url_is_cloudinary_scheme(): void
    {
        config(['services.cloudinary.url' => 'cloudinary://api_key:api_secret@my_cloud']);

        $this->assertTrue((new StoryMediaStorage)->usesCloudinary());
    }

    public function test_uses_cloudinary_when_url_has_env_prefix_stripped(): void
    {
        config(['services.cloudinary.url' => 'CLOUDINARY_URL=cloudinary://k:s@c']);

        $this->assertTrue((new StoryMediaStorage)->usesCloudinary());
    }

    public function test_uses_cloudinary_when_separate_credentials_set(): void
    {
        config([
            'services.cloudinary.url' => '',
            'services.cloudinary.cloud_name' => 'nm',
            'services.cloudinary.api_key' => 'key',
            'services.cloudinary.api_secret' => 'sec',
        ]);

        $this->assertTrue((new StoryMediaStorage)->usesCloudinary());
    }

    public function test_falls_back_to_local_when_unconfigured(): void
    {
        config([
            'services.cloudinary.url' => '',
            'services.cloudinary.cloud_name' => '',
            'services.cloudinary.api_key' => '',
            'services.cloudinary.api_secret' => '',
        ]);

        $this->assertFalse((new StoryMediaStorage)->usesCloudinary());
    }
}
