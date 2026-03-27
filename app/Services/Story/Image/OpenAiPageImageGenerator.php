<?php

namespace App\Services\Story\Image;

use App\Contracts\Story\PageImageGenerator;
use App\Data\Story\PageImageInput;
use App\Services\Media\StoryMediaStorage;
use Illuminate\Support\Facades\Http;
use OpenAI\Client;
use RuntimeException;

class OpenAiPageImageGenerator implements PageImageGenerator
{
    public function __construct(
        private readonly Client $client,
        private readonly StoryMediaStorage $media,
    ) {}

    public function generate(PageImageInput $input, string $storageDirectory): string
    {
        $prompt = implode(' ', [
            "Children's book illustration, {$input->illustrationStyle} style, age-appropriate, no text in image.",
            'Scene inspired by:',
            mb_substr($input->pageText, 0, 900),
        ]);

        $response = $this->client->images()->create([
            'model' => config('story.models.image'),
            'prompt' => $prompt,
            'n' => 1,
            'size' => '1024x1024',
            'response_format' => 'url',
        ]);

        $url = $response->data[0]->url ?? '';
        if ($url === '') {
            throw new RuntimeException('Image API returned no URL.');
        }

        $binary = Http::timeout(120)->get($url)->throw()->body();
        $name = 'page-'.uniqid('', true).'.png';

        return $this->media->storeBytes($binary, $storageDirectory, $name, 'image');
    }
}
