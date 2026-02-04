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
        'access_token' => 'eyJ0eXAiOiJKV1QiLCJrZXlfaWQiOiJza192MS4wIiwiYWxnIjoiSFMyNTYifQ.eyJzdWIiOiI0R0NVTjQiLCJqdGkiOiI2OTgyYWQ3ODFmNWJkMTYyNzRhMDQyZjQiLCJpc011bHRpQ2xpZW50IjpmYWxzZSwiaXNQbHVzUGxhbiI6dHJ1ZSwiaWF0IjoxNzcwMTcxNzY4LCJpc3MiOiJ1ZGFwaS1nYXRld2F5LXNlcnZpY2UiLCJleHAiOjE3NzAyNDI0MDB9.5MpuO3n8D5QbHPtTZoJiwb35FuYy1uZ467EzCSE7Mcs',
        'history_access_token' => 'eyJ0eXAiOiJKV1QiLCJrZXlfaWQiOiJza192MS4wIiwiYWxnIjoiSFMyNTYifQ.eyJzdWIiOiI0R0NVTjQiLCJqdGkiOiI2OTZiODc5NjBiMjljZDEyYjIwMjkwMDciLCJpc011bHRpQ2xpZW50IjpmYWxzZSwiaXNQbHVzUGxhbiI6dHJ1ZSwiaWF0IjoxNzY4NjU0NzQyLCJpc3MiOiJ1ZGFwaS1nYXRld2F5LXNlcnZpY2UiLCJleHAiOjE3Njg2ODcyMDB9.-urVJCU99QOsKFqOTrfl2KwfDsusRZdUMAzUWWa1Drw',
        'history_access_token_1' => 'eyJ0eXAiOiJKV1QiLCJrZXlfaWQiOiJza192MS4wIiwiYWxnIjoiSFMyNTYifQ.eyJzdWIiOiI0R0NVTjQiLCJqdGkiOiI2OTZiODdhMjNkODJjNDc2OGI2OGNhODgiLCJpc011bHRpQ2xpZW50IjpmYWxzZSwiaXNQbHVzUGxhbiI6dHJ1ZSwiaWF0IjoxNzY4NjU0NzU0LCJpc3MiOiJ1ZGFwaS1nYXRld2F5LXNlcnZpY2UiLCJleHAiOjE3Njg2ODcyMDB9.wUCHW0hFHUu3-ejVBuPED_SyUGTExOfU0if20aXnRjQ',
        'client_id'    => env('UPSTOX_CLIENT_ID', ''),
        'segment'      => env('UPSTOX_EXCHANGE_SEGMENT', 'NSE_FNO'),
    ],
    // php artisan config:clear
    // php artisan optimize
];
