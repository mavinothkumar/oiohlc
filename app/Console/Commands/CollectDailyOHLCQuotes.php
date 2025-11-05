<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Instrument;
use App\Models\FullMarketQuote;
use App\Models\DailyOhlcQuote;
use Carbon\Carbon;

class CollectDailyOHLCQuotes extends Command
{
    protected $signature = 'quotes:collect-daily-ohlc';
    protected $description = 'Collect previous day OHLC for Nifty, BankNifty, Sensex, Nifty 50 stocks, using Upstox Batch OHLC API v3';

    public function handle()
    {
        $symbols = ['NIFTY', 'BANKNIFTY', 'SENSEX'];
        $types   = ['FUT', 'CE', 'PE', 'INDEX'];

        info('Starting CollectDailyOHLCQuotes: ' . \Illuminate\Support\Carbon::now());
        $this->info('Starting CollectDailyOHLCQuotes: ' . \Illuminate\Support\Carbon::now());

        // Get last trading date based on actual data
        $last_trading_timestamp = FullMarketQuote::orderBy('timestamp', 'desc')->value('timestamp');

        $trading_date           = $last_trading_timestamp
            ? Carbon::parse($last_trading_timestamp)->format('Y-m-d')
            : Carbon::yesterday()->format('Y-m-d');

        $accessToken = config('services.upstox.access_token');
        $baseUrl     = 'https://api.upstox.com/v3/market-quote/ohlc';

        // Get F&O instruments
        $instruments = Instrument::where(function ($q) use ($symbols) {
            $q->whereIn('underlying_symbol', $symbols)
              ->orWhereIn('trading_symbol', $symbols);
        })
                                 ->whereIn('instrument_type', $types)
                                 ->get();

        // Add Index spot instruments as objects
        $indexSpotNames  = ['Nifty 50', 'Nifty Bank', 'BSE SENSEX'];
        $indexSpotModels = Instrument::where('instrument_type', 'INDEX')
                                     ->whereIn('name', $indexSpotNames)
                                     ->get();

        // Add Nifty 50 stock instruments as objects (using your helper/static method)
        $nifty50List        = FullMarketQuotesCollectCommand::nifty50List();
        $nifty50StockModels = Instrument::where('instrument_type', 'EQ')
                                        ->whereIn('trading_symbol', $nifty50List)
                                        ->get();

        // Merge all instrument objects for fast lookups
        $allInstruments = collect()
            ->merge($instruments)
            ->merge($indexSpotModels)
            ->merge($nifty50StockModels)
            ->keyBy('instrument_key');

        // Gather all keys for API batch
        $allInstrumentKeys = $allInstruments->keys()->toArray();
        $chunks            = array_chunk($allInstrumentKeys, 500);

        foreach ($chunks as $batch) {
            $params = [
                'instrument_key' => implode(',', $batch),
                'interval'       => '1d',
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
                $instrument_token = $entry['instrument_token'] ?? $symbol_key;
                if ( ! $live) {
                    continue;
                }

                $inst = $allInstruments[$instrument_token] ?? null;

                // Set symbol name and type correctly (for Index/EQ fallback)
                if ($inst && $inst->instrument_type === 'INDEX') {
                    $symbol_name = $inst->name ?? $symbol_key;
                    $option_type = 'INDEX';
                } elseif ($inst && $inst->instrument_type === 'EQ') {
                    $symbol_name = $inst->trading_symbol ?? $symbol_key;
                    $option_type = 'EQ';
                } else {
                    $symbol_name = $inst->underlying_symbol ?? $inst->trading_symbol ?? $symbol_key;
                    $option_type = $inst->instrument_type ?? null;
                }

                DailyOhlcQuote::updateOrCreate([
                    'symbol_name'    => $symbol_name,
                    'instrument_key' => $instrument_token,
                    'expiry'         => $inst->expiry ?? null,
                    'strike'         => $inst->strike_price ?? null,
                    'option_type'    => $option_type,
                    'quote_date'     => $trading_date,
                ], [
                    'open'          => $live['open'] ?? null,
                    'high'          => $live['high'] ?? null,
                    'low'           => $live['low'] ?? null,
                    'close'         => $live['close'] ?? null,
                    'volume'        => $live['volume'] ?? null,
                    'open_interest' => null,
                ]);

                $this->info("Stored daily OHLC for $symbol_name ($trading_date)");
            }
        }

        $this->info("Complete: Daily OHLC stored for $trading_date.");
    }
}
