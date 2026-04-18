<?php

namespace App\Console\Commands;

use App\Models\OhlcLiveSnapshot;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Collect live 5-minute OHLC data merged with OI from the option-chain API.
 *
 * Two API calls per instrument group:
 *   1. GET /v3/market-quote/ohlc  — open, high, low, close, volume, timestamp
 *   2. GET /v2/option/chain       — oi, option_greeks (per CE/PE strike)
 *
 * Both results are joined on instrument_key and written to ohlc_live_snapshots.
 *
 * Schedule: every 5 minutes during market hours (9:15 – 15:30 IST).
 */
class CollectLiveOhlcCommand extends Command
{
    protected $signature   = 'ohlc:collect-live';
    protected $description = 'Collect 5-minute OHLC + OI snapshots and compute build-up';

    /** Underlying instruments to track */
    private array $instruments = [
        ['key' => 'NSE_INDEX|Nifty 50',        'symbol' => 'NIFTY',     'exchange' => 'NSE'],
        // ['key' => 'NSE_INDEX|Nifty Bank',       'symbol' => 'BANKNIFTY', 'exchange' => 'NSE'],
        // ['key' => 'NSE_INDEX|Nifty Fin Service', 'symbol' => 'FINNIFTY',  'exchange' => 'NSE'],
        // ['key' => 'BSE_INDEX|SENSEX',            'symbol' => 'SENSEX',    'exchange' => 'BSE'],
    ];

    public function handle(): int
    {
        // ── Market-hours guard ──────────────────────────────────────────────
        $now   = now();
        $start = $now->copy()->setTime(9, 15);
        $end   = $now->copy()->setTime(15, 30);

        if (! $now->between($start, $end)) {
            $this->info('Outside market hours — skipping.');
            return self::SUCCESS;
        }

        $token = config('services.upstox.analytics_token');

        if (! $token) {
            $this->error('Upstox access token not configured.');
            return self::FAILURE;
        }

        foreach ($this->instruments as $inst) {
            $this->processInstrument($inst, $token);
        }

        $this->info('Live OHLC snapshot collection complete — ' . $now->toTimeString());
        return self::SUCCESS;
    }

    // ── Per-instrument processing ──────────────────────────────────────────

    private function processInstrument(array $inst, string $token): void
    {
        $this->info("Processing {$inst['symbol']} …");

        // 1a. Resolve current OPT expiry (weekly) — used for CE/PE
        $_optExpiry = DB::table('nse_expiries')
                       ->where('trading_symbol', $inst['symbol'])
                       ->where('is_current', 1)
                       ->where('instrument_type', 'OPT')
                       ->first();

        $optExpiry = $_optExpiry->expiry_date;



        // 1b. Resolve current FUT expiry (monthly) — used for FUT
        $_futExpiry = DB::table('nse_expiries')
                       ->where('trading_symbol', $inst['symbol'])
                       ->where('is_current', 1)
                       ->where('instrument_type', 'FUT')
                        ->first();

        $futExpiry = $_futExpiry->expiry_date;



        if (! $optExpiry && ! $futExpiry) {
            Log::warning("No current expiry (OPT or FUT) for {$inst['symbol']}");
            $this->warn("No current expiry for {$inst['symbol']} — skipping.");
            return;
        }

        // 2. Collect CE/PE instruments using OPT expiry,
        //    and FUT instruments using FUT expiry — separately, then merge.
        $instruments = collect();

        if ($optExpiry) {
            $cepe = DB::table('instruments')
                      ->where('underlying_symbol', $inst['symbol'])
                      ->where('expiry', $_optExpiry->expiry)
                      ->whereIn('instrument_type', ['CE', 'PE'])
                      ->get(['instrument_key', 'instrument_type', 'strike_price']);

            $instruments = $instruments->merge($cepe);
        }


        if ($futExpiry) {
            $fut = DB::table('instruments')
                     ->where('underlying_symbol', $inst['symbol'])
                     ->where('expiry', $_futExpiry->expiry)
                     ->where('instrument_type', 'FUT')
                     ->get(['instrument_key', 'instrument_type', 'strike_price']);

            $instruments = $instruments->merge($fut);
        }

        if ($instruments->isEmpty()) {
            $this->warn("No instruments found for {$inst['symbol']}.");
            return;
        }

        // Build a lookup map: instrument_key => meta
        $metaMap = $instruments->keyBy('instrument_key')->map(fn($r) => [
            'instrument_type' => $r->instrument_type,
            'strike'          => $r->strike_price,
        ])->toArray();


        // ── API Call 1: OHLC (v3) ─────────────────────────────────────────
        $ohlcData = $this->fetchOhlc(array_keys($metaMap), $token);
        if (empty($ohlcData)) {
            $this->error("OHLC fetch returned empty data for {$inst['symbol']}.");
            return;
        }

        // ── API Call 2: Option Chain OI ───────────────────────────────────
        // Option chain uses OPT expiry (weekly strikes)
        $oiMap = $optExpiry ? $this->fetchOptionChainOI($inst['key'], $optExpiry, $token) : [];

        // ── Load previous snapshot for diff calculation ───────────────────
        $latestTs = OhlcLiveSnapshot::where('underlying_symbol', $inst['symbol'])
                                    ->orderByDesc('timestamp')
                                    ->value('timestamp');

        $prevMap = [];
        if ($latestTs) {
            $prevMap = OhlcLiveSnapshot::where('underlying_symbol', $inst['symbol'])
                                       ->where('timestamp', $latestTs)
                                       ->get(['instrument_key', 'oi', 'volume', 'close'])
                                       ->keyBy('instrument_key')
                                       ->toArray();
        }

        // ── Build and upsert records ──────────────────────────────────────
        $now = now()->second(0); // floor seconds to 0
        $inserted = 0;

        foreach ($ohlcData as $instrumentKey => $quote) {
            if (! isset($metaMap[$instrumentKey])) {
                continue;
            }

            $meta  = $metaMap[$instrumentKey];
            $live  = $quote['live_ohlc'] ?? null;
            if (! $live) {
                continue;
            }

            $tsMs  = $live['ts'] ?? null;
            $tsAt  = $tsMs
                ? Carbon::createFromTimestampMs($tsMs)->setTimezone(config('app.timezone'))->second(0)
                : $now;

            // Only store on 5-minute boundaries (9:20, 9:25 …)
            if ((int) $tsAt->format('i') % 5 !== 0) {
                continue;
            }

            // OI from option-chain map
            $oi = $oiMap[$instrumentKey] ?? null;

            // Prev snapshot values
            $prev       = $prevMap[$instrumentKey] ?? null;
            $prevOi     = $prev ? $prev->oi     : null;
            $prevVol    = $prev ? $prev->volume  : null;
            $prevClose  = $prev ? $prev->close   : null;

            $diffOi     = (! is_null($oi) && ! is_null($prevOi))   ? $oi - $prevOi           : null;
            $diffVol    = (! is_null($live['volume']) && ! is_null($prevVol)) ? $live['volume'] - $prevVol : null;
            $diffLtp    = (! is_null($live['close']) && ! is_null($prevClose)) ? $live['close'] - $prevClose : null;

            $buildUp    = $this->deriveBuildUp($diffLtp, $diffOi);

            OhlcLiveSnapshot::updateOrCreate(
                [
                    'instrument_key' => $instrumentKey,
                    'timestamp'      => $tsAt,
                ],
                [
                    'underlying_symbol' => $inst['symbol'],
                    // Use the correct expiry per instrument type
                    'expiry_date'       => in_array($meta['instrument_type'], ['CE', 'PE']) ? $optExpiry : $futExpiry,
                    'strike'            => $meta['strike'],
                    'instrument_type'   => $meta['instrument_type'],

                    'open'   => $live['open']   ?? null,
                    'high'   => $live['high']   ?? null,
                    'low'    => $live['low']    ?? null,
                    'close'  => $live['close']  ?? null,
                    'volume' => $live['volume'] ?? null,
                    'oi'     => $oi,

                    'exchange' => $inst['exchange'],
                    'interval' => '5m',

                    'build_up'    => $buildUp,
                    'diff_oi'     => $diffOi,
                    'diff_volume' => $diffVol,
                    'diff_ltp'    => $diffLtp,
                ]
            );

            $inserted++;
        }

        $this->info("  ✓ Upserted {$inserted} snapshots for {$inst['symbol']}.");
    }

    // ── API helpers ─────────────────────────────────────────────────────────

    /**
     * Call GET /v3/market-quote/ohlc with interval=I5 (5-minute candles).
     * Returns keyed array: instrument_key => quote array.
     */
    private function fetchOhlc(array $instrumentKeys, string $token): array
    {
        $chunks = array_chunk($instrumentKeys, 500);
        $result = [];

        foreach ($chunks as $chunk) {
            $response = Http::withHeaders([
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ])->get('https://api.upstox.com/v3/market-quote/ohlc', [
                'instrument_key' => implode(',', $chunk),
                'interval'       => 'I5',   // 5-minute interval
            ]);

            if (! $response->ok()) {
                Log::error('OHLC API error: HTTP ' . $response->status());
                continue;
            }

            $body = $response->json();
            if (($body['status'] ?? '') !== 'success' || empty($body['data'])) {
                Log::error('Unexpected OHLC response', ['body' => $body]);
                continue;
            }

            // v3 returns data keyed by instrument_key directly
            foreach ($body['data'] as $key => $quote) {
                // The actual instrument_key might be in instrument_token field
                $iKey = $quote['instrument_token'] ?? $key;
                $result[$iKey] = $quote;
            }
        }

        return $result;
    }

    /**
     * Call GET /v2/option/chain and build a map: instrument_key => oi.
     * Both CE and PE sides are flattened into the same map.
     */
    private function fetchOptionChainOI(string $instrumentKey, string $expiry, string $token): array
    {
        $url = 'https://api.upstox.com/v2/option/chain';
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
        ])->get($url, [
            'instrument_key' => $instrumentKey,
            'expiry_date'    => $expiry,
        ]);

        $data = $response->json('data') ?? [];
        $oiMap = [];

        if (empty($data)) {
            return $oiMap;
        }

        // ── Get previous snapshot for diff calculation (same logic as FetchOptionChainData) ──
        $latestCapturedAt = DB::table('option_chains')
                              ->latest('captured_at')
                              ->value('captured_at');

        $prevData = DB::table('option_chains')
                      ->where('captured_at', $latestCapturedAt)
                      ->select(['instrument_key', 'oi', 'ltp', 'volume'])
                      ->get()
                      ->keyBy('instrument_key')
                      ->toArray();

        $now     = now()->copy()->second(0);
        $records = [];

        foreach ($data as $item) {
            $strike = $item['strike_price'];

            // ── Your existing $oiMap population (keep as-is) ──
            $oiMap[$strike]['CE'] = $item['call_options']['market_data']['oi'] ?? 0;
            $oiMap[$strike]['PE'] = $item['put_options']['market_data']['oi']  ?? 0;

            // ── NEW: Build option_chains records for CE and PE ──
            $records[] = $this->buildOptionChainRecord($item, $prevData, $instrumentKey, 'CE', $now);
            $records[] = $this->buildOptionChainRecord($item, $prevData, $instrumentKey, 'PE', $now);
        }

        // ── Insert all records in one batch ──
        if (!empty($records)) {
            DB::table('option_chains')->insert($records);
        }

        return $oiMap;
    }

    private function buildOptionChainRecord(array $item, array $prevData, string $instrumentKey, string $type, $now): array
    {
        $optData        = $type === 'CE' ? $item['call_options'] : $item['put_options'];
        $m              = $optData['market_data'];
        $g              = $optData['option_greeks'];
        $inst_key       = $optData['instrument_key'];

        $prevRecord  = $prevData[$inst_key] ?? null;
        $diff_oi     = $prevRecord ? $m['oi']     - $prevRecord->oi     : null;
        $diff_volume = $prevRecord ? $m['volume'] - $prevRecord->volume : null;
        $diff_ltp    = $prevRecord ? $m['ltp']    - $prevRecord->ltp    : null;

        $buildUp = null;
        if (!is_null($diff_oi) && !is_null($diff_ltp) && $diff_oi != 0 && $diff_ltp != 0) {
            if      ($diff_ltp > 0 && $diff_oi > 0) $buildUp = 'Long Build';
            elseif  ($diff_ltp < 0 && $diff_oi > 0) $buildUp = 'Short Build';
            elseif  ($diff_ltp > 0 && $diff_oi < 0) $buildUp = 'Short Cover';
            elseif  ($diff_ltp < 0 && $diff_oi < 0) $buildUp = 'Long Unwind';
        }

        return [
            'underlying_key'        => $item['underlying_key'],
            'instrument_key'        => $inst_key,
            'trading_symbol'        => $item['underlying_key'],
            'expiry'                => $item['expiry'],
            'strike_price'          => $item['strike_price'],
            'option_type'           => $type,
            'ltp'                   => $m['ltp'],
            'diff_ltp'              => $diff_ltp,
            'volume'                => $m['volume'],
            'diff_volume'           => $diff_volume,
            'oi'                    => $m['oi'],
            'diff_oi'               => $diff_oi,
            'close_price'           => $m['close_price'],
            'bid_price'             => $m['bid_price'],
            'bid_qty'               => $m['bid_qty'],
            'ask_price'             => $m['ask_price'],
            'ask_qty'               => $m['ask_qty'],
            'prev_oi'               => $m['prev_oi'],
            'vega'                  => $g['vega'],
            'theta'                 => $g['theta'],
            'gamma'                 => $g['gamma'],
            'delta'                 => $g['delta'],
            'iv'                    => $g['iv'],
            'pop'                   => $g['pop'],
            'underlying_spot_price' => $item['underlying_spot_price'],
            'pcr'                   => $item['pcr'] ?? null,
            'build_up'              => $buildUp,
            'captured_at'           => $now,
            'created_at'            => $now,
            'updated_at'            => $now,
        ];
    }

    // ── Build-up logic ──────────────────────────────────────────────────────

    /**
     * Derive build-up type from price and OI change.
     *
     *  LTP ↑  OI ↑  → Long Build
     *  LTP ↓  OI ↑  → Short Build
     *  LTP ↑  OI ↓  → Short Cover
     *  LTP ↓  OI ↓  → Long Unwind
     */
    private function deriveBuildUp(?float $diffLtp, ?int $diffOi): ?string
    {
        if (is_null($diffLtp) || is_null($diffOi) || $diffLtp == 0 || $diffOi == 0) {
            return null;
        }

        return match (true) {
            $diffLtp > 0 && $diffOi > 0 => 'Long Build',
            $diffLtp < 0 && $diffOi > 0 => 'Short Build',
            $diffLtp > 0 && $diffOi < 0 => 'Short Cover',
            $diffLtp < 0 && $diffOi < 0 => 'Long Unwind',
            default                      => null,
        };
    }
}
