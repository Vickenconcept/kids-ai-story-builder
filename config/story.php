<?php

$dedicated = filter_var(env('STORY_DEDICATED_QUEUES', false), FILTER_VALIDATE_BOOL);
$fallback = env('STORY_QUEUE_FALLBACK', 'default');

return [

    /*
    |--------------------------------------------------------------------------
    | Development: deterministic fake AI (no API calls)
    |--------------------------------------------------------------------------
    */
    'use_fake_ai' => env('STORY_USE_FAKE_AI', false),

    /*
    |--------------------------------------------------------------------------
    | Queue routing
    |--------------------------------------------------------------------------
    |
    | When dedicated queues are off (default), all story jobs use "default" so
    | `php artisan queue:work` picks them up. Turn on for scaled workers.
    |
    */
    'dedicated_queues' => $dedicated,

    'queues' => [
        'text' => $dedicated ? env('STORY_QUEUE_TEXT', 'story-text') : $fallback,
        'image' => $dedicated ? env('STORY_QUEUE_IMAGE', 'story-image') : $fallback,
        'audio' => $dedicated ? env('STORY_QUEUE_AUDIO', 'story-audio') : $fallback,
        'video' => $dedicated ? env('STORY_QUEUE_VIDEO', 'story-video') : $fallback,
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI model IDs (override via .env when vendors change)
    |--------------------------------------------------------------------------
    */
    'models' => [
        'text' => env('STORY_MODEL_TEXT', 'gpt-4o-mini'),
        'image' => env('STORY_MODEL_IMAGE', 'dall-e-3'),
        'tts' => env('STORY_MODEL_TTS', 'tts-1'),
        'tts_voice' => env('STORY_TTS_VOICE', 'nova'),
    ],

    'credit_costs' => [
        'text' => (int) env('STORY_CREDIT_TEXT', 5),
        'image' => (int) env('STORY_CREDIT_IMAGE', 3),
        'audio' => (int) env('STORY_CREDIT_AUDIO', 2),
        'video' => (int) env('STORY_CREDIT_VIDEO', 10),
    ],

    'admin_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('STORY_ADMIN_EMAILS', '')),
    ))),

];
