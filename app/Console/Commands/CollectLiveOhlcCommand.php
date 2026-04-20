<?php

namespace App\Console\Commands;

use App\Models\OhlcLiveSnapshot;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CollectLiveOhlcCommand extends Command {
    protected $signature = 'ohlc:collect-live';
    protected $description = 'Collect 5-minute OHLC + OI snapshots using Upstox full market quote API';

    private array $instruments = [
        [ 'key' => 'NSE_INDEX|Nifty 50', 'symbol' => 'NIFTY', 'exchange' => 'NSE' ],
        // ['key' => 'NSE_INDEX|Nifty Bank', 'symbol' => 'BANKNIFTY', 'exchange' => 'NSE'],
        // ['key' => 'NSE_INDEX|Nifty Fin Service', 'symbol' => 'FINNIFTY', 'exchange' => 'NSE'],
        // ['key' => 'BSE_INDEX|SENSEX', 'symbol' => 'SENSEX', 'exchange' => 'BSE'],
    ];

    public function handle(): int {
        $now = now();
        info( 'live ohlc collect running - Start' . now()->toTimeString() );
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

        $this->info( 'Live OHLC snapshot collection complete — ' . now()->toTimeString() );
        info( 'live ohlc collect running - Completed' . now()->toTimeString() );

        return self::SUCCESS;
    }

    private function processInstrument(array $inst, string $token): void
    {
        $this->info("Processing {$inst['symbol']} ...");

        $_optExpiry = DB::table('nse_expiries')
                        ->where('trading_symbol', $inst['symbol'])
                        ->where('is_current', 1)
                        ->where('instrument_type', 'OPT')
                        ->first();

        $_futExpiry = DB::table('nse_expiries')
                        ->where('trading_symbol', $inst['symbol'])
                        ->where('is_current', 1)
                        ->where('instrument_type', 'FUT')
                        ->first();

        $optExpiryDate = $_optExpiry?->expiry_date;
        $futExpiryDate = $_futExpiry?->expiry_date;

        if (! $optExpiryDate && ! $futExpiryDate) {
            Log::warning("No current expiry (OPT or FUT) for {$inst['symbol']}");
            $this->warn("No current expiry for {$inst['symbol']} — skipping.");
            return;
        }

        $instrumentRows = collect();

        if ($_optExpiry) {
            $cepe = DB::table('instruments')
                      ->where('underlying_symbol', $inst['symbol'])
                      ->where('expiry', $_optExpiry->expiry)
                      ->whereIn('instrument_type', ['CE', 'PE'])
                      ->get(['instrument_key', 'instrument_type', 'strike_price']);

            $instrumentRows = $instrumentRows->merge($cepe);
        }

        if ($_futExpiry) {
            $fut = DB::table('instruments')
                     ->where('underlying_symbol', $inst['symbol'])
                     ->where('expiry', $_futExpiry->expiry)
                     ->where('instrument_type', 'FUT')
                     ->get(['instrument_key', 'instrument_type', 'strike_price']);

            $instrumentRows = $instrumentRows->merge($fut);
        }

        if ($instrumentRows->isEmpty()) {
            $this->warn("No instruments found for {$inst['symbol']}.");
            return;
        }

        $metaMap = $instrumentRows->keyBy('instrument_key')->map(fn ($row) => [
            'instrument_type' => $row->instrument_type,
            'strike' => $row->strike_price,
        ])->toArray();
        info( 'before api' . now()->toTimeString() );
        $quotes = $this->fetchFullMarketQuotes(array_keys($metaMap), $token);
        info( 'after api' . now()->toTimeString() );
        if (empty($quotes)) {
            $this->error("Full market quote fetch returned empty data for {$inst['symbol']}.");
            return;
        }

        $latestTs = OhlcLiveSnapshot::where('underlying_symbol', $inst['symbol'])
                                    ->orderByDesc('timestamp')
                                    ->value('timestamp');

        $prevMap = collect();

        if ($latestTs) {
            $prevMap = OhlcLiveSnapshot::where('underlying_symbol', $inst['symbol'])
                                       ->where('timestamp', $latestTs)
                                       ->get(['instrument_key', 'oi', 'volume', 'close'])
                                       ->keyBy('instrument_key');
        }

        $now = now()->second(0);
        $rows = [];
        info( 'for each - start' . now()->toTimeString() );
        foreach ($quotes as $instrumentKey => $quote) {
            if (! isset($metaMap[$instrumentKey])) {
                continue;
            }

            $meta = $metaMap[$instrumentKey];
            $ohlc = $quote['ohlc'] ?? null;

            if (! $ohlc) {
                continue;
            }

            $tsAt = ! empty($quote['last_trade_time'])
                ? Carbon::createFromTimestampMs((int) $quote['last_trade_time'])
                        ->setTimezone(config('app.timezone'))
                : now()->setTimezone(config('app.timezone'));

            $tsAt->second(0);
            $minute = (int) $tsAt->format('i');
            $tsAt->minute(intdiv($minute, 5) * 5)->second(0);

            $oi = isset($quote['oi']) ? (int) $quote['oi'] : null;
            $volume = isset($quote['volume']) ? (int) $quote['volume'] : null;
            $close = isset($quote['last_price']) ? (float) $quote['last_price'] : ($ohlc['close'] ?? null);

            $prev = $prevMap->get($instrumentKey);
            $prevOi = $prev->oi ?? null;
            $prevVol = $prev->volume ?? null;
            $prevClose = $prev->close ?? null;

            $diffOi = (! is_null($oi) && ! is_null($prevOi)) ? $oi - $prevOi : null;
            $diffVol = (! is_null($volume) && ! is_null($prevVol)) ? $volume - $prevVol : null;
            $diffLtp = (! is_null($close) && ! is_null($prevClose)) ? $close - $prevClose : null;

            $rows[] = [
                'instrument_key' => $instrumentKey,
                'underlying_symbol' => $inst['symbol'],
                'expiry_date' => in_array($meta['instrument_type'], ['CE', 'PE'], true) ? $optExpiryDate : $futExpiryDate,
                'strike' => $meta['strike'],
                'instrument_type' => $meta['instrument_type'],
                'open' => $ohlc['open'] ?? null,
                'high' => $ohlc['high'] ?? null,
                'low' => $ohlc['low'] ?? null,
                'close' => $close,
                'oi' => $oi,
                'volume' => $volume,
                'exchange' => $inst['exchange'],
                'interval' => '5minute',
                'timestamp' => $tsAt,
                'build_up' => $this->deriveBuildUp($diffLtp, $diffOi),
                'diff_oi' => $diffOi,
                'diff_volume' => $diffVol,
                'diff_ltp' => $diffLtp,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        info( 'for each - completed' . now()->toTimeString() );

        if (empty($rows)) {
            $this->warn("No rows prepared for {$inst['symbol']}.");
            return;
        }

        DB::table('ohlc_live_snapshots')->upsert(
            $rows,
            ['instrument_key', 'timestamp'],
            [
                'underlying_symbol',
                'expiry_date',
                'strike',
                'instrument_type',
                'open',
                'high',
                'low',
                'close',
                'oi',
                'volume',
                'exchange',
                'interval',
                'build_up',
                'diff_oi',
                'diff_volume',
                'diff_ltp',
                'updated_at',
            ]
        );
        info( 'fully completed' . now()->toTimeString() );
        $inserted = count($rows);
        $this->info("Upserted {$inserted} snapshots for {$inst['symbol']}.");
    }

    private function fetchFullMarketQuotes( array $instrumentKeys, string $token ): array {
        $chunks = array_chunk( $instrumentKeys, 500 );
        $result = [];

        foreach ( $chunks as $chunk ) {
            $response = Http::withHeaders( [
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ] )->get( 'https://api.upstox.com/v2/market-quote/quotes', [
                'instrument_key' => implode( ',', $chunk ),
            ] );

            if ( ! $response->ok() ) {
                Log::error( 'Full market quote API error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ] );
                continue;
            }

            $body = $response->json();

            if ( ( $body['status'] ?? null ) !== 'success' || empty( $body['data'] ) ) {
                Log::error( 'Unexpected full market quote response', [ 'body' => $body ] );
                continue;
            }

            foreach ( $body['data'] as $key => $quote ) {
                $resolvedKey            = $quote['instrument_token'] ?? $key;
                $result[ $resolvedKey ] = $quote;
            }
        }

        return $result;
    }

    private function deriveBuildUp( ?float $diffLtp, ?int $diffOi ): ?string {
        if ( is_null( $diffLtp ) || is_null( $diffOi ) || ( $diffLtp == 0.0 && $diffOi == 0 ) ) {
            return null;
        }

        return match ( true ) {
            $diffLtp > 0 && $diffOi > 0 => 'Long Build',
            $diffLtp < 0 && $diffOi > 0 => 'Short Build',
            $diffLtp > 0 && $diffOi < 0 => 'Short Cover',
            $diffLtp < 0 && $diffOi < 0 => 'Long Unwind',
            default => null,
        };
    }
}
