<?php

return [
    /*
    |--------------------------------------------------------------------------
    | JVZoo IPN secret
    |--------------------------------------------------------------------------
    |
    | This must match the JVZoo secret configured in your JVZIPN settings.
    |
    */
    'secret' => env('JVZOO_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Product to tier mapping
    |--------------------------------------------------------------------------
    |
    | Map JVZoo product IDs to your internal user tier.
    | Fill these env values when you share your real product codes.
    |
    */
    'product_tiers' => [
        env('JVZOO_PRODUCT_BASIC_ID', 'BASIC_PRODUCT_ID') => 'basic',
        env('JVZOO_PRODUCT_PRO_ID', 'PRO_PRODUCT_ID') => 'pro',
        env('JVZOO_PRODUCT_ELITE_ID', 'ELITE_PRODUCT_ID') => 'elite',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default credits per tier
    |--------------------------------------------------------------------------
    */
    'tier_credits' => [
        'basic' => (int) env('JVZOO_BASIC_CREDITS', 30),
        'pro' => (int) env('JVZOO_PRO_CREDITS', 150),
        'elite' => (int) env('JVZOO_ELITE_CREDITS', 500),
    ],
];
