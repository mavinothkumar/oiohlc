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
        if (!$selectedExpiry) {
            if ($isToday && Schema::hasTable('nse_expiries')) {
                $selectedExpiry = DB::table('nse_expiries')
                    ->where('trading_symbol', $symbol)
                    ->where('instrument_type', 'OPT')
                    ->where('is_current', 1)
                    ->value('expiry_date');
            }

            // For past dates: check expired_expiries first (<1ms, indexed)
            if (!$selectedExpiry && !$isToday && Schema::hasTable('expired_expiries')) {
                $selectedExpiry = DB::table('expired_expiries')
                    ->where('underlying_symbol', $symbol)
                    ->where('instrument_type', 'OPT')
                    ->where('expiry_date', '>=', $date)
                    ->orderBy('expiry_date')
                    ->value('expiry_date');
            }

            // For past dates or fallback: resolve from option_chains_history directly using composite index (<100ms)
            if (!$selectedExpiry && Schema::hasTable('option_chains_history')) {
                $selectedExpiry = DB::table(DB::raw("option_chains_history USE INDEX (idx_och_sym_exp_cap)"))
                    ->where('trading_symbol', $symbol)
                    ->where('captured_at', '>=', "{$date} 09:00:00")
                    ->where('captured_at', '<=', "{$date} 23:59:59")
                    ->where('expiry', '>=', $date)
                    ->orderBy('expiry')
                    ->value('expiry');
            }

            // Fallback: check nse_expiries upcoming
            if (!$selectedExpiry && Schema::hasTable('nse_expiries')) {
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

        $startTs = "{$date} 08:00:00";
        $endTs   = "{$date} 23:59:59";

        $isBank = ($symbol === 'BANKNIFTY' || str_contains($symbol, 'BANK') || str_contains($symbol, 'Bank'));
        $defaultStrike = $isBank ? 60000 : 24000;
        $strikeStep    = $isBank ? 100 : 50;

        // Multi-timeframe interval support: 1, 3, 5, 15, 30, 60, 375 (1D)
        $interval = \App\Services\TimeframeResampler::normalizeInterval($request->input('interval', $request->input('timeframe', 5)));

        // Cache TTL: For today, keep cache short (15s during market, 30s outside market)
        // so live/incoming candles are never blocked. For past dates, cache for 1 day.
        $cacheTtl = $isToday ? ($isMarketHours ? 15 : 30) : 86400;
        $reqStrike = $request->input('strike', 'auto');
        $basketMode = $request->input('basket_mode', 'dynamic'); // 'dynamic' (auto-floating ATM) or 'fixed'
        $basis = strtolower($request->input('basis', 'spot')) === 'future' ? 'future' : 'spot';
        $cacheKey = "options_breadth_{$symbol}_{$date}_{$selectedExpiry}_{$range}_{$metric}_{$reqStrike}_{$basketMode}_{$interval}_{$basis}";

        if ($request->boolean('refresh')) {
            Cache::store('file')->forget($cacheKey);
        }

        $data = Cache::store('file')->remember($cacheKey, $cacheTtl, function () use ($request, $table, $symbol, $selectedExpiry, $startTs, $endTs, $range, $metric, $date, $isToday, $isMarketHours, $nowKolkata, $basketMode, $interval, $defaultStrike, $strikeStep, $basis) {
            // Multi-Day Day-wise view (1D / 375m): Load historical daily candles with daily options balance
            if ($interval === 375) {
                return $this->getDailyMultiDayOptionsBreadthData($date, $symbol, $basis, $range, $basketMode, $selectedExpiry);
            }

            // 2. Fetch Strikes and determine ATM strike
            $indexHint = $this->getOptimalIndexHint($table);
            $latestCap = DB::table(DB::raw("{$table}{$indexHint}"))
                ->where('captured_at', '>=', $startTs)
                ->where('captured_at', '<=', $endTs)
                ->where('trading_symbol', $symbol)
                ->where('expiry', $selectedExpiry)
                ->orderByDesc('captured_at')
                ->value('captured_at');

            $strikes = collect([]);
            if ($latestCap) {
                $strikes = DB::table(DB::raw("{$table}{$indexHint}"))
                    ->where('captured_at', $latestCap)
                    ->where('trading_symbol', $symbol)
                    ->where('expiry', $selectedExpiry)
                    ->distinct()
                    ->orderBy('strike_price')
                    ->pluck('strike_price');
            }

            if ($strikes->isEmpty()) {
                $strikes = DB::table(DB::raw("{$table}{$indexHint}"))
                    ->where('captured_at', '>=', $startTs)
                    ->where('captured_at', '<=', $endTs)
                    ->where('trading_symbol', $symbol)
                    ->where('expiry', $selectedExpiry)
                    ->distinct()
                    ->orderBy('strike_price')
                    ->pluck('strike_price');
            }

            if ($strikes->isEmpty()) {
                $altTable = ($table === 'option_chains') ? 'option_chains_history' : 'option_chains';
                $altHint = $this->getOptimalIndexHint($altTable);
                $altStrikes = DB::table(DB::raw("{$altTable}{$altHint}"))
                    ->where('captured_at', '>=', $startTs)
                    ->where('captured_at', '<=', $endTs)
                    ->where('trading_symbol', $symbol)
                    ->where('expiry', $selectedExpiry)
                    ->distinct()
                    ->orderBy('strike_price')
                    ->pluck('strike_price');

                if ($altStrikes->isNotEmpty()) {
                    $table = $altTable;
                    $indexHint = $altHint;
                    $strikes = $altStrikes;
                }
            }

            // If strikes are still empty, the selectedExpiry might be mismatched (e.g. November expiry requested on a June date).
            // Auto-recover by resolving the true active expiry for this date.
            if ($strikes->isEmpty()) {
                foreach ([$table, 'option_chains_history', 'option_chains'] as $candidateTable) {
                    if (!Schema::hasTable($candidateTable)) continue;
                    $candidateHint = $this->getOptimalIndexHint($candidateTable);
                    $realExpiry = DB::table(DB::raw("{$candidateTable}{$candidateHint}"))
                        ->where('trading_symbol', $symbol)
                        ->where('captured_at', '>=', $startTs)
                        ->where('captured_at', '<=', $endTs)
                        ->where('expiry', '>=', $date)
                        ->orderBy('expiry')
                        ->value('expiry');

                    if ($realExpiry) {
                        $recoveredStrikes = DB::table(DB::raw("{$candidateTable}{$candidateHint}"))
                            ->where('trading_symbol', $symbol)
                            ->where('expiry', $realExpiry)
                            ->where('captured_at', '>=', $startTs)
                            ->where('captured_at', '<=', $endTs)
                            ->distinct()
                            ->orderBy('strike_price')
                            ->pluck('strike_price');

                        if ($recoveredStrikes->isNotEmpty()) {
                            $table = $candidateTable;
                            $indexHint = $candidateHint;
                            $selectedExpiry = $realExpiry;
                            $strikes = $recoveredStrikes;
                            break;
                        }
                    }
                }
            }

            // Auto-migration on demand: If this historical date has no option contracts in option_chains_history,
            // automatically migrate this single date from expired_ohlc or Upstox on-the-fly!
            if ($strikes->isEmpty() && !$isToday) {
                try {
                    if ($isBank) {
                        \Illuminate\Support\Facades\Artisan::call('options:sync-banknifty-history', ['--date' => $date]);
                    } elseif (Schema::hasTable('expired_ohlc')) {
                        \Illuminate\Support\Facades\Artisan::call('options:fill-history-from-expired', ['--date' => $date]);
                    }

                    $table = 'option_chains_history';
                    $indexHint = $this->getOptimalIndexHint($table);
                    $realExpiry = DB::table(DB::raw("{$table}{$indexHint}"))
                        ->where('trading_symbol', $symbol)
                        ->where('captured_at', '>=', $startTs)
                        ->where('captured_at', '<=', $endTs)
                        ->where('expiry', '>=', $date)
                        ->orderBy('expiry')
                        ->value('expiry');

                    if ($realExpiry) {
                        $selectedExpiry = $realExpiry;
                        $strikes = DB::table(DB::raw("{$table}{$indexHint}"))
                            ->where('trading_symbol', $symbol)
                            ->where('expiry', $selectedExpiry)
                            ->where('captured_at', '>=', $startTs)
                            ->where('captured_at', '<=', $endTs)
                            ->distinct()
                            ->orderBy('strike_price')
                            ->pluck('strike_price');
                    }
                } catch (\Throwable $e) {
                    // Ignore and proceed to defensive return
                }
            }

        // Defensive check: If date still has no option contracts in database
        if ($strikes->isEmpty()) {
            $emptyCandles = $this->fetchIndexCandles($date, $symbol, $table, $selectedExpiry, $defaultStrike, $interval, $basis);
            if (!empty($emptyCandles)) {
                $lastCandle = end($emptyCandles);
                if (!empty($lastCandle['close']) && $lastCandle['close'] > 0) {
                    $defaultStrike = (int)(round($lastCandle['close'] / (float)$strikeStep) * $strikeStep);
                }
            }
            return [
                'success'          => false,
                'basis'            => $basis,
                'error'            => "No option strikes found for {$symbol} on {$date}. Please run migration for this date or select an available date.",
                'date'             => $date,
                'interval'         => $interval,
                'interval_label'   => ($interval === 375 ? '1D' : ($interval === 60 ? '60m' : "{$interval}m")),
                'expiry'           => $selectedExpiry,
                'center_strike'    => $defaultStrike,
                'atm_strike'       => $defaultStrike,
                'basket_mode'      => $basketMode,
                'range'            => $range,
                'strikes'          => [],
                'is_today'         => $isToday,
                'is_live'          => $isMarketHours,
                'server_time'      => $nowKolkata->format('H:i:s'),
                'latest_time'      => !empty($emptyCandles) ? end($emptyCandles)['time_str'] : '09:15',
                'latest_price'     => !empty($emptyCandles) ? end($emptyCandles)['close'] : 0,
                'nifty_candles'    => $emptyCandles,
                'lw_chart_candles' => array_map(fn($c) => ['time' => $c['timestamp'], 'open' => $c['open'], 'high' => $c['high'], 'low' => $c['low'], 'close' => $c['close']], $emptyCandles),
                'timestamps'       => [],
                'breadth_by_time'  => [],
                'matrix'           => [],
                'summary'          => ['strike_count' => 0, 'candles_count' => 0, 'smart_money_traps' => []],
            ];
        }

        // Detect ATM strike
        $spotPrice = null;
        if (!empty($latestCap)) {
            $spotPrice = DB::table(DB::raw("{$table}{$indexHint}"))
                ->where('captured_at', $latestCap)
                ->where('trading_symbol', $symbol)
                ->where('expiry', $selectedExpiry)
                ->whereNotNull('underlying_spot_price')
                ->where('underlying_spot_price', '>', 0)
                ->value('underlying_spot_price');
        }
        if (!$spotPrice) {
            $spotPrice = DB::table(DB::raw("{$table}{$indexHint}"))
                ->where('captured_at', '>=', $startTs)
                ->where('captured_at', '<=', $endTs)
                ->where('trading_symbol', $symbol)
                ->where('expiry', $selectedExpiry)
                ->whereNotNull('underlying_spot_price')
                ->where('underlying_spot_price', '>', 0)
                ->orderByDesc('captured_at')
                ->value('underlying_spot_price');
        }

        $atmStrike = $defaultStrike;
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

        // Expanded query basket for dynamic floating ATM tracking
        if ($basketMode === 'dynamic') {
            $fromQueryIdx = max(0, $centerIdx - ($range + 10));
            $toQueryIdx   = min(count($strikesArr) - 1, $centerIdx + ($range + 10));
            $queryStrikes = collect(array_slice($strikesArr, $fromQueryIdx, ($toQueryIdx - $fromQueryIdx + 1)));
        } else {
            $queryStrikes = $targetStrikes;
        }

        // 3. Fetch Index Candlestick Data for target interval
        $niftyCandles = $this->fetchIndexCandles($date, $symbol, $table, $selectedExpiry, $centerStrike, $interval, $basis);

        // 4. Fetch Options Snapshot Records for Target Strikes
        $optionsData = $this->buildOptionsBreadthData(
            $table,
            $symbol,
            $selectedExpiry,
            $date,
            $startTs,
            $endTs,
            $queryStrikes,
            $centerStrike,
            $niftyCandles,
            $metric,
            $basketMode,
            $strikesArr,
            $range,
            $spotPrice ?: 23000,
            $interval
        );

        // Fetch previous trading date candles for seamless history pan & SuperTrend burn-in
        $prevTradingDate = DB::table('nse_working_days')
            ->where('working_date', '<', $date)
            ->orderByDesc('working_date')
            ->value('working_date');
        if (!$prevTradingDate) {
            $prevTs = DB::table('ohlc_5m_quotes')
                ->where('ts_at', '<', "{$date} 00:00:00")
                ->orderByDesc('ts_at')
                ->value('ts_at');
            $prevTradingDate = $prevTs ? Carbon::parse($prevTs, 'Asia/Kolkata')->toDateString() : Carbon::parse($date)->subDay()->toDateString();
        }
        $yesterdayCandles = $this->fetchIndexCandles($prevTradingDate, $symbol, $table, $selectedExpiry, $spotPrice ?: 23000, $interval, $basis);

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
                'basis'            => $basis,
                'date'             => $date,
                'interval'         => $interval,
                'interval_label'   => ($interval === 375 ? '1D' : ($interval === 60 ? '60m' : "{$interval}m")),
                'expiry'           => $selectedExpiry,
                'yesterday_date'   => $prevTradingDate,
                'yesterday_candles'=> $yesterdayCandles,
                'center_strike'    => $centerStrike,
                'atm_strike'       => $atmStrike,
                'basket_mode'      => $basketMode,
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
     * Build Day-wise Multi-Day Options Breadth Data
     * Computes net Call vs Put OI flow and balance for each day across available trading days.
     */
    protected function getDailyMultiDayOptionsBreadthData(string $date, string $symbol, string $basis, int $range, string $basketMode, ?string $selectedExpiry): array
    {
        // 1. Resolve trading days up to $date
        $allDays = DB::table('ohlc_5m_quotes')
            ->where('instrument_type', 'INDEX')
            ->where('ts_at', '<=', "{$date} 23:59:59")
            ->selectRaw('DATE(ts_at) as dt')
            ->groupBy('dt')
            ->havingRaw('count(*) >= 5')
            ->orderBy('dt', 'asc')
            ->pluck('dt')
            ->toArray();

        if (empty($allDays)) {
            $allDays = DB::table('nse_working_days')
                ->where('working_date', '<=', $date)
                ->orderBy('working_date', 'asc')
                ->pluck('working_date')
                ->toArray();
        }

        $tradingDays = array_slice($allDays, -30);
        if (empty($tradingDays)) {
            $tradingDays = [$date];
        }

        // 2. Fetch daily index candles from daily_ohlc_quotes or ohlc_5m_quotes
        $dailyIndexQuotes = DB::table('daily_ohlc_quotes')
            ->where('symbol_name', 'NIFTY')
            ->where('option_type', 'INDEX')
            ->whereIn('quote_date', $tradingDays)
            ->get()
            ->keyBy('quote_date');

        $fiveMinIndexQuotes = DB::table('ohlc_5m_quotes')
            ->where('instrument_type', 'INDEX')
            ->where(function($q) {
                $q->where('instrument_key', 'like', '%Nifty 50%')
                  ->orWhere('trading_symbol', 'Nifty 50')
                  ->orWhere('trading_symbol', 'NIFTY');
            })
            ->whereIn(DB::raw('DATE(ts_at)'), $tradingDays)
            ->orderBy('ts_at', 'asc')
            ->get(['ts_at', 'open', 'high', 'low', 'close', 'volume'])
            ->groupBy(fn($r) => Carbon::parse($r->ts_at)->toDateString());

        $niftyCandles = [];
        $lwChartCandles = [];
        $timestamps = [];

        foreach ($tradingDays as $dt) {
            $open = 0; $high = 0; $low = 0; $close = 0; $vol = 0;

            if (isset($fiveMinIndexQuotes[$dt]) && $fiveMinIndexQuotes[$dt]->isNotEmpty()) {
                $dayQuotes = $fiveMinIndexQuotes[$dt];
                $first = $dayQuotes->first();
                $last = $dayQuotes->last();
                $open = (float)$first->open;
                $close = (float)$last->close;
                $high = (float)$dayQuotes->max('high');
                $low = (float)$dayQuotes->min('low');
                $vol = (int)$dayQuotes->sum('volume');
            } elseif (isset($dailyIndexQuotes[$dt])) {
                $dq = $dailyIndexQuotes[$dt];
                $open = (float)$dq->open;
                $high = (float)$dq->high;
                $low = (float)$dq->low;
                $close = (float)$dq->close;
                $vol = (int)$dq->volume;
            }

            if ($open <= 0 && $close <= 0) continue;
            if ($high <= 0) $high = max($open, $close);
            if ($low <= 0) $low = min($open, $close);

            $ts = Carbon::parse("{$dt} 09:15:00", 'Asia/Kolkata')->timestamp;
            $movePts = round($close - $open, 2);
            $movePct = $open > 0 ? round(($movePts / $open) * 100, 3) : 0;
            $color = $close >= $open ? 'green' : 'red';

            $candle = [
                'ts_at'     => "{$dt} 09:15:00",
                'time_str'  => $dt,
                'timestamp' => $ts,
                'time'      => $ts,
                'open'      => round($open, 2),
                'high'      => round($high, 2),
                'low'       => round($low, 2),
                'close'     => round($close, 2),
                'volume'    => $vol,
                'move_pts'  => $movePts,
                'move_pct'  => $movePct,
                'color'     => $color,
            ];

            $niftyCandles[] = $candle;
            $lwChartCandles[] = [
                'time'  => $ts,
                'open'  => round($open, 2),
                'high'  => round($high, 2),
                'low'   => round($low, 2),
                'close' => round($close, 2),
            ];
            $timestamps[] = $dt;
        }

        // 3. Query all options data for these days from daily_ohlc_quotes
        $dailyOptions = DB::table('daily_ohlc_quotes')
            ->where('symbol_name', $symbol)
            ->whereIn('quote_date', $tradingDays)
            ->whereIn('option_type', ['CE', 'PE'])
            ->get()
            ->groupBy('quote_date');

        $breadthByTime = [];
        $nowKolkata = Carbon::now('Asia/Kolkata');
        $todayDate = $nowKolkata->toDateString();
        $isMarketHours = ($date === $todayDate && $nowKolkata->format('H:i') >= '09:15' && $nowKolkata->format('H:i') <= '15:30');

        foreach ($niftyCandles as $c) {
            $dt = $c['time_str'];
            $spotClose = $c['close'] ?: 25500;
            $atmStrike = (int)(round($spotClose / 50.0) * 50);
            $basketStrikes = range($atmStrike - ($range * 50), $atmStrike + ($range * 50), 50);

            $dayOptRows = $dailyOptions[$dt] ?? collect([]);
            $byStrike = $dayOptRows->groupBy('strike');

            $bullWeightSum = 0;
            $totalWeight = 0;
            $bullCount = 0;
            $bearCount = 0;
            $flatCount = 0;
            $contribs = [];
            $totalCeOi = 0;
            $totalPeOi = 0;

            foreach ($basketStrikes as $stk) {
                $stkRows = $byStrike[$stk] ?? null;
                $ce = $stkRows ? $stkRows->firstWhere('option_type', 'CE') : null;
                $pe = $stkRows ? $stkRows->firstWhere('option_type', 'PE') : null;

                $ceOpen = $ce ? (float)$ce->open : 0;
                $ceClose = $ce ? (float)$ce->close : 0;
                $peOpen = $pe ? (float)$pe->open : 0;
                $peClose = $pe ? (float)$pe->close : 0;

                $ceOi = $ce ? (int)$ce->open_interest : 0;
                $peOi = $pe ? (int)$pe->open_interest : 0;
                $totalCeOi += $ceOi;
                $totalPeOi += $peOi;

                $dCe = $ceClose - $ceOpen;
                $dPe = $peClose - $peOpen;

                $dist = abs($stk - $atmStrike);
                $w = max(1.0, 10.0 - ($dist / 50.0) * 1.2);

                $ceMovePct = $ceOpen > 0 ? ($dCe / $ceOpen) : 0;
                $peMovePct = $peOpen > 0 ? ($dPe / $peOpen) : 0;

                $bullScore = 0.5 + ($ceMovePct - $peMovePct) * 0.25;
                $bullScore = max(0.05, min(0.95, $bullScore));
                $strikeBullPct = (int) round($bullScore * 100);

                $bullWeightSum += ($bullScore * $w);
                $totalWeight += $w;

                if ($strikeBullPct >= 55) $bullCount++;
                elseif ($strikeBullPct <= 45) $bearCount++;
                else $flatCount++;

                $pts = round(($ceMovePct - $peMovePct) * $w, 2);
                $contribs[] = [
                    'strike'   => (string)$stk,
                    'name'     => '₹' . number_format($stk) . ($stk == $atmStrike ? ' (ATM)' : ''),
                    'weight'   => round($w, 2),
                    'bull_pct' => $strikeBullPct,
                    'bear_pct' => 100 - $strikeBullPct,
                    'ce_ltp'   => $ceClose,
                    'pe_ltp'   => $peClose,
                    'd_ce_ltp' => round($dCe, 2),
                    'd_pe_ltp' => round($dPe, 2),
                    'ce_vega'  => 8.5,
                    'pe_vega'  => 8.5,
                    'd_ce_vega'=> 0,
                    'd_pe_vega'=> 0,
                    'pts'      => $pts,
                    'color'    => $strikeBullPct >= 55 ? 'green' : ($strikeBullPct <= 45 ? 'red' : 'gray'),
                    'signal'   => $strikeBullPct >= 55 ? 'BULLISH' : ($strikeBullPct <= 45 ? 'BEARISH' : 'NEUTRAL')
                ];
            }

            $upWeight = $totalWeight > 0 ? (int) round(($bullWeightSum / $totalWeight) * 100) : 50;
            $downWeight = 100 - $upWeight;
            $flatWeight = max(0, 100 - abs($upWeight - $downWeight) * 2);

            $totBasket = max(1, count($basketStrikes));
            $upCountPct = (int) round(($bullCount / $totBasket) * 100);
            $downCountPct = (int) round(($bearCount / $totBasket) * 100);
            $flatCountPct = max(0, 100 - $upCountPct - $downCountPct);

            $pcr = $totalCeOi > 0 ? round($totalPeOi / $totalCeOi, 2) : ($c['color'] === 'green' ? 1.15 : 0.85);

            if ($upWeight >= 70) {
                $verdict = "🔥 Dominant Bullish Impulse: Strong Call Buying & Put Decay across {$upWeight}% of option surface.";
            } elseif ($downWeight >= 70) {
                $verdict = "🚨 Dominant Bearish Impulse: Strong Put Buying & Call Writing across {$downWeight}% of option surface.";
            } elseif ($upWeight >= 55) {
                $verdict = "Mild Bullish Bias: Buyers have marginal upper hand (+{$upWeight}% vs {$downWeight}%).";
            } elseif ($downWeight >= 55) {
                $verdict = "Mild Bearish Bias: Sellers have marginal upper hand (-{$downWeight}% vs {$upWeight}%).";
            } else {
                $verdict = "Balanced Tug-of-War: Extrinsic decay & delta balanced in equilibrium ({$upWeight}% Buy vs {$downWeight}% Sell).";
            }

            usort($contribs, fn($a, $b) => $b['pts'] <=> $a['pts']);

            $isComplete = ($dt !== $date) || !$isMarketHours;

            $breadthByTime[$dt] = [
                'time_str'       => $dt,
                'is_complete'    => $isComplete,
                'status'         => $isComplete ? 'CONFIRMED' : 'IN_PROGRESS',
                'candle_idx'     => count($breadthByTime),
                'up_count'       => $bullCount,
                'down_count'     => $bearCount,
                'flat_count'     => $flatCount,
                'up_count_pct'   => $upCountPct,
                'down_count_pct' => $downCountPct,
                'flat_count_pct' => $flatCountPct,
                'up_weight'      => $upWeight,
                'down_weight'    => $downWeight,
                'flat_weight'    => $flatWeight,
                'net_points'     => round(array_sum(array_column($contribs, 'pts')), 2),
                'atm_strike'     => $atmStrike,
                'basket_min'     => min($basketStrikes),
                'basket_max'     => max($basketStrikes),
                'basket_count'   => count($basketStrikes),
                'basket_strikes' => $basketStrikes,
                'basket_mode'    => $basketMode,
                'verdict'        => $verdict,
                'contributions'  => $contribs,
                'greeks'         => [
                    'avg_ce_vega' => 8.5,
                    'avg_pe_vega' => 8.5,
                    'net_delta'   => ($c['move_pts'] >= 0 ? '+' : '') . round($c['move_pts'] * 25) . 'k',
                    'pcr'         => $pcr,
                ],
            ];
        }

        $latestCandle = !empty($niftyCandles) ? end($niftyCandles) : null;

        return [
            'success'          => true,
            'basis'            => $basis,
            'date'             => $date,
            'interval'         => 375,
            'interval_label'   => '1D',
            'expiry'           => $selectedExpiry,
            'center_strike'    => !empty($niftyCandles) ? (int)(round(end($niftyCandles)['close'] / 50.0) * 50) : 25500,
            'atm_strike'       => !empty($niftyCandles) ? (int)(round(end($niftyCandles)['close'] / 50.0) * 50) : 25500,
            'basket_mode'      => $basketMode,
            'range'            => $range,
            'strikes'          => range(25000, 26000, 50),
            'is_today'         => ($date === $todayDate),
            'is_live'          => $isMarketHours,
            'server_time'      => $nowKolkata->format('H:i:s'),
            'latest_time'      => $latestCandle ? $latestCandle['time_str'] : $date,
            'latest_price'     => $latestCandle ? $latestCandle['close'] : 0,
            'nifty_candles'    => $niftyCandles,
            'yesterday_date'   => null,
            'yesterday_candles'=> [],
            'lw_chart_candles' => $lwChartCandles,
            'timestamps'       => $timestamps,
            'breadth_by_time'  => $breadthByTime,
            'matrix'           => [],
            'summary'          => [
                'strike_count'      => count($basketStrikes ?? []),
                'candles_count'     => count($niftyCandles),
                'smart_money_traps' => [],
            ],
        ];
    }

    /**
     * Fetch Index Candlestick Data across any timeframe interval.
     */
    protected function fetchIndexCandles(string $date, string $symbol, string $table, string $expiry, int $baseStrike, int $interval = 5, string $basis = 'spot'): array
    {
        if ($basis === 'future') {
            $futCandles = \App\Services\FuturesCandleService::loadCandlesForDate($symbol, $date, $interval);
            if (!empty($futCandles)) {
                return $futCandles;
            }
        }

        $nowKolkata = Carbon::now('Asia/Kolkata');
        $isToday = ($date === $nowKolkata->toDateString());
        $curTimeStr = $nowKolkata->format('H:i');
        $isWeekday = !in_array($nowKolkata->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY]);
        $isMarketHours = ($isToday && $isWeekday && $curTimeStr >= '09:15' && $curTimeStr <= '15:30');
        $isPreMarket = ($isToday && $isWeekday && $curTimeStr >= '09:00' && $curTimeStr < '09:15');

        $isBank = ($symbol === 'BANKNIFTY' || str_contains($symbol, 'BANK') || str_contains($symbol, 'Bank'));
        $targetSymbols = $isBank ? ['Nifty Bank', 'BANKNIFTY'] : ['Nifty 50', 'NIFTY'];

        // 1. Primary: Use corresponding WeightageMonitorController's battle-tested real candle loader for 100% chart synchronization
        try {
            if ($isBank) {
                $nwCtrl = app(\App\Http\Controllers\BankNiftyWeightageMonitorController::class);
            } else {
                $nwCtrl = app(\App\Http\Controllers\NiftyWeightageMonitorController::class);
            }
            $candles = $nwCtrl->loadCandlesForDate($date, $isToday, $isMarketHours, $curTimeStr, $isWeekday, $isPreMarket, $interval);
            if (!empty($candles)) {
                return $candles;
            }
        } catch (\Throwable $e) {
            // fallback to direct table query
        }

        $candles = [];
        $seen = [];

        if (Schema::hasTable('ohlc_5m_quotes')) {
            $dbCandles = DB::table('ohlc_5m_quotes')
                ->where('instrument_type', 'INDEX')
                ->whereIn('trading_symbol', $targetSymbols)
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
                ->where(function($q) use ($symbol, $targetSymbols) {
                    $q->where('instrument_key', 'like', "%{$symbol}%")
                      ->orWhereIn('trading_symbol', $targetSymbols);
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
            $fallbackHint = $this->getOptimalIndexHint($table);
            $spotRows = DB::table(DB::raw("{$table}{$fallbackHint}"))
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
        string $metric,
        string $basketMode = 'dynamic',
        array $allStrikesArr = [],
        int $range = 5,
        $initialSpot = 23000,
        int $interval = 5
    ): array {
        // Query CE & PE options records combined in a single indexed query
        $indexHint = $this->getOptimalIndexHint($table);
        $optRows = DB::table(DB::raw("{$table}{$indexHint}"))
            ->where('captured_at', '>=', $startTs)
            ->where('captured_at', '<=', $endTs)
            ->where('trading_symbol', $symbol)
            ->where('expiry', $expiry)
            ->whereIn('strike_price', $targetStrikes)
            ->whereIn('option_type', ['CE', 'PE'])
            ->orderBy('captured_at')
            ->select('option_type', 'captured_at', 'strike_price', 'ltp', 'vega', 'delta', 'gamma', 'theta', 'iv', 'oi', 'volume', 'underlying_spot_price')
            ->get();

        // Index by strike and formatted timestamp
        $ceGrid = [];
        $peGrid = [];
        $timestampsSet = [];

        $stepMin = max(1, $interval);
        foreach ($optRows as $r) {
            $ts = Carbon::parse($r->captured_at);
            $minsSinceOpen = ($ts->hour * 60 + $ts->minute) - (9 * 60 + 15);
            if ($minsSinceOpen < 0) continue;
            if ($stepMin >= 375) {
                $t = '09:15';
            } else {
                $bucketMins = (int)(floor($minsSinceOpen / $stepMin) * $stepMin) + (9 * 60 + 15);
                $t = sprintf('%02d:%02d', intdiv($bucketMins, 60), $bucketMins % 60);
            }
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

        $niftyCandlesByTime = [];
        foreach ($niftyCandles as $nc) {
            if (!empty($nc['time_str'])) {
                $niftyCandlesByTime[$nc['time_str']] = $nc;
            }
        }

        // Open values for baseline delta calculations
        $openTimes = !empty($timestamps) ? $timestamps[0] : '09:15';
        $prevCandleLtpCe  = [];
        $prevCandleLtpPe  = [];
        $prevCandleVegaCe = [];
        $prevCandleVegaPe = [];
        $prevOiCe         = [];
        $prevOiPe         = [];
        $prevVolCe        = [];
        $prevVolPe        = [];
        $strikeWeights    = [];

        $totalBasketOi = 0;
        foreach ($targetStrikes as $stk) {
            $c = $ceGrid[$stk][$openTimes] ?? null;
            $p = $peGrid[$stk][$openTimes] ?? null;
            $prevCandleLtpCe[$stk]  = $c ? (float)$c->ltp : 0;
            $prevCandleLtpPe[$stk]  = $p ? (float)$p->ltp : 0;
            $prevCandleVegaCe[$stk] = $c ? (float)$c->vega : 0;
            $prevCandleVegaPe[$stk] = $p ? (float)$p->vega : 0;
            $prevOiCe[$stk]         = $c ? (int)$c->oi : 0;
            $prevOiPe[$stk]         = $p ? (int)$p->oi : 0;
            $prevVolCe[$stk]        = $c ? (int)$c->volume : 0;
            $prevVolPe[$stk]        = $p ? (int)$p->volume : 0;
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

            // 1. Resolve candle spot & active ATM strike
            $candleObj = $niftyCandlesByTime[$timeStr] ?? null;
            $candleSpot = $candleObj ? (float)$candleObj['close'] : 0;
            if (!$candleSpot) {
                foreach ($targetStrikes as $stk) {
                    $cRec = $ceGrid[$stk][$timeStr] ?? null;
                    if ($cRec && $cRec->underlying_spot_price > 0) {
                        $candleSpot = (float)$cRec->underlying_spot_price;
                        break;
                    }
                }
            }
            if (!$candleSpot) $candleSpot = (float)$initialSpot;

            $isBank = ($symbol === 'BANKNIFTY' || str_contains($symbol, 'BANK') || str_contains($symbol, 'Bank'));
            $strikeStep = $isBank ? 100.0 : 50.0;
            $candleAtm = (int)(round($candleSpot / $strikeStep) * $strikeStep);

            if ($basketMode === 'dynamic' && !empty($allStrikesArr)) {
                $cIdxAtm = array_search((float)$candleAtm, array_map('floatval', $allStrikesArr));
                if ($cIdxAtm === false) {
                    $minD = 999999;
                    $cIdxAtm = 0;
                    foreach ($allStrikesArr as $i => $s) {
                        if (abs((float)$s - $candleAtm) < $minD) {
                            $minD = abs((float)$s - $candleAtm);
                            $cIdxAtm = $i;
                        }
                    }
                }
                $fromIdx = max(0, $cIdxAtm - $range);
                $toIdx   = min(count($allStrikesArr) - 1, $cIdxAtm + $range);
                $activeBasket = array_slice($allStrikesArr, $fromIdx, ($toIdx - $fromIdx + 1));
            } else {
                $activeBasket = $targetStrikes->values()->all();
            }

            $basketCount = max(1, count($activeBasket));
            $wtPerStrike = 100.0 / $basketCount;

            foreach ($activeBasket as $stk) {
                $c = $ceGrid[$stk][$timeStr] ?? null;
                $p = $peGrid[$stk][$timeStr] ?? null;

                // For multi-timeframe intervals (15m, 30m, 60m, 1D), match nearest snapshot <= timeStr
                if (!$c && !empty($ceGrid[$stk])) {
                    foreach ($ceGrid[$stk] as $t => $row) {
                        if ($t <= $timeStr) { $c = $row; } else { break; }
                    }
                    if (!$c) { $c = reset($ceGrid[$stk]); }
                }
                if (!$p && !empty($peGrid[$stk])) {
                    foreach ($peGrid[$stk] as $t => $row) {
                        if ($t <= $timeStr) { $p = $row; } else { break; }
                    }
                    if (!$p) { $p = reset($peGrid[$stk]); }
                }

                $wt = $wtPerStrike;

                $cPrice = $c ? (float)$c->ltp : ($prevCandleLtpCe[$stk] ?? 0);
                $pPrice = $p ? (float)$p->ltp : ($prevCandleLtpPe[$stk] ?? 0);
                $cVega  = $c ? (float)$c->vega : ($prevCandleVegaCe[$stk] ?? 0);
                $pVega  = $p ? (float)$p->vega : ($prevCandleVegaPe[$stk] ?? 0);
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

                // Incremental 5m Vega change (Normalized)
                $dCeVega  = round($cVega - ($prevCandleVegaCe[$stk] ?? $cVega), 3);
                $dPeVega  = round($pVega - ($prevCandleVegaPe[$stk] ?? $pVega), 3);

                // --- 1. FACTOR: OI Buildup Flow (-1.0 to +1.0) ---
                $oiCeScore = 0.0;
                if ($dOiCe > 0) {
                    $oiCeScore = ($dCePrice >= 0) ? 0.9 : -0.9; // Long build vs Call writing
                } elseif ($dOiCe < 0) {
                    $oiCeScore = ($dCePrice >= 0) ? 0.7 : -0.7; // Short covering vs Long unwinding
                }

                $oiPeScore = 0.0;
                if ($dOiPe > 0) {
                    $oiPeScore = ($dPePrice >= 0) ? -0.9 : 0.9; // Put buying vs Put writing support
                } elseif ($dOiPe < 0) {
                    $oiPeScore = ($dPePrice >= 0) ? -0.7 : 0.7; // Put short covering vs Put unwinding
                }
                $factorOi = ($oiCeScore + $oiPeScore) / 2.0;

                // --- 2. FACTOR: Vega & Volatility Skew (-1.0 to +1.0) ---
                // Incremental 5m IV/Vega expansion relative to counter-leg
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
                    'name'       => "₹" . number_format($stk) . ($stk == $candleAtm ? ' (ATM)' : ''),
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

                if (isset($matrix[$stk])) {
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
                }

                $prevCandleLtpCe[$stk]  = $cPrice;
                $prevCandleLtpPe[$stk]  = $pPrice;
                $prevCandleVegaCe[$stk] = $cVega;
                $prevCandleVegaPe[$stk] = $pVega;
                $prevOiCe[$stk]         = $cOi;
                $prevOiPe[$stk]         = $pOi;
                $prevVolCe[$stk]        = $cVol;
                $prevVolPe[$stk]        = $pVol;
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
            $totBasket = max(1, count($activeBasket));
            $upCountPct = (int) round(($candleBullCount / $totBasket) * 100);
            $downCountPct = (int) round(($candleBearCount / $totBasket) * 100);
            $flatCountPct = max(0, 100 - $upCountPct - $downCountPct);

            $candleDurationSec = 300;
            if (count($niftyCandles) >= 2 && !empty($niftyCandles[1]['timestamp']) && !empty($niftyCandles[0]['timestamp'])) {
                $candleDurationSec = max(60, (int)$niftyCandles[1]['timestamp'] - (int)$niftyCandles[0]['timestamp']);
            }
            $candleStartTs = Carbon::parse("{$date} {$timeStr}:00", 'Asia/Kolkata')->timestamp;
            $candleEndTs = $candleStartTs + $candleDurationSec;
            $nowTs = Carbon::now('Asia/Kolkata')->timestamp;
            $isTodayDate = ($date === Carbon::now('Asia/Kolkata')->toDateString());
            $isComplete = (!$isTodayDate) || ($nowTs >= $candleEndTs);

            $breadthByTime[$timeStr] = [
                'time_str'       => $timeStr,
                'is_complete'    => $isComplete,
                'status'         => $isComplete ? 'CONFIRMED' : 'IN_PROGRESS',
                'candle_idx'     => $cIdx,
                'up_count'       => $candleBullCount,
                'down_count'     => $candleBearCount,
                'flat_count'     => $candleFlatCount,
                'up_count_pct'   => $upCountPct,
                'down_count_pct' => $downCountPct,
                'flat_count_pct' => $flatCountPct,
                'up_weight'      => $upPct,
                'down_weight'    => $downPct,
                'flat_weight'    => $flatPct,
                'net_points'     => $netPts,
                'atm_strike'     => $candleAtm,
                'basket_min'     => !empty($activeBasket) ? (float)min($activeBasket) : (float)$candleAtm,
                'basket_max'     => !empty($activeBasket) ? (float)max($activeBasket) : (float)$candleAtm,
                'basket_count'   => count($activeBasket),
                'basket_strikes' => !empty($activeBasket) ? array_values(array_map('floatval', $activeBasket)) : [$candleAtm],
                'basket_mode'    => $basketMode,
                'verdict'        => $verdict,
                'contributions'  => $strikeContributions,
                'greeks'         => [
                    'avg_ce_vega' => round($basketCeVegaSum / max(1, count($activeBasket)), 3),
                    'avg_pe_vega' => round($basketPeVegaSum / max(1, count($activeBasket)), 3),
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

    /**
     * Get the optimal composite index hint for option chains queries.
     * Prevents full table scan timeout issues when querying options breadth.
     */
    protected function getOptimalIndexHint(string $table): string
    {
        if ($table === 'option_chains') {
            return ' USE INDEX (idx_oc_sym_exp_cap)';
        }
        if ($table === 'option_chains_history') {
            return ' USE INDEX (idx_och_sym_exp_cap)';
        }
        return '';
    }
}
