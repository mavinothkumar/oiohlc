<?php

namespace App\Http\Controllers;

use App\Services\BankNiftyConstituentManager;
use App\Services\TimeframeResampler;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class BankNiftyWeightageMonitorController extends Controller
{
    /**
     * API Data Endpoint: Returns synchronized candles for Bank Nifty and all 12 constituent banking stocks.
     * Supports multi-timeframe: 1m, 3m, 5m, 15m, 30m, 60m, 1D.
     */
    public function getData(Request $request): JsonResponse
    {
        $nowKolkata = Carbon::now('Asia/Kolkata');
        $todayStr = $nowKolkata->toDateString();
        $date = $request->input('date', $todayStr);
        $isToday = ($date === $todayStr);

        // Check if market is active (between 09:15 and 15:30 IST on a weekday)
        $curTimeStr = $nowKolkata->format('H:i');
        $isWeekday = !in_array($nowKolkata->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY]);
        $isMarketHours = ($isToday && $isWeekday && $curTimeStr >= '09:15' && $curTimeStr <= '15:30');
        $isPreMarket = ($isToday && $isWeekday && $curTimeStr >= '09:00' && $curTimeStr < '09:15');

        // Multi-timeframe interval support: 1, 3, 5, 15, 30, 60, 375 (1D)
        $interval = TimeframeResampler::normalizeInterval($request->input('interval', $request->input('timeframe', 5)));
        $basis = strtolower($request->input('basis', 'spot')) === 'future' ? 'future' : 'spot';

        $cacheTtl = $isToday ? ($isMarketHours ? 15 : 30) : 86400;
        $cacheKey = "banknifty_weightage_data_{$date}_{$interval}_{$basis}";

        if ($request->boolean('refresh')) {
            Cache::store('file')->forget($cacheKey);
        }

        $data = Cache::store('file')->remember($cacheKey, $cacheTtl, function () use ($date, $isToday, $isMarketHours, $isPreMarket, $nowKolkata, $curTimeStr, $isWeekday, $interval, $basis) {
            // Dynamic Bank Nifty constituent configuration for this specific date
            $periodInfo = BankNiftyConstituentManager::getConstituentsForDate($date);
            $constituents = $periodInfo['constituents'];

            // Fetch previous session candles for seamless history pan & SuperTrend burn-in
            $prevTradingDate = $this->getPreviousTradingDate($date);

            // 1. Fetch real Bank Nifty candles (Spot or Futures)
            if ($basis === 'future') {
                $bankCandles = \App\Services\FuturesCandleService::loadCandlesForDate('BANKNIFTY', $date, $interval);
                $yesterdayCandles = \App\Services\FuturesCandleService::loadCandlesForDate('BANKNIFTY', $prevTradingDate, $interval);
                if (empty($bankCandles)) {
                    $bankCandles = $this->loadCandlesForDate($date, $isToday, $isMarketHours, $curTimeStr, $isWeekday, $isPreMarket, $interval);
                    $yesterdayCandles = $this->loadCandlesForDate($prevTradingDate, false, false, '15:30', true, false, $interval);
                }
            } else {
                $bankCandles = $this->loadCandlesForDate($date, $isToday, $isMarketHours, $curTimeStr, $isWeekday, $isPreMarket, $interval);
                $yesterdayCandles = $this->loadCandlesForDate($prevTradingDate, false, false, '15:30', true, false, $interval);
            }

            // 2. Synthesize all 12 constituent banking stocks' candles perfectly coordinated with Bank Nifty
            $stockData = $this->generateConstituentsMatrix($bankCandles, $date, $constituents, $interval);

            // 3. Compute Candle-level Breadth Aggregations
            $breadthByTime = $stockData['breadth_by_time'];
            $matrix        = $stockData['matrix'];
            $timestamps    = $stockData['timestamps'];
            $summary       = $stockData['summary'];

            // Format Bank Nifty candles for Lightweight Charts
            $lwChartCandles = array_map(function($c) {
                return [
                    'time'  => $c['timestamp'],
                    'open'  => $c['open'],
                    'high'  => $c['high'],
                    'low'   => $c['low'],
                    'close' => $c['close'],
                ];
            }, $bankCandles);

            $latestCandle = !empty($bankCandles) ? end($bankCandles) : null;

            return [
                'success'            => true,
                'basis'              => $basis,
                'symbol'             => 'BANKNIFTY',
                'index_name'         => 'Nifty Bank',
                'date'               => $date,
                'interval'           => $interval,
                'interval_label'     => ($interval === 375 ? '1D' : ($interval === 60 ? '60m' : "{$interval}m")),
                'is_today'           => $isToday,
                'is_live'            => $isMarketHours,
                'is_pre_market'      => $isPreMarket,
                'market_status'      => $isMarketHours ? 'LIVE_TRADING' : ($isPreMarket ? 'PRE_MARKET' : ($curTimeStr < '09:00' ? 'BEFORE_MARKET' : 'CLOSED')),
                'server_time'        => $nowKolkata->format('H:i:s'),
                'latest_time'        => $latestCandle ? $latestCandle['time_str'] : '09:15',
                'latest_price'       => $latestCandle ? $latestCandle['close'] : 0,
                'nifty_candles'      => $bankCandles, // Alias kept for seamless frontend cockpit compatibility
                'bank_candles'       => $bankCandles,
                'yesterday_date'     => $prevTradingDate,
                'yesterday_candles'  => $yesterdayCandles,
                'lw_chart_candles'   => $lwChartCandles,
                'timestamps'         => $timestamps,
                'rebalancing_period' => $periodInfo['period_name'],
                'constituents'       => $constituents,
                'matrix'             => $matrix,
                'breadth_by_time'    => $breadthByTime,
                'summary'            => $summary,
            ];
        });

        return response()->json($data);
    }

    /**
     * Generate candle matrix for all 12 banking constituents across any timeframe interval.
     */
    protected function generateConstituentsMatrix(array $bankCandles, string $date, array $constituents, int $interval = 5): array
    {
        $timestamps = [];
        $breadthByTime = [];
        $matrix = [];

        $stockBuckets = [];

        if ($interval < 5) {
            // For 1m and 3m intervals, query 1-minute real equity quotes
            $realEqQuotes = DB::table('ohlc_quotes')
                ->where('instrument_type', 'EQ')
                ->where('ts_at', '>=', "{$date} 00:00:00")
                ->where('ts_at', '<=', "{$date} 23:59:59")
                ->orderBy('ts_at')
                ->get()
                ->groupBy('trading_symbol');

            if ($realEqQuotes->isNotEmpty()) {
                if ($interval === 1) {
                    foreach ($realEqQuotes as $sym => $quotes) {
                        foreach ($quotes as $q) {
                            $timeStr = Carbon::parse($q->ts_at, 'Asia/Kolkata')->format('H:i');
                            $stockBuckets[$sym][$timeStr] = [
                                'open'   => (float)$q->open,
                                'high'   => (float)$q->high,
                                'low'    => (float)$q->low,
                                'close'  => (float)$q->close,
                                'volume' => (int)$q->volume,
                            ];
                        }
                    }
                } else {
                    $stockBuckets = TimeframeResampler::resampleStockQuotes($realEqQuotes->toArray(), $interval);
                }
            }
        } else {
            // 5m or higher intervals: query ohlc_5m_quotes
            $db5mQuotes = DB::table('ohlc_5m_quotes')
                ->where('instrument_type', 'EQ')
                ->where('ts_at', '>=', "{$date} 00:00:00")
                ->where('ts_at', '<=', "{$date} 23:59:59")
                ->orderBy('ts_at')
                ->get()
                ->groupBy('trading_symbol');

            if ($db5mQuotes->isNotEmpty()) {
                if ($interval === 5) {
                    foreach ($db5mQuotes as $sym => $quotes) {
                        foreach ($quotes as $q) {
                            $timeStr = Carbon::parse($q->ts_at, 'Asia/Kolkata')->format('H:i');
                            $stockBuckets[$sym][$timeStr] = [
                                'open'   => (float)$q->open,
                                'high'   => (float)$q->high,
                                'low'    => (float)$q->low,
                                'close'  => (float)$q->close,
                                'volume' => (int)$q->volume,
                            ];
                        }
                    }
                } else {
                    $stockBuckets = TimeframeResampler::resampleStockQuotes($db5mQuotes->toArray(), $interval);
                }
            } else {
                // Fallback: check ohlc_quotes
                $realEqQuotes = DB::table('ohlc_quotes')
                    ->where('instrument_type', 'EQ')
                    ->where('ts_at', '>=', "{$date} 00:00:00")
                    ->where('ts_at', '<=', "{$date} 23:59:59")
                    ->orderBy('ts_at')
                    ->get()
                    ->groupBy('trading_symbol');

                if ($realEqQuotes->isNotEmpty()) {
                    $stockBuckets = TimeframeResampler::resampleStockQuotes($realEqQuotes->toArray(), $interval);
                }
            }
        }

        $hasRealEquity = !empty($stockBuckets);

        // Precompute initial prices
        $currentPrices = [];
        $stockOpenOfDay = [];
        foreach ($constituents as $stock) {
            $sym = $stock['symbol'];
            $basePrice = 1000.0;
            if ($hasRealEquity && isset($stockBuckets[$sym])) {
                $firstBucket = reset($stockBuckets[$sym]);
                $basePrice = $firstBucket['open'];
            }
            $currentPrices[$sym] = $basePrice;
            $stockOpenOfDay[$sym] = $basePrice;
            $matrix[$sym] = [];
        }

        $totalStocks = count($constituents);
        $totalWeight = array_sum(array_column($constituents, 'weight')) ?: 100.0;

        foreach ($bankCandles as $candle) {
            $timeStr = $candle['time_str'];
            $timestamps[] = $timeStr;
            $indexMovePct = $candle['move_pct'];
            $indexMovePts = $candle['move_pts'];

            $greenCount = 0;
            $redCount = 0;
            $greenWeight = 0.0;
            $redWeight = 0.0;
            $greenPts = 0.0;
            $redPts = 0.0;

            $stockContributions = [];

            foreach ($constituents as $stock) {
                $sym = $stock['symbol'];
                $wt = $stock['weight'];
                $beta = $stock['beta'];

                if ($hasRealEquity && isset($stockBuckets[$sym][$timeStr])) {
                    $bar = $stockBuckets[$sym][$timeStr];
                    $stockOpen  = $bar['open'];
                    $stockClose = $bar['close'];
                    $stockHigh  = $bar['high'];
                    $stockLow   = $bar['low'];
                    $stockVol   = $bar['volume'];

                    $stockChangePct = $stockOpen > 0 ? round((($stockClose - $stockOpen) / $stockOpen) * 100, 3) : 0.0;
                    $dayChangePct   = $stockOpenOfDay[$sym] > 0 ? round((($stockClose - $stockOpenOfDay[$sym]) / $stockOpenOfDay[$sym]) * 100, 3) : 0.0;
                    $currentPrices[$sym] = $stockClose;
                } else {
                    // Fallback beta model
                    $stockOpen = $currentPrices[$sym];
                    $stockChangePct = round($indexMovePct * $beta, 3);
                    $stockClose = round($stockOpen * (1 + ($stockChangePct / 100)), 2);
                    $stockHigh  = max($stockOpen, $stockClose);
                    $stockLow   = min($stockOpen, $stockClose);
                    $stockVol   = 50000;
                    $dayChangePct   = $stockOpenOfDay[$sym] > 0 ? round((($stockClose - $stockOpenOfDay[$sym]) / $stockOpenOfDay[$sym]) * 100, 3) : 0.0;
                    $currentPrices[$sym] = $stockClose;
                }

                $stockPts = round(($stockChangePct * $wt) / 100, 3);
                $isGreen = $stockChangePct >= 0;

                if ($isGreen) {
                    $greenCount++;
                    $greenWeight += $wt;
                    $greenPts += $stockPts;
                } else {
                    $redCount++;
                    $redWeight += $wt;
                    $redPts += $stockPts;
                }

                $matrix[$sym][$timeStr] = [
                    'open'         => $stockOpen,
                    'high'         => $stockHigh,
                    'low'          => $stockLow,
                    'close'        => $stockClose,
                    'volume'       => $stockVol,
                    'change_pct'   => $stockChangePct,
                    'day_pct'      => $dayChangePct,
                    'pts_impact'   => $stockPts,
                    'is_green'     => $isGreen,
                ];

                $stockContributions[] = [
                    'symbol'     => $sym,
                    'name'       => $stock['name'],
                    'weight'     => $wt,
                    'change_pct' => $stockChangePct,
                    'pts_impact' => $stockPts,
                    'pts'        => $stockPts,
                    'is_green'   => $isGreen,
                    'color'      => $isGreen ? 'green' : 'red',
                ];
            }

            usort($stockContributions, fn($a, $b) => $b['pts_impact'] <=> $a['pts_impact']);
            $topLeaders = array_slice($stockContributions, 0, 3);
            $topDraggers = array_reverse(array_slice($stockContributions, -3));

            $greenCountPct  = $totalStocks > 0 ? round(($greenCount / $totalStocks) * 100, 1) : 0;
            $redCountPct    = $totalStocks > 0 ? round(($redCount / $totalStocks) * 100, 1) : 0;
            $greenWeightPct = round($greenWeight, 1);
            $redWeightPct   = round($redWeight, 1);

            $verdict = $this->evaluateVerdict(
                $greenCountPct,
                $redCountPct,
                $greenWeightPct,
                $redWeightPct,
                $indexMovePct,
                $topLeaders[0]['symbol'] ?? 'HDFCBANK',
                $topDraggers[0]['symbol'] ?? 'ICICIBANK'
            );

            $breadthByTime[$timeStr] = [
                'time'             => $timeStr,
                'time_str'         => $timeStr,
                'nifty_move_pts'   => $indexMovePts,
                'nifty_move_pct'   => $indexMovePct,
                'index_move_pts'   => $indexMovePts,
                'index_move_pct'   => $indexMovePct,
                'green_count'      => $greenCount,
                'red_count'        => $redCount,
                'green_count_pct'  => $greenCountPct,
                'red_count_pct'    => $redCountPct,
                'green_weight'     => $greenWeightPct,
                'red_weight'       => $redWeightPct,
                'net_green_pts'    => round($greenPts, 2),
                'net_red_pts'      => round($redPts, 2),
                'green_pts'        => round($greenPts, 2),
                'red_pts'          => round($redPts, 2),
                'verdict'          => $verdict,
                'insight'          => $verdict,
                'top_contributors' => $topLeaders,
                'top_leaders'      => $topLeaders,
                'top_draggers'     => $topDraggers,
            ];
        }

        $latestBreadth = !empty($breadthByTime) ? end($breadthByTime) : null;

        return [
            'timestamps'      => $timestamps,
            'breadth_by_time' => $breadthByTime,
            'matrix'          => $matrix,
            'summary'         => $latestBreadth,
        ];
    }

    /**
     * Institutional Conviction Verdict Engine
     */
    protected function evaluateVerdict(
        float $greenCountPct,
        float $redCountPct,
        float $greenWeightPct,
        float $redWeightPct,
        float $indexMovePct,
        string $leaderName,
        string $draggerName
    ): array {
        if ($greenCountPct >= 70 && $redWeightPct >= 55) {
            return [
                'type'    => 'DIVERGENCE_BEARISH',
                'title'   => '⚠️ Bearish Divergence (Heavyweight Selling)',
                'message' => "{$greenCountPct}% of banks are UP, but Heavyweights hold {$redWeightPct}% weight in RED. Bank Nifty weighed down by {$draggerName}.",
                'badge'   => 'BEARISH DIVERGENCE',
                'color'   => 'rose',
            ];
        }

        if ($redCountPct >= 70 && $greenWeightPct >= 55) {
            return [
                'type'    => 'DIVERGENCE_BULLISH',
                'title'   => '🛡️ Bullish Divergence (Heavyweight Support)',
                'message' => "{$redCountPct}% banks are DOWN, yet Heavyweights holding {$greenWeightPct}% weight are GREEN led by {$leaderName}. Index protected.",
                'badge'   => 'BULLISH DIVERGENCE',
                'color'   => 'emerald',
            ];
        }

        if ($greenCountPct >= 65 && $greenWeightPct >= 65) {
            return [
                'type'    => 'BULLISH_CONVICTION',
                'title'   => '🚀 Strong Banking Rally',
                'message' => "High conviction rally: {$greenCountPct}% banks UP backed by {$greenWeightPct}% weight. {$leaderName} leading gains.",
                'badge'   => 'STRONG BULLISH',
                'color'   => 'emerald',
            ];
        }

        if ($redCountPct >= 65 && $redWeightPct >= 65) {
            return [
                'type'    => 'BEARISH_CONVICTION',
                'title'   => '🔻 Heavy Banking Selloff',
                'message' => "Heavy selling pressure: {$redCountPct}% banks DOWN backed by {$redWeightPct}% weight drag. {$draggerName} leading drop.",
                'badge'   => 'STRONG BEARISH',
                'color'   => 'rose',
            ];
        }

        return [
            'type'    => 'MIXED',
            'title'   => '⚖️ Mixed Banking Flow',
            'message' => "Mixed consolidation: {$greenCountPct}% UP vs {$redCountPct}% DOWN. Market seeking directional trigger.",
            'badge'   => 'NEUTRAL / CHOPPY',
            'color'   => 'slate',
        ];
    }

    /**
     * Load Bank Nifty index candles across any interval (1m, 3m, 5m, 15m, 30m, 60m, 1D)
     */
    public function loadCandlesForDate(string $date, bool $isToday = false, bool $isMarketHours = false, string $curTimeStr = '15:30', bool $isWeekday = true, bool $isPreMarket = false, int $interval = 5): array
    {
        // For 1m and 3m intervals, check 1-minute atomic quotes first
        if ($interval < 5) {
            $oneMinQuotes = DB::table('ohlc_quotes')
                ->where(function($q) {
                    $q->where('instrument_key', 'like', '%Nifty Bank%')
                      ->orWhere('trading_symbol', 'Nifty Bank')
                      ->orWhere('trading_symbol', 'BANKNIFTY');
                })
                ->where('instrument_type', 'INDEX')
                ->where('ts_at', '>=', "{$date} 00:00:00")
                ->where('ts_at', '<=', "{$date} 23:59:59")
                ->orderBy('ts_at')
                ->get();

            if ($oneMinQuotes->isEmpty() && !$isToday) {
                $this->syncHistoricalOneMinIndexQuotes($date, 'NSE_INDEX|Nifty Bank', 'Nifty Bank');
                $oneMinQuotes = DB::table('ohlc_quotes')
                    ->where(function($q) {
                        $q->where('instrument_key', 'like', '%Nifty Bank%')
                          ->orWhere('trading_symbol', 'Nifty Bank')
                          ->orWhere('trading_symbol', 'BANKNIFTY');
                    })
                    ->where('instrument_type', 'INDEX')
                    ->where('ts_at', '>=', "{$date} 00:00:00")
                    ->where('ts_at', '<=', "{$date} 23:59:59")
                    ->orderBy('ts_at')
                    ->get();
            }

            if ($oneMinQuotes->isNotEmpty()) {
                $raw1m = [];
                $seen1m = [];
                foreach ($oneMinQuotes as $row) {
                    $ts = Carbon::parse($row->ts_at, 'Asia/Kolkata');
                    $timeStr = $ts->format('H:i');
                    if ($timeStr < '09:15' || $timeStr > '15:29' || isset($seen1m[$timeStr])) continue;
                    if ($isToday && $isWeekday && $curTimeStr <= '15:30' && $timeStr > $curTimeStr) continue;
                    $seen1m[$timeStr] = true;

                    $open  = round((float)$row->open, 2);
                    $high  = round((float)$row->high, 2);
                    $low   = round((float)$row->low, 2);
                    $close = round((float)$row->close, 2);
                    $vol   = (int)$row->volume;

                    $raw1m[] = [
                        'ts_at'     => $row->ts_at,
                        'time_str'  => $timeStr,
                        'timestamp' => Carbon::parse($row->ts_at, 'UTC')->timestamp,
                        'open'      => $open,
                        'high'      => $high,
                        'low'       => $low,
                        'close'     => $close,
                        'volume'    => $vol,
                        'move_pts'  => round($close - $open, 2),
                        'move_pct'  => $open > 0 ? round((($close - $open) / $open) * 100, 3) : 0,
                        'color'     => $close >= $open ? 'green' : 'red',
                    ];
                }

                if ($interval === 1 && !empty($raw1m)) {
                    return $raw1m;
                }
                if ($interval === 3 && !empty($raw1m)) {
                    return TimeframeResampler::resampleCandles($raw1m, 3, $date);
                }
            }
        }

        // 1. Fetch real Bank Nifty 5m candles from database
        $dbCandles = DB::table('ohlc_5m_quotes')
            ->where(function($q) {
                $q->where('instrument_key', 'like', '%Nifty Bank%')
                  ->orWhere('trading_symbol', 'Nifty Bank')
                  ->orWhere('trading_symbol', 'BANKNIFTY');
            })
            ->where('instrument_type', 'INDEX')
            ->where('ts_at', '>=', "{$date} 00:00:00")
            ->where('ts_at', '<=', "{$date} 23:59:59")
            ->orderBy('ts_at')
            ->get();

        // If past date has no 5m candles in DB, attempt on-demand sync from Upstox
        if ($dbCandles->isEmpty() && !$isToday) {
            try {
                Artisan::call('ohlc:sync-banknifty', ['--date' => $date]);
                $dbCandles = DB::table('ohlc_5m_quotes')
                    ->where(function($q) {
                        $q->where('instrument_key', 'like', '%Nifty Bank%')
                          ->orWhere('trading_symbol', 'Nifty Bank')
                          ->orWhere('trading_symbol', 'BANKNIFTY');
                    })
                    ->where('instrument_type', 'INDEX')
                    ->where('ts_at', '>=', "{$date} 00:00:00")
                    ->where('ts_at', '<=', "{$date} 23:59:59")
                    ->orderBy('ts_at')
                    ->get();
            } catch (\Throwable $e) {
                // Ignore and proceed to fallback
            }
        }

        $bankCandles = [];
        $seenTimes = [];

        if ($dbCandles->count() >= 1) {
            foreach ($dbCandles as $c) {
                $ts = Carbon::parse($c->ts_at, 'Asia/Kolkata');
                $timeStr = $ts->format('H:i');
                if ($timeStr < '09:15' || $timeStr > '15:25' || isset($seenTimes[$timeStr])) {
                    continue;
                }

                if ($isToday && $isWeekday && $curTimeStr <= '15:30' && $timeStr > $curTimeStr) {
                    continue;
                }

                $seenTimes[$timeStr] = true;

                $open  = round((float)$c->open, 2);
                $high  = round((float)$c->high, 2);
                $low   = round((float)$c->low, 2);
                $close = round((float)$c->close, 2);
                $vol   = (int)$c->volume;

                $bankCandles[] = [
                    'ts_at'     => $c->ts_at,
                    'time_str'  => $timeStr,
                    'timestamp' => Carbon::parse($c->ts_at, 'UTC')->timestamp,
                    'open'      => $open,
                    'high'      => $high,
                    'low'       => $low,
                    'close'     => $close,
                    'volume'    => $vol,
                    'move_pts'  => round($close - $open, 2),
                    'move_pct'  => $open > 0 ? round((($close - $open) / $open) * 100, 3) : 0,
                    'color'     => $close >= $open ? 'green' : 'red',
                ];
            }
        }

        // If ohlc_5m_quotes had no rows, check 1-min ohlc_quotes
        if (empty($bankCandles) && Schema::hasTable('ohlc_quotes')) {
            $oneMinQuotes = DB::table('ohlc_quotes')
                ->where(function($q) {
                    $q->where('instrument_key', 'like', '%Nifty Bank%')
                      ->orWhere('trading_symbol', 'Nifty Bank')
                      ->orWhere('trading_symbol', 'BANKNIFTY');
                })
                ->where('instrument_type', 'INDEX')
                ->where('ts_at', '>=', "{$date} 00:00:00")
                ->where('ts_at', '<=', "{$date} 23:59:59")
                ->orderBy('ts_at')
                ->get();

            if ($oneMinQuotes->isEmpty() && !$isToday) {
                $this->syncHistoricalOneMinIndexQuotes($date, 'NSE_INDEX|Nifty Bank', 'Nifty Bank');
                $oneMinQuotes = DB::table('ohlc_quotes')
                    ->where(function($q) {
                        $q->where('instrument_key', 'like', '%Nifty Bank%')
                          ->orWhere('trading_symbol', 'Nifty Bank')
                          ->orWhere('trading_symbol', 'BANKNIFTY');
                    })
                    ->where('instrument_type', 'INDEX')
                    ->where('ts_at', '>=', "{$date} 00:00:00")
                    ->where('ts_at', '<=', "{$date} 23:59:59")
                    ->orderBy('ts_at')
                    ->get();
            }

            if ($oneMinQuotes->isNotEmpty()) {
                $fiveMinGroups = [];
                foreach ($oneMinQuotes as $row) {
                    $ts = Carbon::parse($row->ts_at, 'Asia/Kolkata');
                    $timeStr = $ts->format('H:i');
                    if ($timeStr < '09:15' || $timeStr > '15:25') continue;
                    if ($isToday && $isWeekday && $curTimeStr <= '15:30' && $timeStr > $curTimeStr) continue;

                    $minute = (int)$ts->format('i');
                    $bucketMinute = $minute - ($minute % 5);
                    $bucketTimeStr = $ts->format('H') . ':' . str_pad($bucketMinute, 2, '0', STR_PAD_LEFT);
                    $fiveMinGroups[$bucketTimeStr][] = $row;
                }

                foreach ($fiveMinGroups as $bucketTimeStr => $rows) {
                    $firstRow = reset($rows);
                    $lastRow = end($rows);
                    $open  = round((float)$firstRow->open, 2);
                    $close = round((float)$lastRow->close, 2);
                    $high  = round((float)collect($rows)->max('high'), 2);
                    $low   = round((float)collect($rows)->min('low'), 2);
                    $vol   = (int)collect($rows)->sum('volume');
                    $tsAt  = $firstRow->ts_at;

                    $bankCandles[] = [
                        'ts_at'     => $tsAt,
                        'time_str'  => $bucketTimeStr,
                        'timestamp' => Carbon::parse($tsAt, 'UTC')->timestamp,
                        'open'      => $open,
                        'high'      => $high,
                        'low'       => $low,
                        'close'     => $close,
                        'volume'    => $vol,
                        'move_pts'  => round($close - $open, 2),
                        'move_pct'  => $open > 0 ? round((($close - $open) / $open) * 100, 3) : 0,
                        'color'     => $close >= $open ? 'green' : 'red',
                    ];
                }
            }
        }

        if (empty($bankCandles)) {
            return [];
        }

        // Resample candles if higher timeframe requested (15m, 30m, 60m, 1D)
        if ($interval > 5) {
            return TimeframeResampler::resampleCandles($bankCandles, $interval, $date);
        }

        return $bankCandles;
    }

    /**
     * Resolve the previous valid NSE trading date.
     */
    protected function getPreviousTradingDate(string $date): string
    {
        if (Schema::hasTable('nse_working_days')) {
            $prev = DB::table('nse_working_days')
                ->where('working_date', '<', $date)
                ->orderByDesc('working_date')
                ->value('working_date');

            if ($prev) {
                return $prev;
            }
        }

        $prev = DB::table('ohlc_5m_quotes')
            ->where(function($q) {
                $q->where('instrument_key', 'like', '%Nifty Bank%')
                  ->orWhere('trading_symbol', 'Nifty Bank')
                  ->orWhere('trading_symbol', 'BANKNIFTY');
            })
            ->where('ts_at', '<', "{$date} 00:00:00")
            ->orderByDesc('ts_at')
            ->value('ts_at');

        if ($prev) {
            return substr($prev, 0, 10);
        }

        return Carbon::parse($date)->subDay()->toDateString();
    }

    /**
     * On-demand sync of 1-minute historical index candles from Upstox for past dates
     */
    protected function syncHistoricalOneMinIndexQuotes(string $date, string $instrumentKey, string $tradingSymbol): void
    {
        try {
            $token = config('services.upstox.analytics_token') ?: config('services.upstox.access_token');
            if (!$token) return;

            $encodedKey = rawurlencode($instrumentKey);
            $resp = \Illuminate\Support\Facades\Http::withHeaders([
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ])->timeout(10)->get("https://api.upstox.com/v2/historical-candle/{$encodedKey}/1minute/{$date}/{$date}");

            if ($resp->ok()) {
                $candles = $resp->json('data.candles') ?? [];
                if (!empty($candles)) {
                    $rows = [];
                    $now = now();
                    foreach ($candles as $c) {
                        $tsAt = Carbon::parse($c[0])->setTimezone('Asia/Kolkata')->second(0);
                        $rows[] = [
                            'instrument_key'  => $instrumentKey,
                            'instrument_type' => 'INDEX',
                            'trading_symbol'  => $tradingSymbol,
                            'expiry_date'     => null,
                            'strike_price'    => 0,
                            'open'            => (float)$c[1],
                            'high'            => (float)$c[2],
                            'low'             => (float)$c[3],
                            'close'           => (float)$c[4],
                            'volume'          => (int)($c[5] ?? 0),
                            'ts'              => $tsAt->timestamp,
                            'ts_at'           => $tsAt->toDateTimeString(),
                            'last_price'      => (float)$c[4],
                            'created_at'      => $now,
                            'updated_at'      => $now,
                        ];
                    }
                    DB::table('ohlc_quotes')->upsert(
                        $rows,
                        ['instrument_key', 'ts'],
                        ['open', 'high', 'low', 'close', 'volume', 'last_price', 'updated_at']
                    );
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Could not sync 1m candles for {$instrumentKey} on {$date}: " . $e->getMessage());
        }
    }
}
