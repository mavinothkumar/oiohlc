<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchOptionChainData extends Command {
    protected $signature = 'optionchain:fetch';
    protected $description = 'Fetch option chain data from Upstox API every 1 minute and aggregate every 5 minutes';

    public function handle(): int {
        $this->fetchAndStoreOptionChain();
        $this->aggregateFiveMinuteData();

        return self::SUCCESS;
    }

    private function fetchAndStoreOptionChain(): void {
        $instruments = [
            [ 'key' => 'NSE_INDEX|Nifty 50', 'symbol' => 'NIFTY' ],
            // ['key' => 'BSE_INDEX|SENSEX', 'symbol' => 'SENSEX'],
            // ['key' => 'NSE_INDEX|Nifty Bank', 'symbol' => 'BANKNIFTY'],
            // ['key' => 'NSE_INDEX|Nifty Fin Service', 'symbol' => 'FINNIFTY'],
        ];

        $token = config( 'services.upstox.analytics_token' );
        $now   = now()->copy()->second( 0 );

        foreach ( $instruments as $inst ) {
            // Get both current and next expiries using orWhere
            $expiries = DB::table( 'nse_expiries' )
                          ->where( 'trading_symbol', $inst['symbol'] )
                          ->where( function($q) {
                              $q->where( 'is_current', 1 )
                                ->orWhere( 'is_next', 1 );
                          })
                          ->where( 'instrument_type', 'OPT' )
                          ->orderBy('expiry_date')
                          ->get();

            info('$expiries',[$expiries]);

            if ( $expiries->isEmpty() ) {
                Log::warning( "No current or next expiry found for {$inst['symbol']}" );
                continue;
            }

            // Loop through each expiry (current and next)
            foreach ($expiries as $expiryData) {
                $expiry = $expiryData->expiry_date;
                $expiryType = $expiryData->is_current ? 'current' : 'next';

                Log::info( "Fetching {$expiryType} expiry data for {$inst['symbol']} on {$expiry}" );

                $response = Http::withHeaders( [
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                ] )->get( 'https://api.upstox.com/v2/option/chain', [
                    'instrument_key' => $inst['key'],
                    'expiry_date'    => $expiry,
                ] );

                if ( ! $response->ok() ) {
                    Log::error( "Option chain API error for {$inst['symbol']} ({$expiryType} expiry)", [
                        'expiry'  => $expiry,
                        'status'  => $response->status(),
                        'body'    => $response->body(),
                    ] );
                    continue;
                }

                $data = $response->json( 'data' ) ?? [];
                if ( empty( $data ) ) {
                    Log::error( "Empty option chain data for {$inst['symbol']} ({$expiryType} expiry)" );
                    continue;
                }

                // Get latest captured_at for this specific expiry
                $latestCapturedAt = DB::table( 'option_chains' )
                                      ->where('expiry', $expiry)
                                      ->where('trading_symbol', $inst['symbol'])
                                      ->max( 'captured_at' );

                $prevData = collect();

                if ( $latestCapturedAt ) {
                    $prevData = DB::table( 'option_chains' )
                                  ->where( 'captured_at', $latestCapturedAt )
                                  ->where('expiry', $expiry)
                                  ->where('trading_symbol', $inst['symbol'])
                                  ->get( [ 'instrument_key', 'oi', 'ltp', 'volume' ] )
                                  ->keyBy( 'instrument_key' );
                }

                $records = [];
                foreach ( $data as $item ) {
                    $ceRecord = $this->buildRecord( $item, $prevData, $inst['symbol'], 'CE', $now, $expiry, $expiryType );
                    $peRecord = $this->buildRecord( $item, $prevData, $inst['symbol'], 'PE', $now, $expiry, $expiryType );

                    if ($ceRecord) $records[] = $ceRecord;
                    if ($peRecord) $records[] = $peRecord;
                }

                if (!empty($records)) {
                    foreach ( array_chunk( $records, 500 ) as $chunk ) {
                        DB::table( 'option_chains' )->insert( $chunk );
                    }
                    Log::info( "Stored " . count($records) . " records for {$inst['symbol']} ({$expiryType} expiry: {$expiry})" );
                }
            }
        }
    }

    private function buildRecord( array $item, $prevData, string $symbol, string $type, Carbon $now, string $expiry, string $expiryType = 'current' ): array {
        $optData = $type === 'CE' ? ( $item['call_options'] ?? null ) : ( $item['put_options'] ?? null );

        if ( ! $optData ) {
            return [];
        }

        $m             = $optData['market_data'] ?? [];
        $g             = $optData['option_greeks'] ?? [];
        $instrumentKey = $optData['instrument_key'] ?? null;

        if ( ! $instrumentKey ) {
            return [];
        }

        $prevRecord = $prevData[ $instrumentKey ] ?? null;

        $diffOi     = $prevRecord ? ( (int) ( $m['oi'] ?? 0 ) - (int) ( $prevRecord->oi ?? 0 ) ) : null;
        $diffVolume = $prevRecord ? ( (int) ( $m['volume'] ?? 0 ) - (int) ( $prevRecord->volume ?? 0 ) ) : null;
        $diffLtp    = $prevRecord ? ( (float) ( $m['ltp'] ?? 0 ) - (float) ( $prevRecord->ltp ?? 0 ) ) : null;

        $buildUp = null;
        if ( ! is_null( $diffOi ) && ! is_null( $diffLtp ) && $diffOi != 0 && $diffLtp != 0 ) {
            if ( $diffLtp > 0 && $diffOi > 0 ) {
                $buildUp = 'Long Build';
            } elseif ( $diffLtp < 0 && $diffOi > 0 ) {
                $buildUp = 'Short Build';
            } elseif ( $diffLtp > 0 && $diffOi < 0 ) {
                $buildUp = 'Short Cover';
            } elseif ( $diffLtp < 0 && $diffOi < 0 ) {
                $buildUp = 'Long Unwind';
            }
        }

        return [
            'underlying_key'        => $item['underlying_key'] ?? null,
            'instrument_key'        => $instrumentKey,
            'trading_symbol'        => $symbol,
            'expiry'                => $expiry,
            //'expiry_type'           => $expiryType,
            'strike_price'          => $item['strike_price'] ?? null,
            'option_type'           => $type,
            'ltp'                   => $m['ltp'] ?? null,
            'volume'                => $m['volume'] ?? null,
            'oi'                    => $m['oi'] ?? null,
            'close_price'           => $m['close_price'] ?? null,
            'bid_price'             => $m['bid_price'] ?? null,
            'bid_qty'               => $m['bid_qty'] ?? null,
            'ask_price'             => $m['ask_price'] ?? null,
            'ask_qty'               => $m['ask_qty'] ?? null,
            'prev_oi'               => $m['prev_oi'] ?? null,
            'vega'                  => $g['vega'] ?? null,
            'theta'                 => $g['theta'] ?? null,
            'gamma'                 => $g['gamma'] ?? null,
            'delta'                 => $g['delta'] ?? null,
            'iv'                    => $g['iv'] ?? null,
            'pop'                   => $g['pop'] ?? null,
            'underlying_spot_price' => $item['underlying_spot_price'] ?? null,
            'pcr'                   => $item['pcr'] ?? null,
            'captured_at'           => $now,
            'created_at'            => $now,
            'updated_at'            => $now,
            'diff_oi'               => $diffOi,
            'diff_volume'           => $diffVolume,
            'diff_ltp'              => $diffLtp,
            'build_up'              => $buildUp,
        ];
    }

    public function aggregateFiveMinuteData(): void
    {

        $now             = now()->copy()->second(0);
        $currentBucket   = $now->copy()->minute(intdiv((int) $now->format('i'), 5) * 5)->second(0);
        $completedBucket = $currentBucket->copy()->subMinutes(5);

        // Guard: nothing to process before 09:20
        if ($now->lt(today()->setTime(9, 20))) {
            info('aggregateFiveMinuteData skipped: before 09:20');
            return;
        }

        $underlyings = [
            ['symbol' => 'NIFTY', 'exchange' => 'NSE'],
            // ['symbol' => 'BANKNIFTY', 'exchange' => 'NSE'],
            // ['symbol' => 'FINNIFTY',  'exchange' => 'NSE'],
            // ['symbol' => 'SENSEX',    'exchange' => 'BSE'],
        ];

        foreach ($underlyings as $inst) {
            // Get both current and next expiries for options
            $optExpiries = DB::table('nse_expiries')
                             ->where('trading_symbol', $inst['symbol'])
                             ->where('instrument_type', 'OPT')
                             ->where(function($query) {
                                 $query->where('is_current', 1)
                                       ->orWhere('is_next', 1);
                             })
                             ->orderBy('expiry_date')
                             ->get();

            $futExpiry = DB::table('nse_expiries')
                           ->where('trading_symbol', $inst['symbol'])
                           ->where('is_current', 1)
                           ->where('instrument_type', 'FUT')
                           ->first();

            $instrumentRows = collect();

            // Process each option expiry (current and next)
            foreach ($optExpiries as $optExpiry) {
                $instrumentRows = $instrumentRows->merge(
                    DB::table('instruments')
                      ->where('underlying_symbol', $inst['symbol'])
                      ->where('expiry', $optExpiry->expiry)
                      ->whereIn('instrument_type', ['CE', 'PE'])
                      ->get(['instrument_key', 'instrument_type', 'strike_price', 'expiry'])
                      ->map(function($item) use ($optExpiry) {
                          $item->expiry_date = $optExpiry->expiry_date;
                          $item->expiry_type = $optExpiry->is_current ? 'current' : 'next';
                          return $item;
                      })
                );
            }

            if ($futExpiry) {
                $instrumentRows = $instrumentRows->merge(
                    DB::table('instruments')
                      ->where('underlying_symbol', $inst['symbol'])
                      ->where('expiry', $futExpiry->expiry)
                      ->where('instrument_type', 'FUT')
                      ->get(['instrument_key', 'instrument_type', 'strike_price', 'expiry'])
                      ->map(function($item) use ($futExpiry) {
                          $item->expiry_date = $futExpiry->expiry_date;
                          $item->expiry_type = 'current';
                          return $item;
                      })
                );
            }

            if ($instrumentRows->isEmpty()) {
                Log::warning("No CE/PE/FUT instruments for {$inst['symbol']}");
                continue;
            }

            $lastTimestamp = DB::table('ohlc_live_snapshots')
                               ->where('underlying_symbol', $inst['symbol'])
                               ->where('interval', '5minute')
                               ->whereDate('timestamp', today())
                               ->max('timestamp');

            $startBucket = $lastTimestamp
                ? Carbon::parse($lastTimestamp)->addMinutes(5)->startOfMinute()
                : today()->setTime(9, 15);

            $startBucket = $startBucket->copy()->minute(intdiv((int) $startBucket->format('i'), 5) * 5)->second(0);

            for ($bucket = $startBucket->copy(); $bucket->lte($completedBucket); $bucket->addMinutes(5)) {
                $windowStart = $bucket->copy();
                $windowEnd   = $bucket->copy()->addMinutes(4)->endOfMinute();

                // Get chain data for each expiry separately
                $chainMap = collect();

                foreach ($optExpiries as $optExpiry) {
                    $chainCapturedAt = DB::table('option_chains')
                                         ->where('trading_symbol', $inst['symbol'])
                                         ->where('expiry', $optExpiry->expiry)
                                         ->whereBetween('captured_at', [$windowStart, $windowEnd])
                                         ->max('captured_at');

                    if ($chainCapturedAt) {
                        $expiryChainMap = DB::table('option_chains')
                                            ->where('trading_symbol', $inst['symbol'])
                                            ->where('expiry', $optExpiry->expiry)
                                            ->where('captured_at', $chainCapturedAt)
                                            ->get([
                                                'strike_price',
                                                'option_type',
                                                'oi',
                                                'volume',
                                                'diff_oi',
                                                'diff_volume',
                                                'diff_ltp',
                                                'build_up',
                                            ])
                                            ->keyBy(fn ($row) => number_format((float) $row->strike_price, 2, '.', '') . '|' . $row->option_type . '|' . $optExpiry->expiry);

                        $chainMap = $chainMap->merge($expiryChainMap);
                    }
                }

                $rows = [];

                foreach ($instrumentRows as $instrument) {
                    $candles = DB::table('ohlc_quotes')
                                 ->where('instrument_key', $instrument->instrument_key)
                                 ->whereBetween('ts_at', [$windowStart, $windowEnd])
                                 ->orderBy('ts_at')
                                 ->get();

                    if ($candles->isEmpty()) {
                        continue;
                    }

                    $chain = null;
                    if (in_array($instrument->instrument_type, ['CE', 'PE'], true)) {
                        $chainKey = number_format((float) $instrument->strike_price, 2, '.', '') . '|' . $instrument->instrument_type . '|' . $instrument->expiry;
                        $chain    = $chainMap->get($chainKey);
                    }

                    $rows[] = [
                        'instrument_key'    => $instrument->instrument_key,
                        'underlying_symbol' => $inst['symbol'],
                        'expiry_date'       => $instrument->expiry_date ?? null,
                        //'expiry_type'       => $instrument->expiry_type ?? 'current',
                        'strike'            => $instrument->strike_price,
                        'instrument_type'   => $instrument->instrument_type,
                        'open'              => $candles->first()->open,
                        'high'              => $candles->max('high'),
                        'low'               => $candles->min('low'),
                        'close'             => $candles->last()->close,
                        'oi'                => $chain?->oi,
                        'volume'            => $chain?->volume,
                        'diff_oi'           => $chain?->diff_oi,
                        'diff_volume'       => $chain?->diff_volume,
                        'diff_ltp'          => $chain?->diff_ltp,
                        'build_up'          => $chain?->build_up,
                        'exchange'          => $inst['exchange'],
                        'interval'          => '5minute',
                        'timestamp'         => $bucket->copy(),
                        'created_at'        => $now,
                        'updated_at'        => $now,
                    ];
                }

                if (!empty($rows)) {
                    foreach (array_chunk($rows, 500) as $chunk) {
                        DB::table('ohlc_live_snapshots')->upsert(
                            $chunk,
                            ['instrument_key', 'timestamp'],
                            ['open', 'high', 'low', 'close', 'oi', 'volume', 'build_up', 'diff_oi', 'diff_volume', 'diff_ltp', 'updated_at']
                        );
                    }
                    Log::info("5-min candles stored for {$inst['symbol']} | bucket: {$bucket->toTimeString()} | rows: " . count($rows));
                } else {
                    Log::warning("No 5-min candles built for {$inst['symbol']} | bucket: {$bucket->toTimeString()}");
                }
            }
        }

        info('aggregateFiveMinuteData end');
    }
}
