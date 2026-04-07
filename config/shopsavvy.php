<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ShopSavvy API Key
    |--------------------------------------------------------------------------
    |
    | Your ShopSavvy Data API key. Get one at https://shopsavvy.com/data
    | Set SHOPSAVVY_API_KEY in your .env file.
    |
    */
    'api_key' => env('SHOPSAVVY_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | API Base URL
    |--------------------------------------------------------------------------
    |
    | The base URL for the ShopSavvy Data API. You generally will not need
    | to change this.
    |
    */
    'base_url' => env('SHOPSAVVY_BASE_URL', 'https://api.shopsavvy.com/v1'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeout
    |--------------------------------------------------------------------------
    |
    | Number of seconds to wait for an API response before timing out.
    |
    */
    'timeout' => env('SHOPSAVVY_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Whether to cache API responses. When enabled, responses will be stored
    | using Laravel's cache system for the configured TTL in seconds.
    |
    */
    'cache' => [
        'enabled' => env('SHOPSAVVY_CACHE_ENABLED', true),
        'ttl'     => env('SHOPSAVVY_CACHE_TTL', 300),
        'store'   => env('SHOPSAVVY_CACHE_STORE', null), // null = default cache store
        'prefix'  => env('SHOPSAVVY_CACHE_PREFIX', 'shopsavvy'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | When enabled, the package registers optional API routes under the
    | configured prefix. Set to false to disable route registration entirely.
    |
    */
    'routes' => [
        'enabled'    => env('SHOPSAVVY_ROUTES_ENABLED', false),
        'prefix'     => env('SHOPSAVVY_ROUTES_PREFIX', 'api/shopsavvy'),
        'middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry
    |--------------------------------------------------------------------------
    |
    | Automatically retry failed requests with exponential backoff.
    |
    */
    'retry' => [
        'times' => env('SHOPSAVVY_RETRY_TIMES', 3),
        'sleep' => env('SHOPSAVVY_RETRY_SLEEP', 500), // milliseconds
    ],

];
