<?php

namespace App\Providers;

use App\Contracts\Story\PageImageGenerator;
use App\Contracts\Story\PageNarrationSynthesizer;
use App\Contracts\Story\PageVideoGenerator;
use App\Contracts\Story\TextStoryGenerator;
use App\Services\Story\Audio\FakePageNarrationSynthesizer;
use App\Services\Story\Audio\OpenAiPageNarrationSynthesizer;
use App\Services\Story\Image\FakePageImageGenerator;
use App\Services\Story\Image\OpenAiPageImageGenerator;
use App\Services\Story\Text\FakeTextStoryGenerator;
use App\Services\Story\Text\OpenAiTextStoryGenerator;
use App\Services\Media\StoryMediaStorage;
use App\Services\Story\Video\NullPageVideoGenerator;
use App\Services\Story\Video\RunwayPageVideoGenerator;
use Illuminate\Support\ServiceProvider;
use OpenAI;
use OpenAI\Client;

class StoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StoryMediaStorage::class, StoryMediaStorage::class);

        $useFake = (bool) config('story.use_fake_ai');

        if (! $useFake) {
            $this->app->singleton(Client::class, function () {
                $key = config('services.openai.api_key');
                if (! is_string($key) || $key === '') {
                    throw new \RuntimeException('OPENAI_API_KEY is required when STORY_USE_FAKE_AI is false.');
                }

                return OpenAI::factory()
                    ->withApiKey($key)
                    ->withOrganization(config('services.openai.organization'))
                    ->make();
            });
        }

        $this->app->bind(TextStoryGenerator::class, function () use ($useFake) {
            return $useFake
                ? new FakeTextStoryGenerator
                : $this->app->make(OpenAiTextStoryGenerator::class);
        });

        $this->app->bind(PageImageGenerator::class, function () use ($useFake) {
            $storage = $this->app->make(StoryMediaStorage::class);

            return $useFake
                ? new FakePageImageGenerator($storage)
                : $this->app->make(OpenAiPageImageGenerator::class);
        });

        $this->app->bind(PageNarrationSynthesizer::class, function () use ($useFake) {
            $storage = $this->app->make(StoryMediaStorage::class);

            return $useFake
                ? new FakePageNarrationSynthesizer($storage)
                : $this->app->make(OpenAiPageNarrationSynthesizer::class);
        });

        $this->app->bind(PageVideoGenerator::class, function () use ($useFake) {
            $storage = $this->app->make(StoryMediaStorage::class);

            if (! $useFake) {
                return new RunwayPageVideoGenerator($storage);
            }

            return new NullPageVideoGenerator($storage);
        });
    }
}
