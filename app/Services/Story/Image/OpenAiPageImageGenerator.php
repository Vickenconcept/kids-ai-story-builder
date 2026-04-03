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

        $payload = [
            'model' => config('story.models.image'),
            'prompt' => $prompt,
            'n' => 1,
            'size' => '1024x1024',
        ];
        if (! str_starts_with((string) $payload['model'], 'gpt-image-')) {
            $payload['response_format'] = 'url';
        }

        $response = $this->client->images()->create($payload);

        $url = $response->data[0]->url ?? '';
        if ($url !== '') {
            $binary = Http::timeout(120)->get($url)->throw()->body();
        } else {
            $b64 = $response->data[0]->b64_json ?? '';
            $binary = $b64 !== '' ? base64_decode($b64, true) : false;
        }

        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException('Image API returned no image data.');
        }

        $name = 'page-'.uniqid('', true).'.png';

        return $this->media->storeBytes($binary, $storageDirectory, $name, 'image');
    }
}
