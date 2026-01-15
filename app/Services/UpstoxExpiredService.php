<?php
// app/Services/UpstoxExpiredService.php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;

class UpstoxExpiredService
{
    protected string $baseUrl;
    protected string $token;

    public function __construct()
    {

        $token = Arr::random([
            config('services.upstox.history_access_token'),
            config('services.upstox.history_access_token_1'),
            config('services.upstox.access_token'),
        ]);

        $this->baseUrl ='https://api.upstox.com/v2';
        $this->token   = $token;
    }

    protected function client()
    {
        return Http::withToken($this->token)  // Authorization: Bearer
                   ->acceptJson()
                   ->asJson();
    }

    public function getNiftyExpiries(): array
    {
        $url = $this->baseUrl.'/expired-instruments/expiries';

        $response = $this->client()->get($url, [
            'instrument_key' => 'NSE_INDEX|Nifty 50',
        ]);

        $response->throw();

        return $response->json('data') ?? [];
    }

    public function getExpiredOptionContracts(string $expiry): array
    {
        $url = $this->baseUrl.'/expired-instruments/option/contract';

        $response = $this->client()->get($url, [
            'instrument_key' => 'NSE_INDEX|Nifty 50',
            'expiry_date'    => $expiry,   // YYYY-MM-DD
        ]);

        $response->throw();

        return $response->json('data') ?? [];
    }

    public function getExpiredHistoricalCandles(
        string $instrumentKey,
        string $interval,
        string $fromDate,
        string $toDate
    ): array {
        $path = sprintf(
            '/expired-instruments/historical-candle/%s/%s/%s/%s',
            $instrumentKey,
            $interval,
            $toDate,
            $fromDate
        );


        $response = $this->client()->get($this->baseUrl.$path);
        $response->throw();

        usleep(200_000); // 0.2s

        return $response->json('data.candles') ?? [];
    }
}
