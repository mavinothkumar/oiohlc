<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\FullMarketQuote;

class UpstoxMarketQuoteService
{
    protected $apiUrl = 'https://upstox.com/api/v2/market-quote';

    public function fetchAndStoreQuote($instrumentKey)
    {
        $response = Http::withToken(config('services.upstox.access_token'))
                        ->get("{$this->apiUrl}/{$instrumentKey}");

        if ($response->successful() && isset($response['data'][$instrumentKey])) {
            $data = $response['data'][$instrumentKey];

            // Map response to table fields
            FullMarketQuote::create([
                'instrument_token'      => $data['instrument_token'] ?? null,
                'symbol'               => $data['symbol'] ?? null,
                'last_price'           => $data['last_price'] ?? null,
                'volume'               => $data['volume'] ?? null,
                'average_price'        => $data['average_price'] ?? null,
                'oi'                   => $data['oi'] ?? null,
                'net_change'           => $data['net_change'] ?? null,
                'total_buy_quantity'   => $data['total_buy_quantity'] ?? null,
                'total_sell_quantity'  => $data['total_sell_quantity'] ?? null,
                'lower_circuit_limit'  => $data['lower_circuit_limit'] ?? null,
                'upper_circuit_limit'  => $data['upper_circuit_limit'] ?? null,
                'last_trade_time'      => $data['last_trade_time'] ?? null,
                'oi_day_high'          => $data['oi_day_high'] ?? null,
                'oi_day_low'           => $data['oi_day_low'] ?? null,
                'open'                 => $data['ohlc']['open'] ?? null,
                'high'                 => $data['ohlc']['high'] ?? null,
                'low'                  => $data['ohlc']['low'] ?? null,
                'close'                => $data['ohlc']['close'] ?? null,
                'timestamp'            => $data['timestamp'] ?? now(),
            ]);
        }
    }
}

