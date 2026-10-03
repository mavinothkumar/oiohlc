<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Market Data Provider Configuration
    |--------------------------------------------------------------------------
    */
    'default_provider' => env('OPTIONS_MARKET_PROVIDER', 'mock'),

    'providers' => [
        'mock' => [
            'class' => \App\Domain\Market\Providers\MockMarketDataProvider::class,
        ],
        'dhan' => [
            'class' => \App\Domain\Market\Providers\DhanMarketDataProvider::class,
            'client_id' => env('DHAN_CLIENT_ID', ''),
            'access_token' => env('DHAN_ACCESS_TOKEN', ''),
        ],
    ],

    'cache_ttl_seconds' => 15,
];
