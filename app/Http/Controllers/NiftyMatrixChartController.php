<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class NiftyMatrixChartController extends Controller
{
    /**
     * Main View: NIFTY Multi-Chart Matrix (1x1, 2x2, 3x2, 4x2, 3x3, 4x3, 3x4).
     */
    public function index(Request $request): View
    {
        $symbol = 'NIFTY';
        $step   = 50.0;

        // 1. Available Expiries for NIFTY
        $expiries = DB::table('nse_expiries')
            ->where('trading_symbol', $symbol)
            ->where('instrument_type', 'OPT')
            ->where('expiry_date', '>=', today()->subDays(7)->toDateString())
            ->orderBy('expiry_date')
            ->get();

        $currentExpiryRow = $expiries->firstWhere('is_current', 1);
        $nextExpiryRow    = $expiries->firstWhere('is_next', 1);

        $currentExpiry = $currentExpiryRow?->expiry_date ?? ($expiries->first()?->expiry_date ?? today()->toDateString());
        $nextExpiry    = $nextExpiryRow?->expiry_date ?? $currentExpiry;

        $selectedExpiry = $request->input('expiry', $currentExpiry);

        // 2. Working Dates
        $currentWorkingDate  = DB::table('nse_working_days')->where('current', 1)->value('working_date') ?? today()->toDateString();
        $previousWorkingDate = DB::table('nse_working_days')->where('previous', 1)->value('working_date');

        // 3. Current & Next Week MidPoints + Previous Day Close
        $cwMid = 102.23;
        $nwMid = 182.80;
        $pdc   = 22520.45;

        if ($previousWorkingDate) {
            $cwTrend = DB::table('daily_trend')
                ->where('symbol_name', $symbol)
                ->where('expiry_date', $currentExpiry)
                ->where('quote_date', $previousWorkingDate)
                ->first();

            $nwTrend = DB::table('daily_trend')
                ->where('symbol_name', $symbol)
                ->where('expiry_date', $nextExpiry)
                ->where('quote_date', $previousWorkingDate)
                ->first();

            if ($cwTrend) {
                $cwMid = (float)($cwTrend->mid_point ?? 102.23);
                $pdc   = (float)($cwTrend->index_close ?? 22520.45);
            }
            if ($nwTrend) {
                $nwMid = (float)($nwTrend->mid_point ?? 182.80);
            }
        }

        // 4. Current Underlying Spot & Initial ATM
        $latestSpotRow = DB::table('option_chains')
            ->where('trading_symbol', $symbol)
            ->where('captured_at', '>=', today()->subDays(5)->toDateTimeString())
            ->orderByDesc('captured_at')
            ->first();

        $spot = (float)($latestSpotRow->underlying_spot_price ?? 22350.0);
        $atm  = (float)(round($spot / $step) * $step);

        // 5. Grid Layout (Supported: 1x1, 2x2, 3x2, 4x2, 3x3, 4x3, 3x4, 4x4, 4x5)
        $allowedLayouts = ['1x1', '2x2', '3x2', '4x2', '3x3', '4x3', '3x4', '4x4', '4x5'];
        $layout = in_array($request->input('layout'), $allowedLayouts) ? $request->input('layout') : '4x2';

        // 6. Timeframe (default 5m) & Days Back (default 'all')
        $timeframe   = $request->input('timeframe', '5m');
        $daysBack    = $request->input('days_back', 'all');
        $chartHeight = $request->input('chart_height', '300');

        // 7. Initial Tile Configurations (Each chart has CE & PE for same strike)
        $tiles = $this->buildInitialTileConfigs($layout, $atm, $step);

        return view('nifty-matrix.index', compact(
            'symbol',
            'expiries',
            'currentExpiry',
            'nextExpiry',
            'selectedExpiry',
            'currentWorkingDate',
            'spot',
            'atm',
            'step',
            'cwMid',
            'nwMid',
            'pdc',
            'layout',
            'timeframe',
            'daysBack',
            'chartHeight',
            'tiles'
        ));
    }

    /**
     * Build tile configuration defaults for the requested grid.
     */
    protected function buildInitialTileConfigs(string $layout, float $atm, float $step): array
    {
        $tiles = [];

        if ($layout === '1x1') {
            // 1 tile: ATM Strike with CE & PE
            $tiles[] = [
                'id'        => 0,
                'type'      => 'BOTH',
                'strike'    => $atm,
                'offset'    => 0,
                'is_center' => true,
                'label'     => number_format($atm, 0, '', '') . ' CE & PE (ATM)',
            ];
        } elseif ($layout === '2x2') {
            // 4 tiles: ATM-50, ATM, ATM+50, ATM+100 (each has CE & PE)
            $offsets = [-1, 0, 1, 2];
            for ($i = 0; $i < 4; $i++) {
                $offset = $offsets[$i];
                $strike = $atm + ($offset * $step);
                $tiles[] = [
                    'id'        => $i,
                    'type'      => 'BOTH',
                    'strike'    => $strike,
                    'offset'    => $offset,
                    'is_center' => ($offset === 0),
                    'label'     => number_format($strike, 0, '', '') . ' CE & PE',
                ];
            }
        } elseif ($layout === '3x2') {
            // 6 tiles: ATM-100, ATM-50, ATM, ATM+50, ATM+100, ATM+150 (each has CE & PE)
            $offsets = [-2, -1, 0, 1, 2, 3];
            for ($i = 0; $i < 6; $i++) {
                $offset = $offsets[$i];
                $strike = $atm + ($offset * $step);
                $tiles[] = [
                    'id'        => $i,
                    'type'      => 'BOTH',
                    'strike'    => $strike,
                    'offset'    => $offset,
                    'is_center' => ($offset === 0),
                    'label'     => number_format($strike, 0, '', '') . ' CE & PE',
                ];
            }
        } elseif ($layout === '4x2') {
            // 8 tiles: 8 strikes centered around ATM (each has CE & PE)
            $offsets = [-3, -2, -1, 0, 1, 2, 3, 4];
            for ($i = 0; $i < 8; $i++) {
                $offset = $offsets[$i];
                $strike = $atm + ($offset * $step);
                $tiles[] = [
                    'id'        => $i,
                    'type'      => 'BOTH',
                    'strike'    => $strike,
                    'offset'    => $offset,
                    'is_center' => ($offset === 0),
                    'label'     => number_format($strike, 0, '', '') . ' CE & PE',
                ];
            }
        } elseif ($layout === '3x3') {
            // 9 tiles: Tile 4 (index 4) is exact Center ATM
            for ($i = 0; $i < 9; $i++) {
                $offset = $i - 4;
                $strike = $atm + ($offset * $step);
                $tiles[] = [
                    'id'        => $i,
                    'type'      => 'BOTH',
                    'strike'    => $strike,
                    'offset'    => $offset,
                    'is_center' => ($offset === 0),
                    'label'     => number_format($strike, 0, '', '') . ' CE & PE',
                ];
            }
        } elseif ($layout === '4x3' || $layout === '3x4' || $layout === '4x4' || $layout === '4x5') {
            // Layouts with first 4 dedicated tiles:
            // 0: Nifty Index 5m
            // 1: Nifty Index 15m
            // 2: Nifty Future 5m
            // 3: Nifty Fut 15m
            // Remaining tiles: Nifty options (CE & PE) centered around ATM
            $tiles[] = [
                'id'        => 0,
                'type'      => 'INDEX',
                'strike'    => 0,
                'offset'    => 0,
                'timeframe' => '5m',
                'is_center' => false,
                'label'     => 'NIFTY INDEX (5m)',
            ];
            $tiles[] = [
                'id'        => 1,
                'type'      => 'INDEX',
                'strike'    => 0,
                'offset'    => 0,
                'timeframe' => '15m',
                'is_center' => false,
                'label'     => 'NIFTY INDEX (15m)',
            ];
            $tiles[] = [
                'id'        => 2,
                'type'      => 'FUT',
                'strike'    => 0,
                'offset'    => 0,
                'timeframe' => '5m',
                'is_center' => false,
                'label'     => 'NIFTY FUT (5m)',
            ];
            $tiles[] = [
                'id'        => 3,
                'type'      => 'FUT',
                'strike'    => 0,
                'offset'    => 0,
                'timeframe' => '15m',
                'is_center' => false,
                'label'     => 'NIFTY FUT (15m)',
            ];

            // Determine number of option tiles based on grid
            $optCount = match($layout) {
                '4x4' => 12,  // 4 + 12 = 16 tiles (4x4)
                '4x5' => 16,  // 4 + 16 = 20 tiles (4x5)
                default => 8, // 4 + 8 = 12 tiles (4x3 / 3x4)
            };

            $half = (int)floor($optCount / 2);
            for ($i = 0; $i < $optCount; $i++) {
                $offset = $i - $half;
                $strike = $atm + ($offset * $step);
                $tiles[] = [
                    'id'        => $i + 4,
                    'type'      => 'BOTH',
                    'strike'    => $strike,
                    'offset'    => $offset,
                    'is_center' => ($offset === 0),
                    'label'     => number_format($strike, 0, '', '') . ' CE & PE' . ($offset === 0 ? ' (ATM)' : ''),
                ];
            }
        }

        return $tiles;
    }

    /**
     * AJAX Endpoint: Fetch multi-day candle and indicator data for tiles.
     */
    public function getData(Request $request): JsonResponse
    {
        $symbol     = 'NIFTY';
        $expiryDate = $request->input('expiry_date');
        $timeframe  = $request->input('timeframe', '5m');
        $daysBack   = $request->input('days_back', 'all');
        $tilesReq   = $request->input('tiles', []);

        if (empty($expiryDate)) {
            $expiryDate = DB::table('nse_expiries')
                ->where('trading_symbol', $symbol)
                ->where('instrument_type', 'OPT')
                ->where('is_current', 1)
                ->value('expiry_date') ?? today()->toDateString();
        }

        $nextExpiry = DB::table('nse_expiries')
            ->where('trading_symbol', $symbol)
            ->where('instrument_type', 'OPT')
            ->where('is_next', 1)
            ->value('expiry_date') ?? $expiryDate;

        $previousWorkingDate = DB::table('nse_working_days')->where('previous', 1)->value('working_date');

        // Resolve MidPoints and Previous Day Close
        $cwMid = 102.23;
        $nwMid = 182.80;
        $pdc   = 22520.45;

        if ($previousWorkingDate) {
            $cwTrend = DB::table('daily_trend')
                ->where('symbol_name', $symbol)
                ->where('expiry_date', $expiryDate)
                ->where('quote_date', $previousWorkingDate)
                ->first();

            $nwTrend = DB::table('daily_trend')
                ->where('symbol_name', $symbol)
                ->where('expiry_date', $nextExpiry)
                ->where('quote_date', $previousWorkingDate)
                ->first();

            if ($cwTrend) {
                $cwMid = (float)($cwTrend->mid_point ?? 102.23);
                $pdc   = (float)($cwTrend->index_close ?? 22520.45);
            }
            if ($nwTrend) {
                $nwMid = (float)($nwTrend->mid_point ?? 182.80);
            }
        }

        // Custom override if user changed values in inputs
        if ($request->filled('cw_mid')) $cwMid = (float)$request->input('cw_mid');
        if ($request->filled('nw_mid')) $nwMid = (float)$request->input('nw_mid');
        if ($request->filled('pdc'))    $pdc   = (float)$request->input('pdc');

        // Determine date threshold based on days_back
        $startDate = null;
        if ($daysBack !== 'all') {
            $daysNum = max(1, (int)$daysBack);
            $recentDates = DB::table('ohlc_live_snapshots')
                ->where('underlying_symbol', $symbol)
                ->where('expiry_date', $expiryDate)
                ->selectRaw('DISTINCT DATE(timestamp) as dt')
                ->orderByDesc('dt')
                ->take($daysNum)
                ->pluck('dt')
                ->toArray();

            if (!empty($recentDates)) {
                $startDate = end($recentDates) . ' 09:15:00';
            }
        }

        // Group strike requests
        $optionStrikes = [];
        foreach ($tilesReq as $t) {
            $type = strtoupper($t['type'] ?? 'BOTH');
            if (!in_array($type, ['INDEX', 'FUT'])) {
                $s = (float)($t['strike'] ?? 0);
                if ($s > 0 && !in_array($s, $optionStrikes)) {
                    $optionStrikes[] = $s;
                }
            }
        }

        $bucketMinutes = match ($timeframe) {
            '1m' => 1,
            '3m' => 3,
            '15m' => 15,
            '30m' => 30,
            '60m', '1h' => 60,
            default => 5,
        };

        // 1. Fetch Option Strikes Data from ohlc_live_snapshots (both CE and PE)
        $optionDataGrouped = [];
        if (!empty($optionStrikes)) {
            $query = DB::table('ohlc_live_snapshots')
                ->where('underlying_symbol', $symbol)
                ->where('expiry_date', $expiryDate)
                ->whereIn('strike', $optionStrikes)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->select([
                    'strike', 'instrument_type', 'open', 'high', 'low', 'close',
                    'volume', 'oi', 'diff_oi', 'timestamp'
                ])
                ->orderBy('timestamp');

            if ($startDate) {
                $query->where('timestamp', '>=', $startDate);
            }

            $rows = $query->get();

            foreach ($rows as $r) {
                $strike = (string)(float)$r->strike;
                $type   = strtoupper($r->instrument_type);
                $key    = "{$strike}_{$type}";

                // DB timestamp is IST "YYYY-MM-DD HH:MM:SS". Parsing as UTC preserves exact IST clock numbers for Lightweight Charts.
                $dt = Carbon::parse($r->timestamp, 'UTC');
                $bucketTs = $this->alignToTimeframe($dt, $bucketMinutes)->timestamp;

                if (!isset($optionDataGrouped[$key][$bucketTs])) {
                    $optionDataGrouped[$key][$bucketTs] = [
                        'time'    => $bucketTs,
                        'open'    => (float)$r->open,
                        'high'    => (float)$r->high,
                        'low'     => (float)$r->low,
                        'close'   => (float)$r->close,
                        'volume'  => (int)$r->volume,
                        'oi'      => (int)$r->oi,
                        'diff_oi' => (int)$r->diff_oi,
                        'date'    => $dt->toDateString(),
                    ];
                } else {
                    $b = &$optionDataGrouped[$key][$bucketTs];
                    $b['high']    = max($b['high'], (float)$r->high);
                    $b['low']     = min($b['low'], (float)$r->low);
                    $b['close']   = (float)$r->close;
                    $b['volume']  = max($b['volume'], (int)$r->volume);
                    $b['oi']      = (int)$r->oi;
                    $b['diff_oi'] = (int)$r->diff_oi;
                }
            }
        }

        // Resolve instrument keys for live stream subscription
        $indexKey = 'NSE_INDEX|Nifty 50';
        $allSubscribedKeys = [$indexKey];

        $futExpiry = DB::table('nse_expiries')
            ->where('trading_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->where('is_current', 1)
            ->value('expiry');

        $futKey = 'NSE_FO|48704';
        if ($futExpiry) {
            $foundFut = DB::table('instruments')
                ->where('name', $symbol)
                ->where('instrument_type', 'FUT')
                ->where('expiry', $futExpiry)
                ->value('instrument_key');
            if ($foundFut) $futKey = $foundFut;
        }
        $allSubscribedKeys[] = $futKey;

        $expTs = DB::table('nse_expiries')->where('expiry_date', $expiryDate)->value('expiry');
        $instKeysMap = [];
        if ($expTs && !empty($optionStrikes)) {
            $instRows = DB::table('instruments')
                ->where('name', $symbol)
                ->where('expiry', $expTs)
                ->whereIn('strike_price', $optionStrikes)
                ->get(['instrument_key', 'instrument_type', 'strike_price']);

            foreach ($instRows as $ir) {
                $instKeysMap[(string)(float)$ir->strike_price][$ir->instrument_type] = $ir->instrument_key;
            }
        }

        // 2. Process each tile in tilesReq
        $resultTiles = [];
        foreach ($tilesReq as $tile) {
            $tileId = $tile['id'];
            $type   = strtoupper($tile['type'] ?? 'BOTH');
            $strike = (float)($tile['strike'] ?? 0);

            // Check if this tile specifies an individual timeframe override
            $tileTf = $tile['timeframe'] ?? $timeframe;
            $tileBucketMinutes = match ($tileTf) {
                '1m' => 1,
                '3m' => 3,
                '15m' => 15,
                '30m' => 30,
                '60m', '1h' => 60,
                default => 5,
            };

            if ($type === 'INDEX') {
                $candles = $this->fetchIndexCandles($startDate, $tileBucketMinutes, $expiryDate);
                $enriched = $this->enrichIndicators($candles);

                // Index logic lines (2 Resistance + 2 Support)
                $logicLines = [
                    'r1' => round($pdc + $cwMid, 2), // Red (+CW Mid)
                    's1' => round($pdc - $cwMid, 2), // Green (-CW Mid)
                    'r2' => round($pdc + $nwMid, 2), // Dark Red (+NW Mid)
                    's2' => round($pdc - $nwMid, 2), // Dark Green (-NW Mid)
                    'pdc'=> $pdc,
                ];

                $resultTiles[$tileId] = [
                    'id'             => $tileId,
                    'type'           => 'INDEX',
                    'strike'         => 0,
                    'timeframe'      => $tileTf,
                    'instrument_key' => $indexKey,
                    'candles'        => $candles,
                    'indicators'     => $enriched,
                    'logic_lines'    => $logicLines,
                ];
            } elseif ($type === 'FUT') {
                $candles = $this->fetchFuturesCandles($startDate, $tileBucketMinutes);
                $enriched = $this->enrichIndicators($candles);

                // Futures logic lines (2 Resistance + 2 Support)
                $logicLines = [
                    'r1' => round($pdc + $cwMid, 2), // Red (+CW Mid)
                    's1' => round($pdc - $cwMid, 2), // Green (-CW Mid)
                    'r2' => round($pdc + $nwMid, 2), // Dark Red (+NW Mid)
                    's2' => round($pdc - $nwMid, 2), // Dark Green (-NW Mid)
                    'pdc'=> $pdc,
                ];

                $resultTiles[$tileId] = [
                    'id'             => $tileId,
                    'type'           => 'FUT',
                    'strike'         => 0,
                    'timeframe'      => $tileTf,
                    'instrument_key' => $futKey,
                    'candles'        => $candles,
                    'indicators'     => $enriched,
                    'logic_lines'    => $logicLines,
                ];
            } else {
                // Option Strike tile (Contains CE & PE of the same strike)
                $ceKey = ((string)$strike) . "_CE";
                $peKey = ((string)$strike) . "_PE";

                $ceBuckets = $optionDataGrouped[$ceKey] ?? [];
                ksort($ceBuckets);
                $ceCandles = array_values($ceBuckets);

                $peBuckets = $optionDataGrouped[$peKey] ?? [];
                ksort($peBuckets);
                $peCandles = array_values($peBuckets);

                // Options logic lines (Only 2 lines: Current Week Mid & Next Week Mid)
                $optionLogicLines = [
                    'cw_mid' => $cwMid, // Current week MidPoint (Green)
                    'nw_mid' => $nwMid, // Next week MidPoint (Red)
                ];

                // Option A: Strike Combined Average VWAP across CE & PE
                $allOptionTs = array_unique(array_merge(array_keys($ceBuckets), array_keys($peBuckets)));
                sort($allOptionTs);

                $combinedVwap = [];
                $currDate = null;
                $cumPV = 0.0;
                $cumVol = 0.0;

                foreach ($allOptionTs as $ts) {
                    $ce = $ceBuckets[$ts] ?? null;
                    $pe = $peBuckets[$ts] ?? null;

                    $date = $ce['date'] ?? $pe['date'] ?? null;
                    if ($date && $date !== $currDate) {
                        $currDate = $date;
                        $cumPV = 0.0;
                        $cumVol = 0.0;
                    }

                    $pvThisBucket = 0.0;
                    $volThisBucket = 0.0;

                    if ($ce) {
                        $typCe = ($ce['high'] + $ce['low'] + $ce['close']) / 3.0;
                        $volCe = max(1, (int)($ce['volume'] ?? 0));
                        $pvThisBucket += ($typCe * $volCe);
                        $volThisBucket += $volCe;
                    }
                    if ($pe) {
                        $typPe = ($pe['high'] + $pe['low'] + $pe['close']) / 3.0;
                        $volPe = max(1, (int)($pe['volume'] ?? 0));
                        $pvThisBucket += ($typPe * $volPe);
                        $volThisBucket += $volPe;
                    }

                    $cumPV += $pvThisBucket;
                    $cumVol += $volThisBucket;

                    $val = $cumVol > 0 ? round($cumPV / $cumVol, 2) : 0;
                    if ($val > 0) {
                        $combinedVwap[] = ['time' => $ts, 'value' => $val];
                    }
                }

                $ceInstKey = $instKeysMap[(string)$strike]['CE'] ?? null;
                $peInstKey = $instKeysMap[(string)$strike]['PE'] ?? null;
                if ($ceInstKey) $allSubscribedKeys[] = $ceInstKey;
                if ($peInstKey) $allSubscribedKeys[] = $peInstKey;

                $resultTiles[$tileId] = [
                    'id'                => $tileId,
                    'type'              => $type, // BOTH, CE, or PE
                    'strike'            => $strike,
                    'ce_instrument_key' => $ceInstKey,
                    'pe_instrument_key' => $peInstKey,
                    'ce_candles'        => $ceCandles,
                    'pe_candles'        => $peCandles,
                    'candles'           => $type === 'PE' ? $peCandles : $ceCandles,
                    'indicators'        => [
                        'vwap' => $combinedVwap,
                    ],
                    'logic_lines'       => $optionLogicLines,
                ];
            }
        }

        return response()->json([
            'success'             => true,
            'timeframe'           => $timeframe,
            'days_back'           => $daysBack,
            'expiry_date'         => $expiryDate,
            'cw_mid'              => $cwMid,
            'nw_mid'              => $nwMid,
            'pdc'                 => $pdc,
            'tiles'               => $resultTiles,
            'all_instrument_keys' => array_values(array_unique(array_filter($allSubscribedKeys))),
        ]);
    }

    /**
     * Authorize Upstox WebSocket Feed URL for live ticks.
     */
    public function getWsUrl(): JsonResponse
    {
        $token = config('services.upstox.analytics_token');
        if (!$token) {
            return response()->json(['error' => 'Upstox access token not configured in services.upstox.analytics_token'], 400);
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ])->get('https://api.upstox.com/v3/feed/market-data-feed/authorize');

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'error'   => 'Failed to fetch WS URL from Upstox',
                'details' => $response->body()
            ], $response->status());
        } catch (\Throwable $e) {
            return response()->json([
                'error'   => 'Exception fetching WS URL',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Align Carbon timestamp to given bucket minutes.
     */
    protected function alignToTimeframe(Carbon $dt, int $minutes): Carbon
    {
        $minute = (int)$dt->minute;
        $aligned = (int)(floor($minute / $minutes) * $minutes);
        return $dt->copy()->minute($aligned)->second(0);
    }

    /**
     * Compute EMA, SMA, VWMA, VWAP exclusively for Index & Future.
     */
    protected function enrichIndicators(array $candles): array
    {
        $ema9  = [];
        $ema21 = [];
        $sma20 = [];
        $vwma20 = [];
        $vwapSeries = [];

        $k9  = 2.0 / (9.0 + 1.0);
        $k21 = 2.0 / (21.0 + 1.0);

        $prevEma9  = null;
        $prevEma21 = null;

        $cumPV   = 0.0;
        $cumVol  = 0.0;
        $currDate = null;

        $closes = [];
        $volumes = [];

        $k246 = 2.0 / (246.0 + 1.0);
        $prevEma246 = null;
        $ema246 = [];
        $sma246 = [];
        $vwma246 = [];

        foreach ($candles as $c) {
            $t = $c['time'];
            $close = $c['close'];
            $vol   = max(1, $c['volume']);
            $typicalPrice = ($c['high'] + $c['low'] + $close) / 3.0;
            $candleDate   = $c['date'] ?? null;

            // Session VWAP reset each day
            if ($candleDate !== $currDate) {
                $currDate = $candleDate;
                $cumPV  = 0.0;
                $cumVol = 0.0;
            }

            $cumPV  += ($typicalPrice * $vol);
            $cumVol += $vol;
            $vwapVal = $cumVol > 0 ? round($cumPV / $cumVol, 2) : $close;
            $vwapSeries[] = ['time' => $t, 'value' => $vwapVal];

            // EMA 9
            if ($prevEma9 === null) {
                $prevEma9 = $close;
            } else {
                $prevEma9 = ($close * $k9) + ($prevEma9 * (1.0 - $k9));
            }
            $ema9[] = ['time' => $t, 'value' => round($prevEma9, 2)];

            // EMA 21
            if ($prevEma21 === null) {
                $prevEma21 = $close;
            } else {
                $prevEma21 = ($close * $k21) + ($prevEma21 * (1.0 - $k21));
            }
            $ema21[] = ['time' => $t, 'value' => round($prevEma21, 2)];

            // EMA 246
            if ($prevEma246 === null) {
                $prevEma246 = $close;
            } else {
                $prevEma246 = ($close * $k246) + ($prevEma246 * (1.0 - $k246));
            }
            $ema246[] = ['time' => $t, 'value' => round($prevEma246, 2)];

            // SMA 20 & VWMA 20
            $closes[]  = $close;
            $volumes[] = $vol;

            if (count($closes) >= 20) {
                $windowClose = array_slice($closes, -20);
                $windowVol   = array_slice($volumes, -20);

                $sma20[] = ['time' => $t, 'value' => round(array_sum($windowClose) / 20.0, 2)];

                $sumPV = 0.0;
                for ($w = 0; $w < 20; $w++) {
                    $sumPV += ($windowClose[$w] * $windowVol[$w]);
                }
                $sumV = array_sum($windowVol);
                $vwmaVal = $sumV > 0 ? round($sumPV / $sumV, 2) : $close;
                $vwma20[] = ['time' => $t, 'value' => $vwmaVal];
            }

            // SMA 246 & VWMA 246
            $period246 = min(246, count($closes));
            if ($period246 >= 10) {
                $windowClose246 = array_slice($closes, -$period246);
                $windowVol246   = array_slice($volumes, -$period246);

                $sma246[] = ['time' => $t, 'value' => round(array_sum($windowClose246) / (float)$period246, 2)];

                $sumPV246 = 0.0;
                for ($w = 0; $w < $period246; $w++) {
                    $sumPV246 += ($windowClose246[$w] * $windowVol246[$w]);
                }
                $sumV246 = array_sum($windowVol246);
                $vwmaVal246 = $sumV246 > 0 ? round($sumPV246 / $sumV246, 2) : $close;
                $vwma246[] = ['time' => $t, 'value' => $vwmaVal246];
            }
        }

        return [
            'ema9'    => $ema9,
            'ema21'   => $ema21,
            'ema246'  => $ema246,
            'sma20'   => $sma20,
            'sma246'  => $sma246,
            'vwma20'  => $vwma20,
            'vwma246' => $vwma246,
            'vwap'    => $vwapSeries,
        ];
    }

    /**
     * Resolve active option chains table.
     */
    protected function resolveOptionChainsTable(): string
    {
        if (function_exists('getTableName')) {
            $tbl = getTableName('option_chains');
            if (\Illuminate\Support\Facades\Schema::hasTable($tbl) && DB::table($tbl)->exists()) {
                return $tbl;
            }
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('option_chains_history') && DB::table('option_chains_history')->exists()) {
            return 'option_chains_history';
        }
        return 'option_chains';
    }

    /**
     * Resample Spot prices from option_chains and option_chains_history into candles.
     */
    protected function fetchIndexCandles(?string $startDate, int $minutes, ?string $expiryDate = null): array
    {
        $instKey = null;
        if ($expiryDate) {
            $expTs = DB::table('nse_expiries')->where('expiry_date', $expiryDate)->value('expiry');
            if ($expTs) {
                $instKey = DB::table('instruments')
                    ->where('name', 'NIFTY')
                    ->where('instrument_type', 'CE')
                    ->where('expiry', $expTs)
                    ->value('instrument_key');
            }
        }

        if (!$instKey) {
            // Fallback: pick any active NIFTY CE instrument present in option_chains
            $instKey = DB::table('option_chains')->where('instrument_key', 'LIKE', 'NSE_FO|%')->value('instrument_key') ?? 'NSE_FO|44698';
        }

        // 1. Query today's live option_chains
        $todayQuery = DB::table('option_chains')
            ->where('instrument_key', $instKey)
            ->where('underlying_spot_price', '>', 0)
            ->select(['captured_at', 'underlying_spot_price']);

        if ($startDate) {
            $todayQuery->where('captured_at', '>=', $startDate);
        }

        // 2. Query option_chains_history for previous sessions if needed
        $needHistory = (!$startDate || $startDate < today()->toDateString());
        $allRows = collect();

        if ($needHistory && \Illuminate\Support\Facades\Schema::hasTable('option_chains_history')) {
            $histQuery = DB::table('option_chains_history')
                ->where('instrument_key', $instKey)
                ->where('underlying_spot_price', '>', 0)
                ->select(['captured_at', 'underlying_spot_price']);

            if ($startDate) {
                $histQuery->where('captured_at', '>=', $startDate);
            } else {
                $histQuery->where('captured_at', '>=', today()->subDays(5)->toDateTimeString());
            }

            $allRows = $histQuery->orderBy('captured_at')->get();
        }

        $todayRows = $todayQuery->orderBy('captured_at')->get();
        $allRows = $allRows->concat($todayRows);

        $buckets = [];
        $prevClose = null;

        foreach ($allRows as $r) {
            $spot = (float)$r->underlying_spot_price;
            if ($spot <= 0) continue;

            $dt = Carbon::parse($r->captured_at, 'UTC');
            $bucketTs = $this->alignToTimeframe($dt, $minutes)->timestamp;

            if (!isset($buckets[$bucketTs])) {
                $open = $prevClose ?? $spot;
                $buckets[$bucketTs] = [
                    'time'   => $bucketTs,
                    'open'   => $open,
                    'high'   => max($open, $spot),
                    'low'    => min($open, $spot),
                    'close'  => $spot,
                    'volume' => 0,
                    'oi'     => 0,
                    'date'   => $dt->toDateString(),
                ];
                $prevClose = $spot;
            } else {
                $b = &$buckets[$bucketTs];
                $b['high']  = max($b['high'], $spot);
                $b['low']   = min($b['low'], $spot);
                $b['close'] = $spot;
                $prevClose  = $spot;
            }
        }

        // Ensure every candle has realistic body/wick definition
        foreach ($buckets as &$b) {
            if (abs($b['high'] - $b['low']) < 0.1) {
                $b['high'] = round($b['high'] + 1.25, 2);
                $b['low']  = round($b['low'] - 1.25, 2);
            }
        }
        unset($b);

        ksort($buckets);
        return array_values($buckets);
    }

    /**
     * Resample NIFTY Futures from ohlc_quotes and ohlc_quotes_history.
     */
    protected function fetchFuturesCandles(?string $startDate, int $minutes): array
    {
        $futKey = 'NSE_FO|48704';
        $futExpiry = DB::table('nse_expiries')
            ->where('trading_symbol', 'NIFTY')
            ->where('instrument_type', 'FUT')
            ->where('is_current', 1)
            ->value('expiry');

        if ($futExpiry) {
            $foundKey = DB::table('instruments')
                ->where('name', 'NIFTY')
                ->where('instrument_type', 'FUT')
                ->where('expiry', $futExpiry)
                ->value('instrument_key');
            if ($foundKey) $futKey = $foundKey;
        }

        // 1. Query today's live quotes
        $todayRows = collect();
        if (\Illuminate\Support\Facades\Schema::hasTable('ohlc_quotes')) {
            $todayQ = DB::table('ohlc_quotes')
                ->where('instrument_key', $futKey)
                ->select(['ts_at', 'open', 'high', 'low', 'close', 'volume']);

            if ($startDate) {
                $todayQ->where('ts_at', '>=', $startDate);
            }
            $todayRows = $todayQ->orderBy('ts_at')->get();
        }

        // 2. Query historical quotes if requested
        $histRows = collect();
        $needHistory = (!$startDate || $startDate < today()->toDateString());
        if ($needHistory && \Illuminate\Support\Facades\Schema::hasTable('ohlc_quotes_history')) {
            $histQ = DB::table('ohlc_quotes_history')
                ->where('instrument_key', $futKey)
                ->select(['ts_at', 'open', 'high', 'low', 'close', 'volume']);

            if ($startDate) {
                $histQ->where('ts_at', '>=', $startDate);
            } else {
                $histQ->where('ts_at', '>=', today()->subDays(5)->toDateTimeString());
            }
            $histRows = $histQ->orderBy('ts_at')->get();
        }

        $allRows = $histRows->concat($todayRows);
        $buckets = [];

        foreach ($allRows as $r) {
            $dt = Carbon::parse($r->ts_at, 'UTC');
            $bucketTs = $this->alignToTimeframe($dt, $minutes)->timestamp;

            if (!isset($buckets[$bucketTs])) {
                $buckets[$bucketTs] = [
                    'time'   => $bucketTs,
                    'open'   => (float)$r->open,
                    'high'   => (float)$r->high,
                    'low'    => (float)$r->low,
                    'close'  => (float)$r->close,
                    'volume' => (int)($r->volume ?? 0),
                    'oi'     => 0,
                    'date'   => $dt->toDateString(),
                ];
            } else {
                $b = &$buckets[$bucketTs];
                $b['high']   = max($b['high'], (float)$r->high);
                $b['low']    = min($b['low'], (float)$r->low);
                $b['close']  = (float)$r->close;
                $b['volume'] = max($b['volume'], (int)($r->volume ?? 0));
            }
        }

        ksort($buckets);
        return array_values($buckets);
    }
}
