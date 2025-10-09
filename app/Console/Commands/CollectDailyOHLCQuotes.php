<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Instrument;
use App\Models\DailyOhlcQuote;
use Carbon\Carbon;

class CollectDailyOHLCQuotes extends Command
{
    protected $signature = 'quotes:collect-daily-ohlc';
    protected $description = 'Collect previous day OHLC for Nifty, BankNifty, Sensex Futures & Options using Upstox Batch OHLC API v3';

    public function handle()
    {
        $symbols = ['NIFTY', 'BANKNIFTY', 'SENSEX'];
        $types = ['FUT', 'CE', 'PE', 'INDEX'];
        $last_trading_timestamp = \App\Models\FullMarketQuote::orderBy('timestamp', 'desc')->value('timestamp');
        $yesterday              = $last_trading_timestamp
            ? \Carbon\Carbon::parse($last_trading_timestamp)->format('Y-m-d')
            : Carbon::yesterday()->format('Y-m-d');
        $accessToken            = config('services.upstox.access_token');
        $baseUrl                = 'https://api.upstox.com/v3/market-quote/ohlc';

        $instruments = Instrument::where(function ($q) use ($symbols) {
            $q->whereIn('underlying_symbol', $symbols)
              ->orWhereIn('trading_symbol', $symbols);
        })
                                 ->whereIn('instrument_type', $types)
                                 ->get();

        $instrument_keys = $instruments->pluck('instrument_key')->unique()->values()->toArray();

        $indexSpotNames = ['Nifty 50', 'Nifty Bank', 'BSE SENSEX'];
        $indexSpotKeys  = Instrument::where('instrument_type', 'INDEX')
                                    ->whereIn('name', $indexSpotNames)
                                    ->pluck('instrument_key')
                                    ->unique()
                                    ->toArray();

// Combine all instrument keys (remove duplicates)
        $allInstrumentKeys = array_unique(array_merge($instrument_keys, $indexSpotKeys));

        $chunks = array_chunk($allInstrumentKeys, 500);

        foreach ($chunks as $batch) {
            $params = [
                'instrument_key' => implode(',', $batch),
                'interval'       => '1d', // or 'day'
            ];

            $response = Http::withToken($accessToken)
                            ->withHeaders([
                                'Accept'       => 'application/json',
                                'Content-Type' => 'application/json',
                            ])
                            ->get($baseUrl, $params);

            $data = $response->json('data');
            if (empty($data)) {
                $this->warn('No OHLC data returned for batch.');
                continue;
            }

            foreach ($data as $symbol_key => $entry) {
                $live             = $entry['live_ohlc'] ?? null;
                $instrument_token = $entry['instrument_token'] ?? null;
                if ( ! $live) {
                    continue;
                }

                // Find instrument for additional info
                $inst = $instruments->first(function ($row) use ($instrument_token) {
                    return $row->instrument_key == $instrument_token;
                });
                $isIndex = $inst && $inst->instrument_type === 'INDEX';
                DailyOhlcQuote::updateOrCreate([
                    'symbol_name'    => $inst ? ($isIndex ? $inst->name : $inst->underlying_symbol) : null,
                    'instrument_key' => $instrument_token,
                    'expiry'         => $inst ? $inst->expiry : null,
                    'strike'         => $inst ? ($inst->strike_price ?? null) : null,
                    'option_type'    => $inst ? ($isIndex ? 'INDEX' : ($inst->instrument_type ?? null)) : null,
                    'quote_date'     => $yesterday,
                ], [
                    'open'          => $live['open'] ?? null,
                    'high'          => $live['high'] ?? null,
                    'low'           => $live['low'] ?? null,
                    'close'         => $live['close'] ?? null,
                    'volume'        => $live['volume'] ?? null,
                    'open_interest' => null, // OI not available in this API's response
                ]);

                $this->info("Stored daily OHLC for $instrument_token ($yesterday)");
            }
        }

        $this->info("Complete: Daily OHLC (no OI in this API) stored for $yesterday.");
    }
}
