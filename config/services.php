<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'organization' => env('OPENAI_ORGANIZATION'),
    ],

    'runway' => [
        'api_key' => env('RUNWAY_API_KEY'),
        'version' => env('RUNWAY_API_VERSION', '2024-11-06'),
        'model' => env('RUNWAY_MODEL', 'gen4_turbo'),
        'ratio' => env('RUNWAY_RATIO', '1280:720'),
        'duration_seconds' => (int) env('RUNWAY_DURATION_SECONDS', 10),
        'min_duration_seconds' => (int) env('RUNWAY_MIN_DURATION_SECONDS', 5),
        'max_duration_seconds' => (int) env('RUNWAY_MAX_DURATION_SECONDS', 10),
        'poll_interval_ms' => (int) env('RUNWAY_POLL_INTERVAL_MS', 2000),
        'max_polls' => (int) env('RUNWAY_MAX_POLLS', 120),
    ],

    'ffmpeg' => [
        'binary' => env('FFMPEG_BINARY', 'ffmpeg'),
    ],

    'cloudinary' => [
        /*
         * Prefer CLOUDINARY_URL (Dashboard → API Keys → “API environment variable”):
         * cloudinary://API_KEY:API_SECRET@CLOUD_NAME
         */
        'url' => env('CLOUDINARY_URL'),
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key' => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
        'folder' => env('CLOUDINARY_FOLDER', 'ai-story-book'),
        /** Unsigned uploads from the browser only; server uploads use API key + secret. */
        'upload_preset' => env('CLOUDINARY_UPLOAD_PRESET'),
    ],

    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'secret' => env('PAYPAL_SECRET'),
        'sandbox' => filter_var(env('PAYPAL_SANDBOX', true), FILTER_VALIDATE_BOOL),
    ],

];
