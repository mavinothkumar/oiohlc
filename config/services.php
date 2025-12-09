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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack'       => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'dhan'        => [
        'base_url'  => env('DHAN_BASE_URL', 'https://api.dhan.co/v2'),
        'token'     => env('DHAN_ACCESS_TOKEN'),
        'client_id' => env('DHAN_CLIENT_ID'),
        'segment'   => env('DHAN_EXCHANGE_SEGMENT', 'NSE_FNO'),
    ],
    'depth_proxy' => [
        'ws_url' => env('DEPTH_PROXY_WS_URL', 'ws://127.0.0.1:8081'),
        'http'   => env('DEPTH_PROXY_HTTP', 'http://127.0.0.1:8081'),
    ],
    'upstox'      => [
        'access_token' => 'eyJ0eXAiOiJKV1QiLCJrZXlfaWQiOiJza192MS4wIiwiYWxnIjoiSFMyNTYifQ.eyJzdWIiOiI0R0NVTjQiLCJqdGkiOiI2OTM3OTQ0NjQ1MjY4MzczOTgyZWQyNTciLCJpc011bHRpQ2xpZW50IjpmYWxzZSwiaXNQbHVzUGxhbiI6dHJ1ZSwiaWF0IjoxNzY1MjUwMTE4LCJpc3MiOiJ1ZGFwaS1nYXRld2F5LXNlcnZpY2UiLCJleHAiOjE3NjUzMTc2MDB9.U3SiD2JtAR0N52KxMx7tE2zGqW-faFomkj3atMzKJpI',
        'client_id'    => env('UPSTOX_CLIENT_ID', ''),
        'segment'      => env('UPSTOX_EXCHANGE_SEGMENT', 'NSE_FNO'),
    ],
    // php artisan config:clear
    // php artisan optimize
];
