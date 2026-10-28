<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class  CollectOneMinOhlcCommand extends Command {
    protected $signature = 'ohlc:collect-1min';
    protected $description = 'Collect 1-minute OHLC from Upstox /market-quote/ohlc and store in ohlc_quotes';

    private array $instruments = [
        [ 'key' => 'NSE_INDEX|Nifty 50', 'symbol' => 'NIFTY', 'exchange' => 'NSE' ],
    ];

    public function handle(): int {
        $now = now();
        info( 'ohlc:collect-1min running - Start ' . $now->toTimeString() );

        $start = $now->copy()->setTime( 9, 8 );
        $end   = $now->copy()->setTime( 15, 30 );

        if ( ! $now->between( $start, $end ) ) {
            $this->info( 'Outside market hours — skipping.' );

            return self::SUCCESS;
        }

        $token = config( 'services.upstox.analytics_token' );

        if ( ! $token ) {
            $this->error( 'Upstox access token not configured.' );

            return self::FAILURE;
        }

        foreach ( $this->instruments as $inst ) {
            $this->processInstrument( $inst, $token );
        }

        $this->info( '1-min OHLC collection complete — ' . now()->toTimeString() );
        info( 'ohlc:collect-1min running - Completed ' . now()->toTimeString() );

        return self::SUCCESS;
    }

    private function processInstrument( array $inst, string $token ): void {
        $this->info( "Processing {$inst['symbol']} ..." );

        $_optExpiries = DB::table( 'nse_expiries' )
                          ->where( 'trading_symbol', $inst['symbol'] )
                          ->where( 'expiry_date', '>=', today()->toDateString() )
                          ->where( 'instrument_type', 'OPT' )
                          ->orderBy( 'expiry_date', 'asc' )
                          ->take( 5 )
                          ->get();

        $_futExpiry = DB::table( 'nse_expiries' )
                        ->where( 'trading_symbol', $inst['symbol'] )
                        ->where( 'is_current', 1 )
                        ->where( 'instrument_type', 'FUT' )
                        ->first();


        $futExpiryDate = $_futExpiry?->expiry_date;

        if ( $_optExpiries->isEmpty() && ! $futExpiryDate ) {
            Log::warning( "No current expiry (OPT or FUT) for {$inst['symbol']}" );
            $this->warn( "No current expiry for {$inst['symbol']} — skipping." );

            return;
        }
        $instrumentRows = collect();

        if ( $_optExpiries->isNotEmpty() ) {
            $optExpiries = $_optExpiries->pluck( 'expiry' );   // both current + next expiry values

            info('$optExpiries',[$optExpiries]);

            $cepe = DB::table( 'instruments' )
                      ->where( 'underlying_symbol', $inst['symbol'] )
                      ->whereIn( 'expiry', $optExpiries )            // ← both expiry dates
                      ->whereIn( 'instrument_type', [ 'CE', 'PE' ] )
                      ->get( [ 'instrument_key', 'instrument_type', 'strike_price', 'expiry' ] );

            // Build per-key expiry_date map using the nse_expiries lookup
            $expiryToDate = $_optExpiries->pluck( 'expiry_date', 'expiry' );  // [ expiry => expiry_date ]

            foreach ( $cepe as $row ) {
                $optExpiryDateMap[ $row->instrument_key ] = $expiryToDate[ $row->expiry ] ?? null;
            }

            $instrumentRows = $instrumentRows->merge( $cepe );
        }

        if ( $_futExpiry ) {
            $fut = DB::table( 'instruments' )
                     ->where( 'underlying_symbol', $inst['symbol'] )
                     ->where( 'expiry', $_futExpiry->expiry )
                     ->where( 'instrument_type', 'FUT' )
                     ->get( [ 'instrument_key', 'instrument_type', 'strike_price' ] );

            $instrumentRows = $instrumentRows->merge( $fut );
        }

        if ( $instrumentRows->isEmpty() ) {
            $this->warn( "No instruments found for {$inst['symbol']}." );

            return;
        }

        $metaMap = $instrumentRows
            ->keyBy( 'instrument_key' )
            ->map( fn( $row ) => [
                'instrument_type' => $row->instrument_type,
                'strike'          => $row->strike_price,
            ] )
            ->toArray();

        // Always include the Index instrument itself (e.g. NSE_INDEX|Nifty 50)
        if ( ! empty( $inst['key'] ) ) {
            $metaMap[ $inst['key'] ] = [
                'instrument_type' => 'INDEX',
                'strike'          => 0,
            ];
            // Also sync intraday index candles for today to ensure complete history
            $this->syncIntradayIndexCandles( $inst['key'], $token );
        }

        info( 'before ohlc api ' . now()->toTimeString() );

        $quotes = $this->fetchOneMinOhlc( array_keys( $metaMap ), $token );

        info( 'after ohlc api ' . now()->toTimeString() );

        if ( empty( $quotes ) ) {
            $this->error( "1-min OHLC fetch returned empty data for {$inst['symbol']}." );

            return;
        }

        $rows = [];
        $now  = now()->second( 0 );

        foreach ( $quotes as $instrumentKey => $quote ) {
            if ( ! isset( $metaMap[ $instrumentKey ] ) ) {
                continue;
            }

            $meta = $metaMap[ $instrumentKey ];

            $ohlc = $quote['live_ohlc'] ?? null;
            if ( ! $ohlc ) {
                continue;
            }

            $open  = $ohlc['open'] ?? null;
            $high  = $ohlc['high'] ?? null;
            $low   = $ohlc['low'] ?? null;
            $close = $ohlc['close'] ?? null;

            if ( $open === null || $high === null || $low === null || $close === null ) {
                continue;
            }

            $tsAt = ! empty( $ohlc['ts'] )
                ? Carbon::createFromTimestampMs( (int) $ohlc['ts'] )
                        ->setTimezone( config( 'app.timezone' ) )
                : now()->setTimezone( config( 'app.timezone' ) );

            $tsAt->second( 0 );

            // ── Resolve the correct expiry_date per instrument_key ─────────────
            $resolvedExpiryDate = in_array( $meta['instrument_type'], [ 'CE', 'PE' ], true )
                ? ( $optExpiryDateMap[ $instrumentKey ] ?? null )   // per-key lookup
                : ( $meta['instrument_type'] === 'FUT' ? $futExpiryDate : null );

            $rows[] = [
                'instrument_key'  => $instrumentKey,
                'instrument_type' => $meta['instrument_type'],
                'trading_symbol'  => $meta['instrument_type'] === 'INDEX' ? ($inst['symbol'] === 'NIFTY' ? 'Nifty 50' : $inst['symbol']) : $inst['symbol'],
                'expiry_date'     => $resolvedExpiryDate,       // ← correct per-instrument expiry
                'strike_price'    => $meta['strike'],
                'open'            => $open,
                'high'            => $high,
                'low'             => $low,
                'close'           => $close,
                'volume'          => $ohlc['volume'] ?? null,
                'ts'              => $tsAt->timestamp,
                'ts_at'           => $tsAt,
                'last_price'      => $quote['last_price'] ?? null,
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }

        if ( empty( $rows ) ) {
            $this->warn( "No rows prepared for {$inst['symbol']}." );

            return;
        }

        DB::table( 'ohlc_quotes' )->upsert(
            $rows,
            [ 'instrument_key', 'ts' ],
            [ 'open', 'high', 'low', 'close', 'volume', 'last_price', 'ts_at', 'updated_at' ]
        );

        info( 'Event Firing start' . now()->toTimeString() );
        event( new \App\Events\OhlcOneMinCollected(
            symbol: $inst['symbol'],
            timestamp: now()->second( 0 )->toDateTimeString(),
        ) );
        info( 'Event Firing end' . now()->toTimeString() );
        info( 'fully completed ' . now()->toTimeString() );
        $this->info( "Upserted " . count( $rows ) . " 1-min OHLC rows for {$inst['symbol']}." );
    }

    private function fetchOneMinOhlc( array $instrumentKeys, string $token ): array {
        info( '$instrumentKeys', [ count( $instrumentKeys ) ] );
        $chunks = array_chunk( $instrumentKeys, 490 );
        $result = [];

        foreach ( $chunks as $chunk ) {
            $response = Http::withHeaders( [
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ] )->get( 'https://api.upstox.com/v3/market-quote/ohlc', [  // ← v3
                'instrument_key' => implode( ',', $chunk ),
                'interval'       => 'I1',
            ] );

            if ( ! $response->ok() ) {
                Log::error( '1-min OHLC API error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ] );
                continue;
            }

            $body = $response->json();

            //info('$body',[$body]);

            if ( ( $body['status'] ?? null ) !== 'success' || empty( $body['data'] ) ) {
                Log::error( 'Unexpected 1-min OHLC response', [ 'body' => $body ] );
                continue;
            }

            foreach ( $body['data'] as $key => $quote ) {
                $resolvedKey            = $quote['instrument_token'] ?? $key;
                $result[ $resolvedKey ] = $quote;
            }
        }

        return $result;
    }

    /**
     * Backfill/sync 1-minute intraday index candles for today from Upstox to ensure continuous history
     */
    private function syncIntradayIndexCandles( string $instrumentKey, string $token ): void {
        try {
            $encodedKey = rawurlencode( $instrumentKey );
            $resp = Http::withHeaders( [
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ] )->timeout( 10 )->get( "https://api.upstox.com/v2/historical-candle/intraday/{$encodedKey}/1minute" );

            if ( $resp->ok() ) {
                $candles = $resp->json( 'data.candles' ) ?? [];
                if ( ! empty( $candles ) ) {
                    $now = now();
                    $indexRows = [];
                    foreach ( $candles as $c ) {
                        $tsAt = Carbon::parse( $c[0] )->setTimezone( config( 'app.timezone', 'Asia/Kolkata' ) )->second( 0 );
                        $indexRows[] = [
                            'instrument_key'  => $instrumentKey,
                            'instrument_type' => 'INDEX',
                            'trading_symbol'  => 'Nifty 50',
                            'expiry_date'     => null,
                            'strike_price'    => 0,
                            'open'            => (float) $c[1],
                            'high'            => (float) $c[2],
                            'low'             => (float) $c[3],
                            'close'           => (float) $c[4],
                            'volume'          => (int) ( $c[5] ?? 0 ),
                            'ts'              => $tsAt->timestamp,
                            'ts_at'           => $tsAt->toDateTimeString(),
                            'last_price'      => (float) $c[4],
                            'created_at'      => $now,
                            'updated_at'      => $now,
                        ];
                    }

                    if ( ! empty( $indexRows ) ) {
                        DB::table( 'ohlc_quotes' )->upsert(
                            $indexRows,
                            [ 'instrument_key', 'ts' ],
                            [ 'open', 'high', 'low', 'close', 'volume', 'last_price', 'ts_at', 'updated_at' ]
                        );
                    }
                }
            }
        } catch ( \Throwable $e ) {
            Log::warning( "Failed to sync intraday index candles for {$instrumentKey}: " . $e->getMessage() );
        }
    }
}
