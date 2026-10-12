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

        // 5. Grid Layout (Supported: 1x1, 2x2, 3x2, 4x2, 3x3, 4x3, 3x4)
        $allowedLayouts = ['1x1', '2x2', '3x2', '4x2', '3x3', '4x3', '3x4'];
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
        } elseif ($layout === '4x3' || $layout === '3x4') {
            // 12 tiles: Tile 0 = INDEX, Tile 1 = FUT, Tiles 2..11 = 10 strikes around ATM
            $tiles[] = [
                'id'        => 0,
                'type'      => 'INDEX',
                'strike'    => 0,
                'offset'    => 0,
                'is_center' => false,
                'label'     => 'NIFTY 50 INDEX',
            ];
            $tiles[] = [
                'id'        => 1,
                'type'      => 'FUT',
                'strike'    => 0,
                'offset'    => 0,
                'is_center' => false,
                'label'     => 'NIFTY FUTURE',
            ];

            $offsets = [-4, -3, -2, -1, 0, 1, 2, 3, 4, 5];
            for ($i = 0; $i < 10; $i++) {
                $offset = $offsets[$i];
                $strike = $atm + ($offset * $step);
                $tiles[] = [
                    'id'        => $i + 2,
                    'type'      => 'BOTH',
                    'strike'    => $strike,
                    'offset'    => $offset,
                    'is_center' => ($offset === 0),
                    'label'     => number_format($strike, 0, '', '') . ' CE & PE',
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

        // 2. Process each tile in tilesReq
        $resultTiles = [];
        foreach ($tilesReq as $tile) {
            $tileId = $tile['id'];
            $type   = strtoupper($tile['type'] ?? 'BOTH');
            $strike = (float)($tile['strike'] ?? 0);

            if ($type === 'INDEX') {
                $candles = $this->fetchIndexCandles($startDate, $bucketMinutes, $expiryDate);
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
                    'id'          => $tileId,
                    'type'        => 'INDEX',
                    'strike'      => 0,
                    'candles'     => $candles,
                    'indicators'  => $enriched,
                    'logic_lines' => $logicLines,
                ];
            } elseif ($type === 'FUT') {
                $candles = $this->fetchFuturesCandles($startDate, $bucketMinutes);
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
                    'id'          => $tileId,
                    'type'        => 'FUT',
                    'strike'      => 0,
                    'candles'     => $candles,
                    'indicators'  => $enriched,
                    'logic_lines' => $logicLines,
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

                $resultTiles[$tileId] = [
                    'id'          => $tileId,
                    'type'        => $type, // BOTH, CE, or PE
                    'strike'      => $strike,
                    'ce_candles'  => $ceCandles,
                    'pe_candles'  => $peCandles,
                    'candles'     => $type === 'PE' ? $peCandles : $ceCandles,
                    'indicators'  => null, // NO EMA/VWMA/VWAP for options!
                    'logic_lines' => $optionLogicLines,
                ];
            }
        }

        return response()->json([
            'success'     => true,
            'timeframe'   => $timeframe,
            'days_back'   => $daysBack,
            'expiry_date' => $expiryDate,
            'cw_mid'      => $cwMid,
            'nw_mid'      => $nwMid,
            'pdc'         => $pdc,
            'tiles'       => $resultTiles,
        ]);
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
     * Resample Spot prices from option_chains into candles via indexed instrument key.
     */
    protected function fetchIndexCandles(?string $startDate, int $minutes, ?string $expiryDate = null): array
    {
        $table = $this->resolveOptionChainsTable();

        $instKey = 'NSE_FO|44600';
        if ($expiryDate) {
            $expTs = DB::table('nse_expiries')->where('expiry_date', $expiryDate)->value('expiry');
            if ($expTs) {
                $foundKey = DB::table('instruments')
                    ->where('name', 'NIFTY')
                    ->where('instrument_type', 'CE')
                    ->where('expiry', $expTs)
                    ->value('instrument_key');
                if ($foundKey) {
                    $instKey = $foundKey;
                }
            }
        }

        $query = DB::table($table)
            ->where('instrument_key', $instKey)
            ->select(['captured_at', 'underlying_spot_price'])
            ->orderBy('captured_at');

        if ($startDate) {
            $query->where('captured_at', '>=', $startDate);
        } else {
            $query->where('captured_at', '>=', today()->subDays(5)->toDateTimeString());
        }

        $rows = $query->get();
        $buckets = [];
        $prevClose = null;

        foreach ($rows as $r) {
            $spot = (float)$r->underlying_spot_price;
            if ($spot <= 0) continue;

            // DB captured_at is IST "YYYY-MM-DD HH:MM:SS". Parsing as UTC preserves exact IST clock numbers for Lightweight Charts.
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

        // Ensure every candle has realistic body/wick definition for crisp display
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
     * Resample NIFTY Futures from ohlc_quotes_history.
     */
    protected function fetchFuturesCandles(?string $startDate, int $minutes): array
    {
        $futKey = 'NSE_FO|48704';
        $table = function_exists('getTableName') ? getTableName('ohlc_quotes') : 'ohlc_quotes_history';
        if (!\Illuminate\Support\Facades\Schema::hasTable($table)) {
            $table = 'ohlc_quotes';
        }

        $query = DB::table($table)
            ->where('instrument_key', $futKey)
            ->select(['ts_at', 'open', 'high', 'low', 'close', 'volume'])
            ->orderBy('ts_at');

        if ($startDate) {
            $query->where('ts_at', '>=', $startDate);
        } else {
            $query->where('ts_at', '>=', today()->subDays(5)->toDateTimeString());
        }

        $rows = $query->get();
        $buckets = [];

        foreach ($rows as $r) {
            // DB ts_at is IST "YYYY-MM-DD HH:MM:SS". Parsing as UTC preserves exact IST clock numbers for Lightweight Charts.
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
