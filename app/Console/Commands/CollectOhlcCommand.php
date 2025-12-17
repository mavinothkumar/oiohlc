<?php

namespace App\Console\Commands;

use App\Models\Expiry;
use App\Models\Instrument;
use App\Models\Ohlc5mQuote;
use App\Models\OhlcDayQuote;
use App\Models\OhlcQuote;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class CollectOhlcCommand extends Command
{
    protected $signature = 'market:collect-ohlc';
    protected $description = 'Collect 1-minute live OHLC data from Upstox for current expiry indices and options';

    public function handle(): int
    {
        $this->info('Collecting current-expiry instruments...'.now());
        info('Collecting OHLC '.now());

        // 1) Get current expiries for index and options
        $expiries = Expiry::query()
                          ->where('is_current', 1)
                          ->whereIn('trading_symbol', ['NIFTY', 'BANKNIFTY', 'SENSEX']) // 'FINNIFTY'
                          ->whereIn('instrument_type', ['FUT', 'OPT'])
                          ->get();

        if ($expiries->isEmpty()) {
            $this->warn('No current expiries found.');

            return self::SUCCESS;
        }

        // 2) Map (trading_symbol, expiry) to expiry_date
        $this->info('Resolving instruments for current expiries...');

        $instrumentMeta = []; // instrument_key => [instrument_type, expiry_date]

        foreach ($expiries as $expiry) {
            $query = Instrument::query()
                               ->where('name', $expiry->trading_symbol);

            if ( ! is_null($expiry->expiry ?? null)) {
                $query->where('expiry', $expiry->expiry);
            }

            $instruments = $query->get(['instrument_key', 'instrument_type', 'strike_price', 'name']);

            foreach ($instruments as $instrument) {
                $instrumentMeta[$instrument->instrument_key] = [
                    'trading_symbol'  => $instrument->name,
                    'instrument_type' => $instrument->instrument_type,
                    'strike_price'    => $instrument->strike_price,
                    'expiry_date'     => $expiry->expiry_date instanceof \Carbon\Carbon
                        ? $expiry->expiry_date->toDateString()
                        : $expiry->expiry_date,
                ];
            }
        }

        if (empty($instrumentMeta)) {
            $this->warn('No instruments found for current expiries.');

            return self::SUCCESS;
        }

        $index          = Instrument::where('instrument_type', 'INDEX')->get([
            'instrument_type', 'expiry as expiry_date', 'instrument_key', 'strike_price', 'name as trading_symbol',
        ])->keyBy('instrument_key')->toArray();
        $instrumentMeta = array_merge($index, $instrumentMeta);
        $instrumentKeys = array_keys($instrumentMeta);

        $this->info('Total instruments to query: '.count($instrumentKeys));

        foreach (['1d', 'I1'] as $interval) {
            info($interval.' start '.now());
            $chunks = array_chunk($instrumentKeys, 500);

            $accessToken = config('services.upstox.access_token');
            if ( ! $accessToken) {
                $this->error('Upstox access token not configured (services.upstox.access_token).');

                return self::FAILURE;
            }

            foreach ($chunks as $chunkIndex => $chunkKeys) {
                $this->info(sprintf('Fetching OHLC for chunk %d with %d instruments...', $chunkIndex + 1, count($chunkKeys)));
                info(sprintf('Fetching OHLC for chunk %d with %d instruments...', $chunkIndex + 1, count($chunkKeys)));

                $instrumentKeyParam = implode(',', $chunkKeys);

                $response = Http::withHeaders([
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                    'Authorization' => 'Bearer '.$accessToken,
                ])
                                ->timeout(10)
                                ->get('https://api.upstox.com/v3/market-quote/ohlc', [
                                    'instrument_key' => $instrumentKeyParam,
                                    'interval'       => $interval,
                                ]);

                if ( ! $response->ok()) {
                    $this->error('Upstox API error: HTTP '.$response->status());
                    continue;
                }

                $body = $response->json();

                if ( ! isset($body['status']) || $body['status'] !== 'success' || ! isset($body['data'])) {
                    $this->error('Unexpected Upstox response format.');
                    continue;
                }

                $data = $body['data'];


                foreach ($data as $instrumentKey => $quote) {
                    if ( ! isset($instrumentMeta[$quote['instrument_token']])) {
                        continue;
                    }

                    $meta = $instrumentMeta[$quote['instrument_token']];

                    $live = $quote['live_ohlc'] ?? null;
                    if ( ! $live) {
                        continue;
                    }

                    $tsMs = $live['ts'] ?? null;
                    $tsAt = null;
                    if ($tsMs) {
                        $tsAt = Carbon::createFromTimestampMs($tsMs)->setTimezone(config('app.timezone'));
                    }

                    // Common data array for reuse
                    $commonData = [
                        'instrument_type' => $meta['instrument_type'],
                        'expiry_date'     => $meta['expiry_date'] ?? null,
                        'strike_price'    => $meta['strike_price'] ?? null,
                        'trading_symbol'  => $meta['trading_symbol'] ?? null,

                        'open'       => $live['open'] ?? null,
                        'high'       => $live['high'] ?? null,
                        'low'        => $live['low'] ?? null,
                        'close'      => $live['close'] ?? null,
                        'volume'     => $live['volume'] ?? null,
                        'ts_at'      => $tsAt,
                        'last_price' => $quote['last_price'] ?? null,
                    ];

                    if ($interval === '1d') {
                        OhlcDayQuote::updateOrCreate(
                            [
                                'instrument_key' => $quote['instrument_token'],
                                'ts'             => $tsMs,
                            ],
                            $commonData
                        );
                    }
                    if ($interval === 'I1') {
                        // Store 1-minute data (OhlcQuote)
                        OhlcQuote::updateOrCreate(
                            [
                                'instrument_key' => $quote['instrument_token'],
                                'ts'             => $tsMs,
                            ],
                            $commonData
                        );

                        // 5-MINUTE LOGIC WITH ERROR CORRECTION
                        $currentTsAt = Carbon::createFromTimestampMs($tsMs);
                        $minute      = $currentTsAt->format('i');

                        // Check if this is a 5-minute boundary (9:20, 9:25, 9:30, etc.)
                        if ((int) $minute % 5 === 0) {

                            Ohlc5mQuote::updateOrCreate(
                                [
                                    'instrument_key' => $quote['instrument_token'],
                                    'ts'             => $tsMs,
                                ],
                                $commonData
                            );
                        }
                    }
                }
            }
            $this->info('OHLC collection of '.$interval.' completed.');
            info($interval.' End '.now());
        }
        $this->info('OHLC collection complete.'.now());
        info('OHLC collection complete '.now());

        return self::SUCCESS;
    }
}
