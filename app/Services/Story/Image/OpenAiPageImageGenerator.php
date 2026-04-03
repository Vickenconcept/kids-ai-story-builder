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
        $style = $this->describeIllustrationStyle($input->illustrationStyle);
        $prompt = implode(' ', [
            "Children's book illustration, {$style}, age-appropriate, no text or letters in the image.",
            'Scene inspired by:',
            mb_substr($input->pageText, 0, 900),
        ]);

        $model = trim((string) config('story.models.image'));

        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'n' => 1,
            'size' => '1024x1024',
        ];

        // DALL·E 2/3 accept response_format=url. GPT image models (gpt-image-1, gpt-image-1.5, …) reject it
        // and return base64 by default; see https://platform.openai.com/docs/api-reference/images/create
        if (str_starts_with(strtolower($model), 'dall-e-')) {
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

    /**
     * Map stored slug → richer prompt text so different UI choices produce visibly different art.
     */
    private function describeIllustrationStyle(string $slug): string
    {
        $key = strtolower(trim($slug));

        return match ($key) {
            'cartoon' => 'bold cartoon linework, saturated flat colors, expressive characters',
            'watercolor' => 'soft watercolor washes, paper texture, gentle edges, luminous pigment',
            '3d' => 'polished 3D render, soft studio lighting, rounded kid-friendly forms',
            'storybook' => 'classic printed storybook look, warm ink and gentle crosshatching',
            'anime' => 'anime-inspired: clean cel shading, large expressive eyes, dynamic poses',
            'flat-vector' => 'modern flat vector art, limited palette, crisp geometric shapes',
            'pencil-sketch' => 'graphite pencil sketch, light hatching, hand-drawn storybook feel',
            'pixel-art' => 'cohesive pixel art, limited resolution aesthetic, retro game charm',
            'paper-collage' => 'cut-paper collage, layered colored paper shapes, tactile edges',
            default => trim($slug).' illustration style',
        };
    }
}
