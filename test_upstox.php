<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$token = config('services.upstox.analytics_token');
echo "Token: $token\n";

$url = 'https://api.upstox.com/v3/feed/market-data-feed/authorize';
echo "Testing $url\n";
$response = Illuminate\Support\Facades\Http::withHeaders([
    'Accept' => 'application/json',
    'Authorization' => 'Bearer ' . $token,
])->get($url);

echo $response->status() . "\n";
echo $response->body() . "\n";

$url2 = 'https://api.upstox.com/v2/feed/market-data-feed/authorize';
echo "Testing $url2\n";
$response2 = Illuminate\Support\Facades\Http::withHeaders([
    'Accept' => 'application/json',
    'Authorization' => 'Bearer ' . $token,
])->get($url2);
echo $response2->status() . "\n";
echo $response2->body() . "\n";
