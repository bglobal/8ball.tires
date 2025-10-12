<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Shopify Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Shopify API integration
    |
    */

    'shop' => env('SHOPIFY_SHOP_DOMAIN'),
    'api_key' => env('SHOPIFY_API_KEY'),
    'api_secret' => env('SHOPIFY_API_SECRET'),
    'token' => env('SHOPIFY_ADMIN_TOKEN'),
    'api_version' => env('SHOPIFY_API_VERSION', '2025-07'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configuration for handling Shopify API rate limits
    |
    */

    'rate_limit' => [
        'retry_after' => 2, // seconds to wait before retry
        'max_retries' => 3, // maximum number of retries
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Shopify webhooks
    |
    */

    'webhooks' => [
        'verify_hmac' => env('SHOPIFY_VERIFY_WEBHOOK_HMAC', true),
        'secret' => env('SHOPIFY_WEBHOOK_SECRET'),
    ],
];
