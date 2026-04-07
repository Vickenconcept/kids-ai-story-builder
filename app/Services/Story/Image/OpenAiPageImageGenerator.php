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

        if ($this->looksLikeBlankImage($binary)) {
            throw new RuntimeException('Generated image appears blank or black.');
        }

        $name = 'page-'.uniqid('', true).'.png';

        return $this->media->storeBytes($binary, $storageDirectory, $name, 'image');
    }

    private function looksLikeBlankImage(string $binary): bool
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagecolorat')) {
            return false;
        }

        $img = @imagecreatefromstring($binary);
        if ($img === false) {
            return false;
        }

        try {
            $width = imagesx($img);
            $height = imagesy($img);

            if ($width < 1 || $height < 1) {
                return true;
            }

            $stepX = max(1, (int) floor($width / 32));
            $stepY = max(1, (int) floor($height / 32));
            $opaqueSamples = 0;
            $darkSamples = 0;
            $luminanceSum = 0.0;

            for ($x = 0; $x < $width; $x += $stepX) {
                for ($y = 0; $y < $height; $y += $stepY) {
                    $argb = imagecolorat($img, $x, $y);
                    $alpha = ($argb & 0x7F000000) >> 24;

                    if ($alpha >= 126) {
                        continue;
                    }

                    $r = ($argb >> 16) & 0xFF;
                    $g = ($argb >> 8) & 0xFF;
                    $b = $argb & 0xFF;
                    $lum = (0.2126 * $r) + (0.7152 * $g) + (0.0722 * $b);

                    $opaqueSamples++;
                    $luminanceSum += $lum;

                    if ($lum < 20) {
                        $darkSamples++;
                    }
                }
            }

            if ($opaqueSamples === 0) {
                return true;
            }

            $averageLuminance = $luminanceSum / $opaqueSamples;
            $darkRatio = $darkSamples / $opaqueSamples;

            return $averageLuminance < 12 || $darkRatio >= 0.98;
        } finally {
            imagedestroy($img);
        }
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
