<?php

// app/Services/UpstoxHistoryService.php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class UpstoxHistoryService
{
    protected string $baseUrl;
    protected string $token;

    public function __construct()
    {
        $this->baseUrl = 'https://api.upstox.com';
        $this->token   = config('services.upstox.access_token');
    }

    protected function client()
    {
        return Http::withToken($this->token)
                   ->acceptJson()
                   ->asJson();
    }

    /**
     * Get historical candles V3 for a given instrument and unit.
     *
     * @param  string      $instrumentKey  e.g. 'NSE_INDEX|Nifty 50'
     * @param  string      $unit           'days' or 'minutes'
     * @param  int|string  $interval       1 for days, 5 for 5min, etc.
     * @param  string      $fromDate       'YYYY-MM-DD'
     * @param  string      $toDate         'YYYY-MM-DD'
     * @return array        Array of candle arrays [ts, o, h, l, c, v, oi]
     */
    public function getHistoricalCandles(
        string $instrumentKey,
        string $unit,
        int|string $interval,
        string $fromDate,
        string $toDate
    ): array {
        $path = sprintf(
            '/v3/historical-candle/%s/%s/%s/%s/%s',
            rawurlencode($instrumentKey),
            $unit,
            $interval,
            $toDate,
            $fromDate
        );

        $response = $this->client()->get($this->baseUrl.$path);
        $response->throw();

        return $response->json('data.candles') ?? [];
    }
}
