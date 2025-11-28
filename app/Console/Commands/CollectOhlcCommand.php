<?php

namespace App\Console\Commands;

use App\Models\Expiry;
use App\Models\Instrument;
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
        $this->info('Collecting current-expiry instruments...');

        // 1) Get current expiries for index and options
        $expiries = Expiry::query()
                          ->where('is_current', 1)
                          ->whereIn('trading_symbol', ['NIFTY', 'BANKNIFTY', 'SENSEX', 'FINNIFTY'])
                          ->whereIn('instrument_type', ['FUT', 'OPT'])
                          ->get();

        if ($expiries->isEmpty()) {
            $this->warn('No current expiries found.');

            return self::SUCCESS;
        }

        // 2) Map (trading_symbol, expiry) to expiry_date
        // Assuming expiries table has columns: trading_symbol, instrument_type, expiry, expiry_date
        $this->info('Resolving instruments for current expiries...');

        $instrumentMeta = []; // instrument_key => [instrument_type, expiry_date]

        foreach ($expiries as $expiry) {
            $query = Instrument::query()
                               ->where('name', $expiry->trading_symbol);

            // If both tables share an "expiry" column (string code)
            if ( ! is_null($expiry->expiry ?? null)) {
                $query->where('expiry', $expiry->expiry);
            }

            // If instruments also store instrument_type, match it as well
//            if (!is_null($expiry->instrument_type ?? null)) {
//                $query->where('instrument_type', $expiry->instrument_type);
//            }

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
            // 3) Chunk into max 500 instrument keys per request
            $chunks = array_chunk($instrumentKeys, 500);

            $accessToken = config('services.upstox.access_token');
            if ( ! $accessToken) {
                $this->error('Upstox access token not configured (services.upstox.access_token).');

                return self::FAILURE;
            }

            foreach ($chunks as $chunkIndex => $chunkKeys) {
                $this->info(sprintf('Fetching OHLC for chunk %d with %d instruments...', $chunkIndex + 1, count($chunkKeys)));

                $instrumentKeyParam = implode(',', $chunkKeys);

                $response = Http::withHeaders([
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                    'Authorization' => 'Bearer '.$accessToken,
                ])
                                ->timeout(10)
                                ->get('https://api.upstox.com/v3/market-quote/ohlc', [
                                    'instrument_key' => $instrumentKeyParam,
                                    'interval'       => $interval, // always 1-min interval
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

                DB::beginTransaction();
                try {
                    foreach ($data as $instrumentKey => $quote) {
                        if ( ! isset($instrumentMeta[$quote['instrument_token']])) {
                            // Instrument not in our map, skip
                            continue;
                        }

                        $meta = $instrumentMeta[$quote['instrument_token']];

                        $live = $quote['live_ohlc'] ?? null;
                        if ( ! $live) {
                            // No live data
                            continue;
                        }

                        $tsMs = $live['ts'] ?? null;
                        $tsAt = null;
                        if ($tsMs) {
                            $tsAt = Carbon::createFromTimestampMs($tsMs)->setTimezone(config('app.timezone'));
                        }

                        // Upsert on instrument_key + ts to keep one row per candle
                        if ($interval === '1d') {
                            OhlcDayQuote::updateOrCreate(
                                [
                                    'instrument_key' => $quote['instrument_token'],
                                    'ts'             => $tsMs,
                                ],
                                [
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
                                ]
                            );
                        }
                        if ($interval === 'I1') {
                            OhlcQuote::updateOrCreate(
                                [
                                    'instrument_key' => $quote['instrument_token'],
                                    'ts'             => $tsMs,
                                ],
                                [
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
                                ]
                            );
                        }


                    }

                    DB::commit();
                } catch (\Throwable $e) {
                    DB::rollBack();
                    $this->error('DB error: '.$e->getMessage());
                }
            }
            $this->info('OHLC collection of '.$interval.' completed.');
        }
        $this->info('OHLC collection complete.');

        return self::SUCCESS;
    }
}
