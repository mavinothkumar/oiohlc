<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class OptionsBreadthMonitorController extends Controller
{
    /**
     * Render the main Options Breadth & Greek Monitor view
     */
    public function index(Request $request): View
    {
        $todayStr = Carbon::now('Asia/Kolkata')->toDateString();

        // 1. Fetch available dates from nse_working_days
        $availableDates = [];
        if (Schema::hasTable('nse_working_days')) {
            $availableDates = DB::table('nse_working_days')
                ->where('working_date', '<=', $todayStr)
                ->orderByDesc('working_date')
                ->limit(20)
                ->pluck('working_date')
                ->toArray();
        }

        if (empty($availableDates)) {
            $availableDates = [$todayStr];
        }

        $selectedDate = $request->input('date', $availableDates[0]);

        // 2. Fetch Expiries
        $expiries = [];
        if (Schema::hasTable('nse_expiries')) {
            $expiries = DB::table('nse_expiries')
                ->where('trading_symbol', 'NIFTY')
                ->where('instrument_type', 'OPT')
                ->orderBy('expiry_date')
                ->pluck('expiry_date')
                ->toArray();
        }

        $defaultExpiry = !empty($expiries) ? ($expiries[0] ?? $selectedDate) : $selectedDate;

        return view('options-breadth.index', [
            'availableDates' => $availableDates,
            'selectedDate'   => $selectedDate,
            'todayDate'      => $todayStr,
            'expiries'       => $expiries,
            'defaultExpiry'  => $defaultExpiry,
        ]);
    }

    /**
     * API: Get complete 5m options breadth, candlestick chart, and Greek matrix data
     */
    public function getData(Request $request): JsonResponse
    {
        $symbol   = $request->input('symbol', 'NIFTY');
        $date     = $request->input('date', Carbon::now('Asia/Kolkata')->toDateString());
        $range    = (int) $request->input('range', 5); // Default +/- 5 strikes (11 strikes)
        $metric   = $request->input('metric', 'signal'); // 'signal', 'vega', 'delta', 'oi', 'premium'

        $nowKolkata = Carbon::now('Asia/Kolkata');
        $isToday = ($date === $nowKolkata->toDateString());
        $curTimeStr = $nowKolkata->format('H:i');
        $isMarketHours = $isToday && ($curTimeStr >= '09:15' && $curTimeStr <= '15:30');

        // Resolve table
        $isLiveToday = $isToday && DB::table('option_chains')->limit(1)->exists();
        $table = $isLiveToday ? 'option_chains' : 'option_chains_history';

        // 1. Resolve Expiry
        $selectedExpiry = $request->input('expiry');
        if (!$selectedExpiry && Schema::hasTable('nse_expiries')) {
            $selectedExpiry = DB::table('nse_expiries')
                ->where('trading_symbol', $symbol)
                ->where('instrument_type', 'OPT')
                ->where('is_current', 1)
                ->value('expiry_date');

            if (!$selectedExpiry) {
                $selectedExpiry = DB::table('nse_expiries')
                    ->where('trading_symbol', $symbol)
                    ->where('instrument_type', 'OPT')
                    ->where('expiry_date', '>=', $date)
                    ->orderBy('expiry_date')
                    ->value('expiry_date');
            }
        }
        if (!$selectedExpiry) {
            $selectedExpiry = $date;
        }

        $startTs = "{$date} 09:00:00";
        $endTs   = "{$date} 15:35:00";

        // Cache TTL: For today, keep cache short (15s during market, 30s outside market)
        // so live/incoming 5-min candles are never blocked. For past dates, cache for 1 day.
        $cacheTtl = $isToday ? ($isMarketHours ? 15 : 30) : 86400;
        $reqStrike = $request->input('strike', 'auto');
        $cacheKey = "options_breadth_{$symbol}_{$date}_{$selectedExpiry}_{$range}_{$metric}_{$reqStrike}";

        if ($request->has('_t') || $request->boolean('refresh')) {
            Cache::forget($cacheKey);
        }

        $data = Cache::remember($cacheKey, $cacheTtl, function () use ($request, $table, $symbol, $selectedExpiry, $startTs, $endTs, $range, $metric, $date, $isToday, $isMarketHours, $nowKolkata) {
            // 2. Fetch Strikes and determine ATM strike
        $strikes = DB::table($table)
            ->where('trading_symbol', $symbol)
            ->where('expiry', $selectedExpiry)
            ->where('captured_at', '>=', $startTs)
            ->where('captured_at', '<=', $endTs)
            ->distinct()
            ->orderBy('strike_price')
            ->pluck('strike_price');

        if ($strikes->isEmpty()) {
            $altTable = ($table === 'option_chains') ? 'option_chains_history' : 'option_chains';
            $altStrikes = DB::table($altTable)
                ->where('trading_symbol', $symbol)
                ->where('expiry', $selectedExpiry)
                ->where('captured_at', '>=', $startTs)
                ->where('captured_at', '<=', $endTs)
                ->distinct()
                ->orderBy('strike_price')
                ->pluck('strike_price');

            if ($altStrikes->isNotEmpty()) {
                $table = $altTable;
                $strikes = $altStrikes;
            }
        }

        // Detect ATM strike
        $spotPrice = DB::table($table)
            ->where('trading_symbol', $symbol)
            ->where('expiry', $selectedExpiry)
            ->where('captured_at', '>=', $startTs)
            ->where('captured_at', '<=', $endTs)
            ->whereNotNull('underlying_spot_price')
            ->where('underlying_spot_price', '>', 0)
            ->orderBy('captured_at')
            ->value('underlying_spot_price');

        $atmStrike = 24000;
        if ($strikes->isNotEmpty()) {
            $atmStrike = $spotPrice ? $strikes->sortBy(fn($s) => abs($s - $spotPrice))->first() : $strikes->values()->get((int) floor($strikes->count() / 2));
        }

        $centerStrike = (int) $request->input('strike', $atmStrike);
        $strikesArr = $strikes->values()->all();
        $centerIdx = array_search($centerStrike, $strikesArr);
        if ($centerIdx === false) {
            $closest = $strikes->sortBy(fn($s) => abs($s - $centerStrike))->first() ?: $atmStrike;
            $centerIdx = array_search($closest, $strikesArr);
            $centerStrike = $closest;
        }

        // Target Basket strikes
        if ($range === 0) {
            $targetStrikes = collect([$centerStrike]);
        } else {
            $fromIdx = max(0, $centerIdx - $range);
            $toIdx   = min(count($strikesArr) - 1, $centerIdx + $range);
            $targetStrikes = collect(array_slice($strikesArr, $fromIdx, ($toIdx - $fromIdx + 1)));
        }

        // 3. Fetch Index 5m Candlestick Data
        $niftyCandles = $this->fetchIndexCandles($date, $symbol, $table, $selectedExpiry, $centerStrike);

        // 4. Fetch Options Snapshot Records for Target Strikes
        $optionsData = $this->buildOptionsBreadthData(
            $table,
            $symbol,
            $selectedExpiry,
            $date,
            $startTs,
            $endTs,
            $targetStrikes,
            $centerStrike,
            $niftyCandles,
            $metric
        );

        $lwChartCandles = array_map(function($c) {
            return [
                'time'  => $c['timestamp'],
                'open'  => $c['open'],
                'high'  => $c['high'],
                'low'   => $c['low'],
                'close' => $c['close'],
            ];
        }, $niftyCandles);

        $latestCandle = !empty($niftyCandles) ? end($niftyCandles) : null;

            return [
                'success'          => true,
                'date'             => $date,
                'expiry'           => $selectedExpiry,
                'center_strike'    => $centerStrike,
                'atm_strike'       => $atmStrike,
                'range'            => $range,
                'strikes'          => $targetStrikes->values(),
                'is_today'         => $isToday,
                'is_live'          => $isMarketHours,
                'server_time'      => $nowKolkata->format('H:i:s'),
                'latest_time'      => $latestCandle ? $latestCandle['time_str'] : '09:15',
                'latest_price'     => $latestCandle ? $latestCandle['close'] : 0,
                'nifty_candles'    => $niftyCandles,
                'lw_chart_candles' => $lwChartCandles,
                'timestamps'       => $optionsData['timestamps'],
                'breadth_by_time'  => $optionsData['breadth_by_time'],
                'matrix'           => $optionsData['matrix'],
                'summary'          => $optionsData['summary'],
            ];
        });

        return response()->json($data);
    }

    /**
     * Fetch Index 5m Candlestick Data (Uses ohlc_5m_quotes or synthesized from options spot/synth)
     */
    protected function fetchIndexCandles(string $date, string $symbol, string $table, string $expiry, int $baseStrike): array
    {
        $candles = [];
        $seen = [];

        if (Schema::hasTable('ohlc_5m_quotes')) {
            $dbCandles = DB::table('ohlc_5m_quotes')
                ->where(function($q) use ($symbol) {
                    $q->where('instrument_key', 'like', "%{$symbol}%")
                      ->orWhere('trading_symbol', $symbol)
                      ->orWhere('trading_symbol', 'Nifty 50');
                })
                ->where('instrument_type', 'INDEX')
                ->where('ts_at', '>=', "{$date} 09:00:00")
                ->where('ts_at', '<=', "{$date} 15:35:00")
                ->orderBy('ts_at')
                ->get();

            if ($dbCandles->count() >= 5) {
                foreach ($dbCandles as $c) {
                    $ts = Carbon::parse($c->ts_at, 'Asia/Kolkata');
                    $timeStr = $ts->format('H:i');
                    if ($timeStr < '09:15' || $timeStr > '15:25' || isset($seen[$timeStr])) continue;
                    $seen[$timeStr] = true;

                    $open  = round((float)$c->open, 2);
                    $high  = round((float)$c->high, 2);
                    $low   = round((float)$c->low, 2);
                    $close = round((float)$c->close, 2);

                    $candles[] = [
                        'time_str'  => $timeStr,
                        'timestamp' => Carbon::parse($c->ts_at, 'UTC')->timestamp,
                        'open'      => $open,
                        'high'      => $high,
                        'low'       => $low,
                        'close'     => $close,
                        'move_pts'  => round($close - $open, 2),
                        'move_pct'  => $open > 0 ? round((($close - $open) / $open) * 100, 3) : 0,
                        'color'     => $close >= $open ? 'green' : 'red',
                    ];
                }
            }
        }

        // Check ohlc_quotes for 1-minute INDEX candles and aggregate into 5-minute candles
        if (empty($candles) && Schema::hasTable('ohlc_quotes')) {
            $oneMinQuotes = DB::table('ohlc_quotes')
                ->where(function($q) use ($symbol) {
                    $q->where('instrument_key', 'like', "%{$symbol}%")
                      ->orWhere('trading_symbol', $symbol)
                      ->orWhere('trading_symbol', 'Nifty 50');
                })
                ->where('instrument_type', 'INDEX')
                ->where('ts_at', '>=', "{$date} 00:00:00")
                ->where('ts_at', '<=', "{$date} 23:59:59")
                ->orderBy('ts_at')
                ->get();

            if ($oneMinQuotes->isNotEmpty()) {
                $fiveMinGroups = [];
                foreach ($oneMinQuotes as $row) {
                    $ts = Carbon::parse($row->ts_at, 'Asia/Kolkata');
                    $timeStr = $ts->format('H:i');
                    if ($timeStr < '09:15' || $timeStr > '15:25') continue;

                    $minute = (int)$ts->format('i');
                    $bucketMinute = $minute - ($minute % 5);
                    $bucketTimeStr = $ts->format('H') . ':' . str_pad($bucketMinute, 2, '0', STR_PAD_LEFT);
                    $fiveMinGroups[$bucketTimeStr][] = $row;
                }

                foreach ($fiveMinGroups as $bucketTimeStr => $group) {
                    if (isset($seen[$bucketTimeStr])) continue;
                    $seen[$bucketTimeStr] = true;

                    $firstRow = $group[0];
                    $lastRow  = end($group);
                    $open  = round((float)$firstRow->open, 2);
                    $close = round((float)$lastRow->close, 2);
                    $high  = round((float)collect($group)->max('high'), 2);
                    $low   = round((float)collect($group)->min('low'), 2);

                    $cTs = Carbon::parse("{$date} {$bucketTimeStr}:00", 'Asia/Kolkata');

                    $candles[] = [
                        'time_str'  => $bucketTimeStr,
                        'timestamp' => Carbon::parse($cTs->toDateTimeString(), 'UTC')->timestamp,
                        'open'      => $open,
                        'high'      => $high,
                        'low'       => $low,
                        'close'     => $close,
                        'move_pts'  => round($close - $open, 2),
                        'move_pct'  => $open > 0 ? round((($close - $open) / $open) * 100, 3) : 0,
                        'color'     => $close >= $open ? 'green' : 'red',
                    ];
                }
            }
        }

        // Fallback: Generate candles from option chain spot prices or Synthetic Future
        if (empty($candles)) {
            $spotRows = DB::table($table)
                ->where('trading_symbol', $symbol)
                ->where('expiry', $expiry)
                ->where('captured_at', '>=', "{$date} 09:15:00")
                ->where('captured_at', '<=', "{$date} 15:30:00")
                ->whereNotNull('underlying_spot_price')
                ->where('underlying_spot_price', '>', 0)
                ->orderBy('captured_at')
                ->select('captured_at', 'underlying_spot_price')
                ->get()
                ->groupBy(fn($r) => Carbon::parse($r->captured_at)->format('H:i'));

            if ($spotRows->isNotEmpty()) {
                $prevClose = null;
                foreach ($spotRows as $timeStr => $group) {
                    if (isset($seen[$timeStr])) continue;
                    $seen[$timeStr] = true;

                    $first = (float)$group->first()->underlying_spot_price;
                    $last  = (float)$group->last()->underlying_spot_price;
                    $max   = (float)$group->max('underlying_spot_price');
                    $min   = (float)$group->min('underlying_spot_price');

                    $open = $prevClose ?: $first;
                    $close = $last;
                    $high = max($max, $open, $close);
                    $low  = min($min, $open, $close);
                    $prevClose = $close;

                    $cTs = Carbon::parse("{$date} {$timeStr}:00", 'Asia/Kolkata');

                    $candles[] = [
                        'time_str'  => $timeStr,
                        'timestamp' => Carbon::parse($cTs->toDateTimeString(), 'UTC')->timestamp,
                        'open'      => round($open, 2),
                        'high'      => round($high, 2),
                        'low'       => round($low, 2),
                        'close'     => round($close, 2),
                        'move_pts'  => round($close - $open, 2),
                        'move_pct'  => $open > 0 ? round((($close - $open) / $open) * 100, 3) : 0,
                        'color'     => $close >= $open ? 'green' : 'red',
                    ];
                }
            }
        }

        return $candles;
    }

    /**
     * Build Options Breadth, Tug-of-War Tug, and Matrix Heatmap
     */
    protected function buildOptionsBreadthData(
        string $table,
        string $symbol,
        string $expiry,
        string $date,
        string $startTs,
        string $endTs,
        $targetStrikes,
        int $centerStrike,
        array $niftyCandles,
        string $metric
    ): array {
        // Query CE & PE options records combined in a single indexed query
        $optRows = DB::table($table)
            ->where('trading_symbol', $symbol)
            ->where('expiry', $expiry)
            ->whereIn('strike_price', $targetStrikes)
            ->whereIn('option_type', ['CE', 'PE'])
            ->where('captured_at', '>=', $startTs)
            ->where('captured_at', '<=', $endTs)
            ->orderBy('captured_at')
            ->select('option_type', 'captured_at', 'strike_price', 'ltp', 'vega', 'delta', 'gamma', 'theta', 'iv', 'oi', 'volume', 'underlying_spot_price')
            ->get();

        // Index by strike and formatted timestamp
        $ceGrid = [];
        $peGrid = [];
        $timestampsSet = [];

        foreach ($optRows as $r) {
            $t = Carbon::parse($r->captured_at)->format('H:i');
            if ($r->option_type === 'CE') {
                $ceGrid[$r->strike_price][$t] = $r;
            } else {
                $peGrid[$r->strike_price][$t] = $r;
            }
            $timestampsSet[$t] = true;
        }

        // Align with nifty candle times or captured times
        $candleTimes = array_column($niftyCandles, 'time_str');
        $timestamps = !empty($candleTimes) ? $candleTimes : array_keys($timestampsSet);
        sort($timestamps);

        // Open values for baseline delta calculations
        $openTimes = !empty($timestamps) ? $timestamps[0] : '09:15';
        $openVegaCe = [];
        $openVegaPe = [];
        $openLtpCe  = [];
        $openLtpPe  = [];
        $strikeWeights = [];

        $totalBasketOi = 0;
        foreach ($targetStrikes as $stk) {
            $c = $ceGrid[$stk][$openTimes] ?? null;
            $p = $peGrid[$stk][$openTimes] ?? null;
            $openVegaCe[$stk] = $c ? (float)$c->vega : 0;
            $openVegaPe[$stk] = $p ? (float)$p->vega : 0;
            $openLtpCe[$stk]  = $c ? (float)$c->ltp : 0;
            $openLtpPe[$stk]  = $p ? (float)$p->ltp : 0;
            $stkOi = ($c ? (int)$c->oi : 0) + ($p ? (int)$p->oi : 0);
            $strikeWeights[$stk] = max($stkOi, 1000);
            $totalBasketOi += $strikeWeights[$stk];
        }

        // Normalize weights
        foreach ($targetStrikes as $stk) {
            $strikeWeights[$stk] = $totalBasketOi > 0 ? round(($strikeWeights[$stk] / $totalBasketOi) * 100, 2) : round(100 / count($targetStrikes), 2);
        }

        $breadthByTime = [];
        $matrix = [];
        $matrix['MASTER'] = [
            'id' => 'MASTER',
            'symbol' => 'ATM BASKET AVG',
            'name' => "ATM ±" . (int)floor(count($targetStrikes)/2) . " Strikes Composite",
            'weight' => 100.0,
            'is_master' => true,
            'cells' => [],
        ];

        foreach ($targetStrikes as $stk) {
            $matrix[$stk] = [
                'id' => $stk,
                'symbol' => "₹" . number_format($stk),
                'name' => ($stk == $centerStrike) ? "ATM Strike Pair" : (($stk > $centerStrike) ? "+OTM Call / ITM Put" : "-ITM Call / OTM Put"),
                'weight' => $strikeWeights[$stk],
                'is_master' => false,
                'is_atm' => ($stk == $centerStrike),
                'cells' => [],
            ];
        }

        // Loop over each 5-minute candle
        $prevCandleLtpCe = $openLtpCe;
        $prevCandleLtpPe = $openLtpPe;

        foreach ($timestamps as $cIdx => $timeStr) {
            $candleBullWeight = 0.0;
            $candleBearWeight = 0.0;
            $candleFlatWeight = 0.0;

            $candleBullCount = 0;
            $candleBearCount = 0;
            $candleFlatCount = 0;

            $strikeContributions = [];
            $basketCeVegaSum = 0;
            $basketPeVegaSum = 0;
            $basketDeltaSum = 0;
            $basketOiCeSum = 0;
            $basketOiPeSum = 0;

            $totalBasketBullSum = 0.0;
            $totalActiveWeight = 0.0;

            foreach ($targetStrikes as $stk) {
                $c = $ceGrid[$stk][$timeStr] ?? null;
                $p = $peGrid[$stk][$timeStr] ?? null;
                $wt = $strikeWeights[$stk] ?? (100.0 / count($targetStrikes));

                $cPrice = $c ? (float)$c->ltp : ($prevCandleLtpCe[$stk] ?? 0);
                $pPrice = $p ? (float)$p->ltp : ($prevCandleLtpPe[$stk] ?? 0);
                $cVega  = $c ? (float)$c->vega : 0;
                $pVega  = $p ? (float)$p->vega : 0;
                $cDelta = $c ? (float)$c->delta : 0.5;
                $pDelta = $p ? (float)$p->delta : -0.5;
                $cOi    = $c ? (int)$c->oi : ($prevOiCe[$stk] ?? 0);
                $pOi    = $p ? (int)$p->oi : ($prevOiPe[$stk] ?? 0);
                $cVol   = $c ? (int)$c->volume : ($prevVolCe[$stk] ?? 0);
                $pVol   = $p ? (int)$p->volume : ($prevVolPe[$stk] ?? 0);

                $basketCeVegaSum += $cVega;
                $basketPeVegaSum += $pVega;
                $basketDeltaSum += ($cDelta * $cOi + $pDelta * $pOi);
                $basketOiCeSum += $cOi;
                $basketOiPeSum += $pOi;

                // 5m Incremental Changes
                $dCePrice = round($cPrice - ($prevCandleLtpCe[$stk] ?? $cPrice), 2);
                $dPePrice = round($pPrice - ($prevCandleLtpPe[$stk] ?? $pPrice), 2);
                $dOiCe    = $cOi - ($prevOiCe[$stk] ?? $cOi);
                $dOiPe    = $pOi - ($prevOiPe[$stk] ?? $pOi);
                $dVolCe   = max(0, $cVol - ($prevVolCe[$stk] ?? $cVol));
                $dVolPe   = max(0, $pVol - ($prevVolPe[$stk] ?? $pVol));
                $dCeVega  = round($cVega - ($openVegaCe[$stk] ?? $cVega), 3);
                $dPeVega  = round($pVega - ($openVegaPe[$stk] ?? $pVega), 3);

                // --- 1. FACTOR: OI Buildup Flow (-1.0 to +1.0) ---
                $oiCeScore = 0.0;
                if ($dOiCe > 0) {
                    $oiCeScore = ($dCePrice >= 0) ? 0.9 : -0.9; // Long build vs Call writing
                } elseif ($dOiCe < 0) {
                    $oiCeScore = ($dCePrice >= 0) ? 0.4 : -0.4; // Short covering vs Long unwinding
                }

                $oiPeScore = 0.0;
                if ($dOiPe > 0) {
                    $oiPeScore = ($dPePrice >= 0) ? -0.9 : 0.9; // Put buying vs Put writing support
                } elseif ($dOiPe < 0) {
                    $oiPeScore = ($dPePrice >= 0) ? -0.4 : 0.4;
                }
                $factorOi = ($oiCeScore + $oiPeScore) / 2.0;

                // --- 2. FACTOR: Vega & Volatility Skew (-1.0 to +1.0) ---
                // If CE Vega expands relative to PE Vega -> Bullish skew
                $factorVega = tanh(($dCeVega - $dPeVega) * 1.5);

                // --- 3. FACTOR: Volume Dominance (-1.0 to +1.0) ---
                $totVolCandle = $dVolCe + $dVolPe;
                $factorVol = ($totVolCandle > 0) ? (($dVolCe - $dVolPe) / $totVolCandle) : 0.0;

                // --- 4. FACTOR: Price & Delta Momentum (-1.0 to +1.0) ---
                $combLtp = max(5.0, $cPrice + $pPrice);
                $factorPrice = tanh(($dCePrice - $dPePrice) / ($combLtp * 0.04));

                // Continuous Multi-Factor Composite Score for this Strike (-1.0 = Max Bear, +1.0 = Max Bull)
                $strikeComposite = (0.35 * $factorOi) + (0.25 * $factorVega) + (0.20 * $factorVol) + (0.20 * $factorPrice);
                $strikeComposite = max(-1.0, min(1.0, $strikeComposite));

                // Convert to continuous 0% - 100% Bull Probability for this strike
                $strikeBullPct = (($strikeComposite + 1.0) / 2.0) * 100.0;
                $totalBasketBullSum += ($wt * ($strikeBullPct / 100.0));
                $totalActiveWeight += $wt;

                // Net points contributed to index
                $strikePts = round(($strikeComposite * ($wt / 8.0)), 2);

                if ($strikeBullPct >= 54) {
                    $color = 'green';
                    $candleBullCount++;
                } elseif ($strikeBullPct <= 46) {
                    $color = 'red';
                    $candleBearCount++;
                } else {
                    $color = 'gray';
                    $candleFlatCount++;
                }

                $strikeContributions[] = [
                    'strike'     => $stk,
                    'name'       => "₹" . number_format($stk) . ($stk == $centerStrike ? ' (ATM)' : ''),
                    'weight'     => $wt,
                    'bull_pct'   => round($strikeBullPct, 1),
                    'bear_pct'   => round(100 - $strikeBullPct, 1),
                    'ce_ltp'     => $cPrice,
                    'pe_ltp'     => $pPrice,
                    'd_ce_ltp'   => $dCePrice,
                    'd_pe_ltp'   => $dPePrice,
                    'ce_vega'    => $cVega,
                    'pe_vega'    => $pVega,
                    'd_ce_vega'  => $dCeVega,
                    'd_pe_vega'  => $dPeVega,
                    'pts'        => $strikePts,
                    'color'      => $color,
                    'signal'     => ($strikeBullPct >= 60) ? 'BUY FLOW' : (($strikeBullPct <= 40) ? 'SELL FLOW' : 'NEUTRAL'),
                ];

                $matrix[$stk]['cells'][$timeStr] = [
                    'time'     => $timeStr,
                    'color'    => $color,
                    'bull_pct' => round($strikeBullPct),
                    'ce_ltp'   => $cPrice,
                    'pe_ltp'   => $pPrice,
                    'd_ce'     => $dCePrice,
                    'd_pe'     => $dPePrice,
                    'd_ce_v'   => $dCeVega,
                    'd_pe_v'   => $dPeVega,
                    'pts'      => $strikePts,
                ];

                $prevCandleLtpCe[$stk] = $cPrice;
                $prevCandleLtpPe[$stk] = $pPrice;
                $prevOiCe[$stk]        = $cOi;
                $prevOiPe[$stk]        = $pOi;
                $prevVolCe[$stk]       = $cVol;
                $prevVolPe[$stk]       = $pVol;
            }

            // Continuous Basket Aggregate Tug-of-War Breadth
            if ($totalActiveWeight > 0) {
                $rawUpPct = ($totalBasketBullSum / $totalActiveWeight) * 100.0;
                $upPct = (int) round($rawUpPct);
                $downPct = 100 - $upPct;
                $flatPct = max(0, 100 - abs($upPct - $downPct) * 2);
            } else {
                $upPct = 50;
                $downPct = 50;
                $flatPct = 100;
            }

            // Master Composite Row Cell
            $masterColor = ($upPct >= 54) ? 'green' : (($upPct <= 46) ? 'red' : 'gray');
            $matrix['MASTER']['cells'][$timeStr] = [
                'time'  => $timeStr,
                'color' => $masterColor,
                'up_pct' => $upPct,
                'down_pct' => $downPct,
            ];

            // Net Index Pressure Points
            $netPts = round(array_sum(array_column($strikeContributions, 'pts')), 2);

            // Verdict
            if ($upPct >= 70) {
                $verdict = "🔥 Dominant Bullish Impulse: Strong Call Buying & Put Decay across {$upPct}% of option surface.";
            } elseif ($downPct >= 70) {
                $verdict = "🚨 Dominant Bearish Impulse: Strong Put Buying & Call Writing across {$downPct}% of option surface.";
            } elseif ($upPct >= 55) {
                $verdict = "Mild Bullish Bias: Buyers have marginal upper hand (+{$upPct}% vs {$downPct}%).";
            } elseif ($downPct >= 55) {
                $verdict = "Mild Bearish Bias: Sellers have marginal upper hand (-{$downPct}% vs {$upPct}%).";
            } else {
                $verdict = "Balanced Tug-of-War: Extrinsic decay & delta balanced in equilibrium ({$upPct}% Buy vs {$downPct}% Sell).";
            }

            // Sort contributions: leaders first, draggers last
            usort($strikeContributions, fn($a, $b) => $b['pts'] <=> $a['pts']);

            $pcr = $basketOiCeSum > 0 ? round($basketOiPeSum / $basketOiCeSum, 2) : 1.0;

            $breadthByTime[$timeStr] = [
                'time_str'       => $timeStr,
                'candle_idx'     => $cIdx,
                'up_count'       => $candleBullCount,
                'down_count'     => $candleBearCount,
                'flat_count'     => $candleFlatCount,
                'up_weight'      => $upPct,
                'down_weight'    => $downPct,
                'flat_weight'    => $flatPct,
                'net_points'     => $netPts,
                'verdict'        => $verdict,
                'contributions'  => $strikeContributions,
                'greeks'         => [
                    'avg_ce_vega' => round($basketCeVegaSum / max(1, count($targetStrikes)), 3),
                    'avg_pe_vega' => round($basketPeVegaSum / max(1, count($targetStrikes)), 3),
                    'net_delta'   => round($basketDeltaSum / 1000, 1) . 'k',
                    'pcr'         => $pcr,
                ],
            ];
        }

        return [
            'timestamps'      => $timestamps,
            'breadth_by_time' => $breadthByTime,
            'matrix'          => array_values($matrix),
            'summary'         => [
                'strike_count'  => count($targetStrikes),
                'candles_count' => count($timestamps),
            ],
        ];
    }
}
