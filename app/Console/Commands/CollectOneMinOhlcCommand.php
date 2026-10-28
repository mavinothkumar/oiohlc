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

    /**
     * Exact Upstox instrument keys for all 50 NIFTY constituent stocks
     */
    public static array $nifty50EquityStocks = [
        'HDFCBANK'   => 'NSE_EQ|INE040A01034',
        'RELIANCE'   => 'NSE_EQ|INE002A01018',
        'ICICIBANK'  => 'NSE_EQ|INE090A01021',
        'INFY'       => 'NSE_EQ|INE009A01021',
        'ITC'        => 'NSE_EQ|INE154A01025',
        'TCS'        => 'NSE_EQ|INE467B01029',
        'LT'         => 'NSE_EQ|INE018A01030',
        'BHARTIARTL' => 'NSE_EQ|INE397D01024',
        'AXISBANK'   => 'NSE_EQ|INE238A01034',
        'SBIN'       => 'NSE_EQ|INE062A01020',
        'KOTAKBANK'  => 'NSE_EQ|INE237A01036',
        'M&M'        => 'NSE_EQ|INE101A01026',
        'HINDUNILVR' => 'NSE_EQ|INE030A01027',
        'MARUTI'     => 'NSE_EQ|INE585B01010',
        'BAJFINANCE' => 'NSE_EQ|INE296A01032',
        'SUNPHARMA'  => 'NSE_EQ|INE044A01036',
        'TATAMOTORS' => 'NSE_EQ|INE155A01022',
        'NTPC'       => 'NSE_EQ|INE733E01010',
        'TITAN'      => 'NSE_EQ|INE280A01028',
        'POWERGRID'  => 'NSE_EQ|INE752E01010',
        'TATASTEEL'  => 'NSE_EQ|INE081A01020',
        'ASIANPAINT' => 'NSE_EQ|INE021A01026',
        'ADANIENT'   => 'NSE_EQ|INE423A01024',
        'ADANIPORTS' => 'NSE_EQ|INE742F01042',
        'COALINDIA'  => 'NSE_EQ|INE522F01014',
        'BAJAJFINSV' => 'NSE_EQ|INE918I01026',
        'ULTRACEMCO' => 'NSE_EQ|INE481G01011',
        'ONGC'       => 'NSE_EQ|INE213A01029',
        'HCLTECH'    => 'NSE_EQ|INE860A01027',
        'NESTLEIND'  => 'NSE_EQ|INE239A01024',
        'JSWSTEEL'   => 'NSE_EQ|INE019A01038',
        'GRASIM'     => 'NSE_EQ|INE047A01021',
        'TECHM'      => 'NSE_EQ|INE669C01036',
        'WIPRO'      => 'NSE_EQ|INE075A01022',
        'CIPLA'      => 'NSE_EQ|INE059A01026',
        'DRREDDY'    => 'NSE_EQ|INE089A01031',
        'EICHERMOT'  => 'NSE_EQ|INE066A01021',
        'TATACONSUM' => 'NSE_EQ|INE192A01025',
        'BPCL'       => 'NSE_EQ|INE029A01011',
        'HEROMOTOCO' => 'NSE_EQ|INE158A01026',
        'BRITANNIA'  => 'NSE_EQ|INE216A01030',
        'HINDALCO'   => 'NSE_EQ|INE038A01020',
        'APOLLOHOSP' => 'NSE_EQ|INE437A01024',
        'SBILIFE'    => 'NSE_EQ|INE123W01016',
        'DIVISLAB'   => 'NSE_EQ|INE361B01024',
        'SHRIRAMFIN' => 'NSE_EQ|INE721A01047',
        'BEL'        => 'NSE_EQ|INE263A01024',
        'TRENT'      => 'NSE_EQ|INE849A01020',
        'JIOFIN'     => 'NSE_EQ|INE758E01017',
        'BAJAJ-AUTO' => 'NSE_EQ|INE917I01010',
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

        // Include all 50 Nifty constituent cash equity stocks
        foreach ( self::$nifty50EquityStocks as $stockSym => $stockKey ) {
            $metaMap[ $stockKey ] = [
                'instrument_type' => 'EQ',
                'strike'          => 0,
                'symbol'          => $stockSym,
            ];
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

            $isIndex = ( $meta['instrument_type'] === 'INDEX' );
            $isEq    = ( $meta['instrument_type'] === 'EQ' );
            $tradingSymbol = $isIndex ? 'Nifty 50' : ( $isEq ? ( $meta['symbol'] ?? $inst['symbol'] ) : $inst['symbol'] );

            $rows[] = [
                'instrument_key'  => $instrumentKey,
                'instrument_type' => $meta['instrument_type'],
                'trading_symbol'  => $tradingSymbol,
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
