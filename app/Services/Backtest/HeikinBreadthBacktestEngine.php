<?php

namespace App\Services\Backtest;

use App\Services\NiftyConstituentManager;
use App\Services\TimeframeResampler;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HeikinBreadthBacktestEngine
{
    /**
     * Run backtest for a single configuration or date range.
     */
    public function run(array $config = []): array
    {
        $interval          = (int)($config['interval'] ?? 15);
        $threshold         = (float)($config['threshold'] ?? 75.0);
        $slMode            = $config['sl_mode'] ?? 'normal'; // 'normal' or 'ha'
        $breadthFilterMode = $config['breadth_mode'] ?? 'both'; // 'both', 'count', 'weight', 'either'
        $lotSize           = (int)($config['lot_size'] ?? 65);
        $maxTradesPerDay   = (int)($config['max_trades_per_day'] ?? 2);
        $maxDurationMins   = (int)($config['max_duration_mins'] ?? 0); // 0 = unlimited, >0 = exit after X mins
        $minEntryTime      = $config['min_entry_time'] ?? '09:15'; // Earliest allowed trade entry time
        $maxEntryTime      = $config['max_entry_time'] ?? '14:30'; // No new trade entries at/after this time
        $fromDate          = $config['from_date'] ?? null;
        $toDate            = $config['to_date'] ?? null;

        $dates = $this->resolveDates($fromDate, $toDate);

        if (empty($dates)) {
            return [
                'success' => false,
                'message' => 'No trading days found for the selected date range.',
                'trades'  => [],
                'stats'   => [],
            ];
        }

        [$trades, $discardedTrades, $discardedLossesCount] = $this->simulateDateRange(
            $dates,
            $interval,
            $threshold,
            $slMode,
            $breadthFilterMode,
            $lotSize,
            $maxTradesPerDay,
            $maxDurationMins,
            $minEntryTime,
            $maxEntryTime
        );

        $stats = $this->calculateStatistics($trades, count($dates), $lotSize, $discardedLossesCount);

        return [
            'success' => true,
            'config'  => [
                'interval'               => $interval,
                'threshold'              => $threshold,
                'sl_mode'                => $slMode,
                'breadth_mode'           => $breadthFilterMode,
                'lot_size'               => $lotSize,
                'max_trades_per_day'     => $maxTradesPerDay,
                'max_duration_mins'      => $maxDurationMins,
                'discarded_losses_count' => $discardedLossesCount,
                'min_entry_time'         => $minEntryTime,
                'max_entry_time'         => $maxEntryTime,
                'from_date'              => $dates[0] ?? null,
                'to_date'                => end($dates) ?: null,
                'total_trading_days'     => count($dates),
            ],
            'stats'            => $stats,
            'trades'           => $trades,
            'discarded_trades' => $discardedTrades,
        ];
    }

    /**
     * Run full Priority Comparison Matrix across user-requested combinations:
     * 1. 15 mins with 75, 80, 85, 90%
     * 2. 5 mins with 75, 80, 85, 90%
     * 3. 30 mins with 70, 75, 80, 85%
     */
    public function runPriorityMatrix(array $config = []): array
    {
        $fromDate          = $config['from_date'] ?? null;
        $toDate            = $config['to_date'] ?? null;
        $slMode            = $config['sl_mode'] ?? 'normal';
        $breadthFilterMode = $config['breadth_mode'] ?? 'both';
        $lotSize           = (int)($config['lot_size'] ?? 65);
        $maxTradesPerDay   = (int)($config['max_trades_per_day'] ?? 2);
        $maxDurationMins   = (int)($config['max_duration_mins'] ?? 0);
        $minEntryTime      = $config['min_entry_time'] ?? '09:15';
        $maxEntryTime      = $config['max_entry_time'] ?? '14:30';

        $dates = $this->resolveDates($fromDate, $toDate);

        if (empty($dates)) {
            return [
                'success' => false,
                'message' => 'No trading days found for the selected date range.',
                'matrix'  => [],
            ];
        }

        // Priority combinations as specified by user
        $combinations = [
            // Priority 1: 15 mins
            ['interval' => 15, 'threshold' => 75.0, 'priority' => 1, 'label' => '15m @ 75%'],
            ['interval' => 15, 'threshold' => 80.0, 'priority' => 1, 'label' => '15m @ 80%'],
            ['interval' => 15, 'threshold' => 85.0, 'priority' => 1, 'label' => '15m @ 85%'],
            ['interval' => 15, 'threshold' => 90.0, 'priority' => 1, 'label' => '15m @ 90%'],

            // Priority 2: 5 mins
            ['interval' => 5,  'threshold' => 75.0, 'priority' => 2, 'label' => '5m @ 75%'],
            ['interval' => 5,  'threshold' => 80.0, 'priority' => 2, 'label' => '5m @ 80%'],
            ['interval' => 5,  'threshold' => 85.0, 'priority' => 2, 'label' => '5m @ 85%'],
            ['interval' => 5,  'threshold' => 90.0, 'priority' => 2, 'label' => '5m @ 90%'],

            // Priority 3: 30 mins
            ['interval' => 30, 'threshold' => 70.0, 'priority' => 3, 'label' => '30m @ 70%'],
            ['interval' => 30, 'threshold' => 75.0, 'priority' => 3, 'label' => '30m @ 75%'],
            ['interval' => 30, 'threshold' => 80.0, 'priority' => 3, 'label' => '30m @ 80%'],
            ['interval' => 30, 'threshold' => 85.0, 'priority' => 3, 'label' => '30m @ 85%'],
        ];

        // Stream across date range in 5-day chunks to prevent PHP memory exhaustion
        $allComboTrades = array_fill(0, count($combinations), []);
        $chunks = array_chunk($dates, 5);

        foreach ($chunks as $chunk) {
            $chunkStart = $chunk[0] . ' 09:15:00';
            $chunkEnd   = end($chunk) . ' 15:30:00';

            $rows = DB::table('ohlc_5m_quotes')
                ->whereIn('instrument_type', ['INDEX', 'EQ'])
                ->where('ts_at', '>=', $chunkStart)
                ->where('ts_at', '<=', $chunkEnd)
                ->select('instrument_type', 'trading_symbol', 'open', 'high', 'low', 'close', 'ts_at', 'volume')
                ->orderBy('ts_at')
                ->get();

            $quotesByDate = [];
            foreach ($rows as $r) {
                $d = substr($r->ts_at, 0, 10);
                $quotesByDate[$d][] = $r;
            }
            unset($rows);

            foreach ($combinations as $cIdx => $combo) {
                $chunkTrades = $this->simulateGroupedQuotes(
                    $chunk,
                    $quotesByDate,
                    $combo['interval'],
                    $combo['threshold'],
                    $slMode,
                    $breadthFilterMode,
                    $lotSize,
                    $maxTradesPerDay,
                    $maxDurationMins,
                    $minEntryTime,
                    $maxEntryTime
                );

                foreach ($chunkTrades as $ct) {
                    $allComboTrades[$cIdx][] = $ct;
                }
            }
            unset($quotesByDate);
        }

        $matrixResults = [];

        foreach ($combinations as $cIdx => $combo) {
            $interval  = $combo['interval'];
            $threshold = $combo['threshold'];
            $rawTrades = $allComboTrades[$cIdx];

            [$trades, $discardedTrades, $discardedCount] = $this->filterLossTradesByDuration($rawTrades, $maxDurationMins);

            $stats = $this->calculateStatistics($trades, count($dates), $lotSize, $discardedCount);

            $matrixResults[] = [
                'interval'         => $interval,
                'threshold'        => $threshold,
                'priority'         => $combo['priority'],
                'label'            => $combo['label'],
                'total_trades'     => $stats['total_trades'],
                'win_rate'         => $stats['win_rate'],
                'profit_factor'    => $stats['profit_factor'],
                'net_pts'          => $stats['net_pts'],
                'net_pnl'          => $stats['net_pnl'],
                'avg_duration'     => $stats['avg_duration_mins'],
                'avg_pts'          => $stats['avg_pts'],
                'max_win_pts'      => $stats['max_win_pts'],
                'max_loss_pts'     => $stats['max_loss_pts'],
                'max_drawdown_pts' => $stats['max_drawdown_pts'],
                'max_drawdown_pnl' => $stats['max_drawdown_pnl'],
                'wins'             => $stats['wins'],
                'losses'           => $stats['losses'],
                'discarded_losses' => $discardedCount,
                'sl_exits'         => $stats['exit_reasons']['STOP_LOSS'] ?? 0,
                'time_exits'       => $stats['exit_reasons']['TIME_EXIT_1500'] ?? 0,
                'reversals'        => $stats['exit_reasons']['REVERSAL'] ?? 0,
            ];
        }

        return [
            'success'            => true,
            'total_trading_days' => count($dates),
            'from_date'          => $dates[0] ?? null,
            'to_date'            => end($dates) ?: null,
            'sl_mode'            => $slMode,
            'breadth_mode'       => $breadthFilterMode,
            'min_entry_time'     => $minEntryTime,
            'max_entry_time'     => $maxEntryTime,
            'matrix'             => $matrixResults,
        ];
    }

    /**
     * Resolve eligible trading dates from nse_working_days.
     */
    public function resolveDates(?string $fromDate, ?string $toDate): array
    {
        $query = DB::table('nse_working_days')
            ->orderBy('working_date', 'asc');

        if ($fromDate) {
            $query->where('working_date', '>=', $fromDate);
        } else {
            // Default to past 90 days if not specified
            $query->where('working_date', '>=', Carbon::now('Asia/Kolkata')->subMonths(3)->toDateString());
        }

        if ($toDate) {
            $query->where('working_date', '<=', $toDate);
        } else {
            $query->where('working_date', '<=', Carbon::now('Asia/Kolkata')->toDateString());
        }

        return $query->pluck('working_date')->toArray();
    }

    /**
     * Fetch all relevant OHLC 5m quotes for given dates in bulk chunks.
     */
    protected function fetchQuotesGroupedByDate(array $dates): array
    {
        if (empty($dates)) return [];

        $quotesByDate = [];
        $chunks = array_chunk($dates, 30); // 30-day blocks for optimal memory and SQL efficiency

        foreach ($chunks as $chunk) {
            $chunkStart = $chunk[0] . ' 09:15:00';
            $chunkEnd   = end($chunk) . ' 15:30:00';

            $rows = DB::table('ohlc_5m_quotes')
                ->whereIn('instrument_type', ['INDEX', 'EQ'])
                ->where('ts_at', '>=', $chunkStart)
                ->where('ts_at', '<=', $chunkEnd)
                ->select('instrument_type', 'trading_symbol', 'open', 'high', 'low', 'close', 'ts_at', 'volume')
                ->orderBy('ts_at')
                ->get();

            foreach ($rows as $r) {
                $d = substr($r->ts_at, 0, 10);
                $quotesByDate[$d][] = $r;
            }
        }

        return $quotesByDate;
    }

    /**
     * Simulate across date range with dynamic quote fetching streamed in chunks.
     */
    protected function simulateDateRange(
        array $dates,
        int $interval,
        float $threshold,
        string $slMode,
        string $breadthFilterMode,
        int $lotSize,
        int $maxTradesPerDay,
        int $maxDurationMins = 0,
        string $minEntryTime = '09:15',
        string $maxEntryTime = '14:30'
    ): array {
        $allTrades = [];
        $chunks = array_chunk($dates, 7);

        foreach ($chunks as $chunk) {
            $chunkStart = $chunk[0] . ' 09:15:00';
            $chunkEnd   = end($chunk) . ' 15:30:00';

            $rows = DB::table('ohlc_5m_quotes')
                ->whereIn('instrument_type', ['INDEX', 'EQ'])
                ->where('ts_at', '>=', $chunkStart)
                ->where('ts_at', '<=', $chunkEnd)
                ->select('instrument_type', 'trading_symbol', 'open', 'high', 'low', 'close', 'ts_at', 'volume')
                ->orderBy('ts_at')
                ->get();

            $quotesByDate = [];
            foreach ($rows as $r) {
                $d = substr($r->ts_at, 0, 10);
                $quotesByDate[$d][] = $r;
            }
            unset($rows);

            $chunkTrades = $this->simulateGroupedQuotes(
                $chunk,
                $quotesByDate,
                $interval,
                $threshold,
                $slMode,
                $breadthFilterMode,
                $lotSize,
                $maxTradesPerDay,
                $maxDurationMins,
                $minEntryTime,
                $maxEntryTime
            );

            foreach ($chunkTrades as $ct) {
                $allTrades[] = $ct;
            }
            unset($quotesByDate);
        }

        return $this->filterLossTradesByDuration($allTrades, $maxDurationMins);
    }

    /**
     * Filter loss trades exceeding max holding duration.
     * Winning and breakeven trades (points >= 0) are 100% preserved (profits run unlimited).
     * Only losing trades (points < 0) with duration > maxDurationMins are discarded (moved to Cost-to-Cost / avoided).
     */
    public function filterLossTradesByDuration(array $trades, int $maxDurationMins): array
    {
        if ($maxDurationMins <= 0) {
            foreach ($trades as $idx => &$tr) {
                $tr['id'] = $idx + 1;
                $tr['is_discarded'] = false;
            }
            unset($tr);
            return [$trades, [], 0];
        }

        $filtered = [];
        $discarded = [];

        foreach ($trades as $t) {
            if ($t['points'] >= 0) {
                // Winning or breakeven trade: keep regardless of duration
                $t['is_discarded'] = false;
                $filtered[] = $t;
            } else {
                // Losing trade: only keep if duration <= maxDurationMins
                if ($t['duration_mins'] <= $maxDurationMins) {
                    $t['is_discarded'] = false;
                    $filtered[] = $t;
                } else {
                    $t['is_discarded'] = true;
                    $t['discard_reason'] = "Stagnant Loss (Held {$t['duration_mins']}m > {$maxDurationMins}m limit)";
                    $discarded[] = $t;
                }
            }
        }

        foreach ($filtered as $idx => &$tr) {
            $tr['id'] = $idx + 1;
        }
        unset($tr);

        foreach ($discarded as $idx => &$tr) {
            $tr['id'] = $idx + 1;
        }
        unset($tr);

        return [$filtered, $discarded, count($discarded)];
    }

    /**
     * Core simulation loop across pre-grouped quotes.
     */
    public function simulateGroupedQuotes(
        array $dates,
        array $quotesByDate,
        int $interval,
        float $threshold,
        string $slMode,
        string $breadthFilterMode,
        int $lotSize,
        int $maxTradesPerDay,
        int $maxDurationMins = 0,
        string $minEntryTime = '09:15',
        string $maxEntryTime = '14:30'
    ): array {
        $allTrades = [];
        $globalTradeId = 0;

        foreach ($dates as $date) {
            $dayQuotes = $quotesByDate[$date] ?? [];
            if (empty($dayQuotes)) continue;

            $periodInfo = NiftyConstituentManager::getConstituentsForDate($date);
            $weights = [];
            foreach ($periodInfo['constituents'] as $c) {
                $weights[$c['symbol']] = (float)$c['weight'];
            }

            $nifty5m = [];
            $stockQuotesBySym = [];

            foreach ($dayQuotes as $q) {
                if ($q->instrument_type === 'INDEX' && in_array($q->trading_symbol, ['NIFTY', 'Nifty 50'])) {
                    $timeStr = substr($q->ts_at, 11, 5);
                    $nifty5m[] = [
                        'time_str' => $timeStr,
                        'open'     => (float)$q->open,
                        'high'     => (float)$q->high,
                        'low'      => (float)$q->low,
                        'close'    => (float)$q->close,
                        'volume'   => (int)$q->volume,
                    ];
                } elseif ($q->instrument_type === 'EQ') {
                    $stockQuotesBySym[$q->trading_symbol][] = $q;
                }
            }

            if (empty($nifty5m)) continue;

            // 1. Resample Nifty candles to selected timeframe
            $niftyCandles = ($interval === 5)
                ? $nifty5m
                : TimeframeResampler::resampleCandles($nifty5m, $interval, $date);

            // 2. Resample Stock quotes to selected timeframe
            $stockBuckets = ($interval === 5)
                ? null
                : TimeframeResampler::resampleStockQuotes($stockQuotesBySym, $interval);

            // 3. Compute Candle-level Breadth Aggregations
            $candlesData = [];
            $totalConstituents = count($periodInfo['constituents']);

            foreach ($niftyCandles as $c) {
                $t = $c['time_str'];
                $greenCount  = 0;
                $redCount    = 0;
                $greenWeight = 0.0;
                $redWeight   = 0.0;

                foreach ($periodInfo['constituents'] as $stock) {
                    $sym = $stock['symbol'];
                    $w   = $weights[$sym] ?? 1.0;

                    if ($interval === 5) {
                        $stkQuotes = $stockQuotesBySym[$sym] ?? [];
                        $q = null;
                        foreach ($stkQuotes as $sq) {
                            if (substr($sq->ts_at, 11, 5) === $t) {
                                $q = $sq;
                                break;
                            }
                        }
                        if ($q) {
                            if ($q->close > $q->open) {
                                $greenCount++;
                                $greenWeight += $w;
                            } elseif ($q->close < $q->open) {
                                $redCount++;
                                $redWeight += $w;
                            }
                        }
                    } else {
                        if (isset($stockBuckets[$sym][$t])) {
                            $b = $stockBuckets[$sym][$t];
                            if ($b['close'] > $b['open']) {
                                $greenCount++;
                                $greenWeight += $w;
                            } elseif ($b['close'] < $b['open']) {
                                $redCount++;
                                $redWeight += $w;
                            }
                        }
                    }
                }

                $greenCountPct = $totalConstituents > 0 ? round(($greenCount / $totalConstituents) * 100) : 0;
                $redCountPct   = $totalConstituents > 0 ? round(($redCount / $totalConstituents) * 100) : 0;
                $greenWeight   = round($greenWeight, 1);
                $redWeight     = round($redWeight, 1);

                $candlesData[] = [
                    'time_str'        => $t,
                    'open'            => (float)$c['open'],
                    'high'            => (float)$c['high'],
                    'low'             => (float)$c['low'],
                    'close'           => (float)$c['close'],
                    'green_count_pct' => $greenCountPct,
                    'red_count_pct'   => $redCountPct,
                    'green_weight'    => $greenWeight,
                    'red_weight'      => $redWeight,
                ];
            }

            // 4. Calculate Heikin Ashi Candles
            $haCandles = [];
            $prevHa = null;
            foreach ($candlesData as $i => $c) {
                $haClose = ($c['open'] + $c['high'] + $c['low'] + $c['close']) / 4;
                $haOpen  = ($i === 0 || !$prevHa)
                    ? ($c['open'] + $c['close']) / 2
                    : ($prevHa['open'] + $prevHa['close']) / 2;

                $haHigh = max($c['high'], $haOpen, $haClose);
                $haLow  = min($c['low'], $haOpen, $haClose);

                $topWick    = round($haHigh - max($haOpen, $haClose), 2);
                $bottomWick = round(min($haOpen, $haClose) - $haLow, 2);

                $haCandles[$i] = [
                    'open'        => round($haOpen, 2),
                    'high'        => round($haHigh, 2),
                    'low'         => round($haLow, 2),
                    'close'       => round($haClose, 2),
                    'top_wick'    => $topWick,
                    'bottom_wick' => $bottomWick,
                    // Rule 4: Bullish = Green HA + NO bottom wick (flat bottom)
                    'clean_bull'  => ($haClose >= $haOpen) && ($bottomWick <= 0.15),
                    // Rule 4: Bearish = Red HA + NO top wick (flat top)
                    'clean_bear'  => ($haClose < $haOpen) && ($topWick <= 0.15),
                ];
                $prevHa = $haCandles[$i];
            }

            // 5. Day Trade Simulation
            $activeTrade      = null;
            $tradesTodayCount = 0;
            $nCandles         = count($candlesData);

            for ($i = 0; $i < $nCandles; $i++) {
                $c  = $candlesData[$i];
                $ha = $haCandles[$i];
                $t  = $c['time_str'];

                // Check active trade exits on current candle
                if ($activeTrade !== null) {
                    $activeTrade['candles_held']++;

                    // Update MFE / MAE
                    if ($activeTrade['direction'] === 'LONG') {
                        $favorable = $c['high'] - $activeTrade['entry_price'];
                        $adverse   = $activeTrade['entry_price'] - $c['low'];
                        if ($c['high'] > $activeTrade['max_price']) $activeTrade['max_price'] = $c['high'];
                        if ($c['low'] < $activeTrade['min_price'])   $activeTrade['min_price'] = $c['low'];
                    } else {
                        $favorable = $activeTrade['entry_price'] - $c['low'];
                        $adverse   = $c['high'] - $activeTrade['entry_price'];
                        if ($c['low'] < $activeTrade['min_price'])   $activeTrade['min_price'] = $c['low'];
                        if ($c['high'] > $activeTrade['max_price']) $activeTrade['max_price'] = $c['high'];
                    }
                    if ($favorable > $activeTrade['mfe_pts']) $activeTrade['mfe_pts'] = round($favorable, 2);
                    if ($adverse > $activeTrade['mae_pts'])   $activeTrade['mae_pts'] = round($adverse, 2);

                    $exitNow    = false;
                    $exitReason = null;
                    $exitPrice  = null;

                    // Stop loss: Executed ONLY when NORMAL candle closes beyond SL level
                    if ($activeTrade['direction'] === 'LONG') {
                        if ($c['close'] < $activeTrade['sl_price']) {
                            $exitNow    = true;
                            $exitReason = 'STOP_LOSS';
                            $exitPrice  = $c['close'];
                        }
                    } else {
                        if ($c['close'] > $activeTrade['sl_price']) {
                            $exitNow    = true;
                            $exitReason = 'STOP_LOSS';
                            $exitPrice  = $c['close'];
                        }
                    }

                    // 15:00 Time Exit (Exit trade at 15:00 either in profit or loss)
                    [$h, $m] = explode(':', $t);
                    $candleEndMin = ((int)$h * 60) + (int)$m + $interval;
                    if (!$exitNow && $candleEndMin >= (15 * 60)) {
                        $exitNow    = true;
                        $exitReason = 'TIME_EXIT_1500';
                        $exitPrice  = $c['close'];
                    }

                    if ($exitNow) {
                        $activeTrade['exit_time']   = $t;
                        $activeTrade['exit_price']  = $exitPrice;
                        $activeTrade['exit_reason'] = $exitReason;

                        $pts = ($activeTrade['direction'] === 'LONG')
                            ? ($exitPrice - $activeTrade['entry_price'])
                            : ($activeTrade['entry_price'] - $exitPrice);

                        $activeTrade['points']        = round($pts, 2);
                        $activeTrade['pnl']           = round($pts * $lotSize, 2);
                        $activeTrade['duration_mins'] = $activeTrade['candles_held'] * $interval;
                        $activeTrade['mfe_pnl']       = round($activeTrade['mfe_pts'] * $lotSize, 2);
                        $activeTrade['mae_pnl']       = round($activeTrade['mae_pts'] * $lotSize, 2);
                        $activeTrade['status']        = ($pts > 0) ? 'WIN' : (($pts < 0) ? 'LOSS' : 'BREAKEVEN');

                        $allTrades[] = $activeTrade;
                        $activeTrade = null;
                    }
                }

                // Check for Confirmation Signal on candle $i
                // Next candle must exist to enter
                if ($i + 1 >= $nCandles) continue;
                $nextCandle = $candlesData[$i + 1];

                // Time window filters:
                // Min Entry Time: Trade only on/after X entry time (e.g. 09:30, 09:45, 10:00)
                if (!empty($minEntryTime) && $nextCandle['time_str'] < $minEntryTime) continue;

                // Max Entry Time: No trade entries initiated at or after cutoff time (e.g. 14:30 or 13:30)
                if (!empty($maxEntryTime) && ($t >= $maxEntryTime || $nextCandle['time_str'] > $maxEntryTime)) continue;

                $bullBreadth = false;
                $bearBreadth = false;

                if ($breadthFilterMode === 'both') {
                    $bullBreadth = ($c['green_count_pct'] >= $threshold && $c['green_weight'] >= $threshold);
                    $bearBreadth = ($c['red_count_pct'] >= $threshold && $c['red_weight'] >= $threshold);
                } elseif ($breadthFilterMode === 'count') {
                    $bullBreadth = ($c['green_count_pct'] >= $threshold);
                    $bearBreadth = ($c['red_count_pct'] >= $threshold);
                } elseif ($breadthFilterMode === 'weight') {
                    $bullBreadth = ($c['green_weight'] >= $threshold);
                    $bearBreadth = ($c['red_weight'] >= $threshold);
                } else { // either
                    $bullBreadth = ($c['green_count_pct'] >= $threshold || $c['green_weight'] >= $threshold);
                    $bearBreadth = ($c['red_count_pct'] >= $threshold || $c['red_weight'] >= $threshold);
                }

                $bullSignal = $bullBreadth && $ha['clean_bull'];
                $bearSignal = $bearBreadth && $ha['clean_bear'];

                if (!$bullSignal && !$bearSignal) continue;

                $sigDirection = $bullSignal ? 'LONG' : 'SHORT';

                // Stop loss reference level (Confirmation candle low for long, high for short)
                // Toggle: normal candle (default) vs Heikin Ashi candle
                $slPrice = 0.0;
                if ($sigDirection === 'LONG') {
                    $slPrice = ($slMode === 'ha') ? $ha['low'] : $c['low'];
                } else {
                    $slPrice = ($slMode === 'ha') ? $ha['high'] : $c['high'];
                }

                // If currently holding an active trade:
                if ($activeTrade !== null) {
                    if ($activeTrade['direction'] !== $sigDirection) {
                        // Reversal signal! Exit current trade and reverse
                        $activeTrade['exit_time']   = $nextCandle['time_str'];
                        $activeTrade['exit_price']  = $nextCandle['open'];
                        $activeTrade['exit_reason'] = 'REVERSAL';

                        $pts = ($activeTrade['direction'] === 'LONG')
                            ? ($activeTrade['exit_price'] - $activeTrade['entry_price'])
                            : ($activeTrade['entry_price'] - $activeTrade['exit_price']);

                        $activeTrade['points']        = round($pts, 2);
                        $activeTrade['pnl']           = round($pts * $lotSize, 2);
                        $activeTrade['duration_mins'] = $activeTrade['candles_held'] * $interval;
                        $activeTrade['mfe_pnl']       = round($activeTrade['mfe_pts'] * $lotSize, 2);
                        $activeTrade['mae_pnl']       = round($activeTrade['mae_pts'] * $lotSize, 2);
                        $activeTrade['status']        = ($pts > 0) ? 'WIN' : (($pts < 0) ? 'LOSS' : 'BREAKEVEN');

                        $allTrades[] = $activeTrade;
                        $activeTrade = null;

                        // Enter opposite trade if under daily trade limit
                        if ($tradesTodayCount < $maxTradesPerDay) {
                            $tradesTodayCount++;
                            $globalTradeId++;
                            $activeTrade = [
                                'id'                   => $globalTradeId,
                                'date'                 => $date,
                                'trade_no_in_day'      => $tradesTodayCount,
                                'direction'            => $sigDirection,
                                'signal_time'          => $t,
                                'signal_breadth_count' => $bullSignal ? $c['green_count_pct'] : $c['red_count_pct'],
                                'signal_breadth_weight'=> $bullSignal ? $c['green_weight'] : $c['red_weight'],
                                'signal_candle'        => [
                                    'open'  => $c['open'],
                                    'high'  => $c['high'],
                                    'low'   => $c['low'],
                                    'close' => $c['close'],
                                ],
                                'signal_ha'            => $ha,
                                'entry_time'           => $nextCandle['time_str'],
                                'entry_price'          => $nextCandle['open'],
                                'sl_price'             => $slPrice,
                                'sl_mode'              => $slMode,
                                'candles_held'         => 0,
                                'max_price'            => $nextCandle['open'],
                                'min_price'            => $nextCandle['open'],
                                'mfe_pts'              => 0.0,
                                'mae_pts'              => 0.0,
                            ];
                        }
                    }
                    // Same direction -> ignore new signal, remain in existing position
                } else {
                    // No active trade -> Enter on next candle open if under daily limit
                    if ($tradesTodayCount < $maxTradesPerDay) {
                        $tradesTodayCount++;
                        $globalTradeId++;
                        $activeTrade = [
                            'id'                   => $globalTradeId,
                            'date'                 => $date,
                            'trade_no_in_day'      => $tradesTodayCount,
                            'direction'            => $sigDirection,
                            'signal_time'          => $t,
                            'signal_breadth_count' => $bullSignal ? $c['green_count_pct'] : $c['red_count_pct'],
                            'signal_breadth_weight'=> $bullSignal ? $c['green_weight'] : $c['red_weight'],
                            'signal_candle'        => [
                                'open'  => $c['open'],
                                'high'  => $c['high'],
                                'low'   => $c['low'],
                                'close' => $c['close'],
                            ],
                            'signal_ha'            => $ha,
                            'entry_time'           => $nextCandle['time_str'],
                            'entry_price'          => $nextCandle['open'],
                            'sl_price'             => $slPrice,
                            'sl_mode'              => $slMode,
                            'candles_held'         => 0,
                            'max_price'            => $nextCandle['open'],
                            'min_price'            => $nextCandle['open'],
                            'mfe_pts'              => 0.0,
                            'mae_pts'              => 0.0,
                        ];
                    }
                }
            }

            // End of day: Force close any remaining open trade at last candle close
            if ($activeTrade !== null) {
                $lastC = end($candlesData);
                $activeTrade['exit_time']   = $lastC['time_str'];
                $activeTrade['exit_price']  = $lastC['close'];
                $activeTrade['exit_reason'] = 'TIME_EXIT_1500';

                $pts = ($activeTrade['direction'] === 'LONG')
                    ? ($lastC['close'] - $activeTrade['entry_price'])
                    : ($activeTrade['entry_price'] - $lastC['close']);

                $activeTrade['points']        = round($pts, 2);
                $activeTrade['pnl']           = round($pts * $lotSize, 2);
                $activeTrade['duration_mins'] = max($interval, $activeTrade['candles_held'] * $interval);
                $activeTrade['mfe_pnl']       = round($activeTrade['mfe_pts'] * $lotSize, 2);
                $activeTrade['mae_pnl']       = round($activeTrade['mae_pts'] * $lotSize, 2);
                $activeTrade['status']        = ($pts > 0) ? 'WIN' : (($pts < 0) ? 'LOSS' : 'BREAKEVEN');

                $allTrades[] = $activeTrade;
                $activeTrade = null;
            }
        }

        return $allTrades;
    }

    /**
     * Compute comprehensive performance statistics across executed trades.
     */
    public function calculateStatistics(array $trades, int $totalDays, int $lotSize = 65, int $discardedLossesCount = 0): array
    {
        $totalTrades = count($trades);

        if ($totalTrades === 0) {
            return [
                'total_trades'           => 0,
                'total_trading_days'     => $totalDays,
                'trades_per_day'         => 0.0,
                'wins'                   => 0,
                'losses'                 => 0,
                'breakeven'              => 0,
                'discarded_losses_count' => $discardedLossesCount,
                'win_rate'               => 0.0,
                'loss_rate'          => 0.0,
                'net_pts'            => 0.0,
                'net_pnl'            => 0.0,
                'gross_profit_pts'   => 0.0,
                'gross_loss_pts'     => 0.0,
                'profit_factor'      => 0.0,
                'avg_pts'            => 0.0,
                'avg_pnl'            => 0.0,
                'avg_win_pts'        => 0.0,
                'avg_win_pnl'        => 0.0,
                'avg_loss_pts'       => 0.0,
                'avg_loss_pnl'       => 0.0,
                'risk_reward_ratio'  => 0.0,
                'max_win_pts'        => 0.0,
                'max_win_pnl'        => 0.0,
                'max_loss_pts'       => 0.0,
                'max_loss_pnl'       => 0.0,
                'avg_duration_mins'  => 0.0,
                'avg_candles_held'   => 0.0,
                'avg_mfe_pts'        => 0.0,
                'avg_mae_pts'        => 0.0,
                'max_drawdown_pts'   => 0.0,
                'max_drawdown_pnl'   => 0.0,
                'exit_reasons'       => [
                    'STOP_LOSS'      => 0,
                    'MAX_DURATION'   => 0,
                    'TIME_EXIT_1500' => 0,
                    'REVERSAL'       => 0,
                ],
                'direction_stats'    => [
                    'LONG'           => ['count' => 0, 'wins' => 0, 'net_pts' => 0.0, 'net_pnl' => 0.0, 'win_rate' => 0.0],
                    'SHORT'          => ['count' => 0, 'wins' => 0, 'net_pts' => 0.0, 'net_pnl' => 0.0, 'win_rate' => 0.0],
                ],
            ];
        }

        $wins = 0;
        $losses = 0;
        $breakevens = 0;

        $grossProfitPts = 0.0;
        $grossLossPts   = 0.0;
        $netPts         = 0.0;

        $maxWinPts  = 0.0;
        $maxLossPts = 0.0;

        $totalDuration = 0;
        $totalCandles  = 0;
        $totalMfe      = 0.0;
        $totalMae      = 0.0;

        $exitReasons = [
            'STOP_LOSS'      => 0,
            'MAX_DURATION'   => 0,
            'TIME_EXIT_1500' => 0,
            'REVERSAL'       => 0,
        ];

        $directionStats = [
            'LONG'  => ['count' => 0, 'wins' => 0, 'net_pts' => 0.0, 'net_pnl' => 0.0],
            'SHORT' => ['count' => 0, 'wins' => 0, 'net_pts' => 0.0, 'net_pnl' => 0.0],
        ];

        // Drawdown tracking
        $cumulativePts   = 0.0;
        $peakPts         = 0.0;
        $maxDrawdownPts  = 0.0;

        foreach ($trades as $t) {
            $pts = (float)$t['points'];
            $dir = $t['direction'] ?? 'LONG';

            $netPts += $pts;
            $cumulativePts += $pts;
            if ($cumulativePts > $peakPts) {
                $peakPts = $cumulativePts;
            }
            $dd = $peakPts - $cumulativePts;
            if ($dd > $maxDrawdownPts) {
                $maxDrawdownPts = $dd;
            }

            if ($pts > 0) {
                $wins++;
                $grossProfitPts += $pts;
                if ($pts > $maxWinPts) $maxWinPts = $pts;
            } elseif ($pts < 0) {
                $losses++;
                $grossLossPts += abs($pts);
                if (abs($pts) > $maxLossPts) $maxLossPts = abs($pts);
            } else {
                $breakevens++;
            }

            $totalDuration += (int)($t['duration_mins'] ?? 0);
            $totalCandles  += (int)($t['candles_held'] ?? 0);
            $totalMfe      += (float)($t['mfe_pts'] ?? 0);
            $totalMae      += (float)($t['mae_pts'] ?? 0);

            $reason = $t['exit_reason'] ?? 'TIME_EXIT_1500';
            $exitReasons[$reason] = ($exitReasons[$reason] ?? 0) + 1;

            if (isset($directionStats[$dir])) {
                $directionStats[$dir]['count']++;
                if ($pts > 0) $directionStats[$dir]['wins']++;
                $directionStats[$dir]['net_pts'] += $pts;
                $directionStats[$dir]['net_pnl'] += ($pts * $lotSize);
            }
        }

        $winRate  = round(($wins / $totalTrades) * 100, 2);
        $lossRate = round(($losses / $totalTrades) * 100, 2);

        $profitFactor = $grossLossPts > 0 ? round($grossProfitPts / $grossLossPts, 2) : ($grossProfitPts > 0 ? 99.99 : 0.0);

        $avgPts = round($netPts / $totalTrades, 2);
        $avgWinPts = $wins > 0 ? round($grossProfitPts / $wins, 2) : 0.0;
        $avgLossPts = $losses > 0 ? round($grossLossPts / $losses, 2) : 0.0;
        $riskReward = $avgLossPts > 0 ? round($avgWinPts / $avgLossPts, 2) : 0.0;

        foreach ($directionStats as $dKey => $dData) {
            $cnt = $dData['count'];
            $directionStats[$dKey]['win_rate'] = $cnt > 0 ? round(($dData['wins'] / $cnt) * 100, 1) : 0.0;
            $directionStats[$dKey]['net_pts']  = round($dData['net_pts'], 2);
            $directionStats[$dKey]['net_pnl']  = round($dData['net_pnl'], 2);
        }

        return [
            'total_trades'       => $totalTrades,
            'total_trading_days' => $totalDays,
            'trades_per_day'     => $totalDays > 0 ? round($totalTrades / $totalDays, 2) : 0.0,
            'wins'                   => $wins,
            'losses'                 => $losses,
            'breakeven'              => $breakevens,
            'discarded_losses_count' => $discardedLossesCount,
            'win_rate'               => $winRate,
            'loss_rate'          => $lossRate,
            'net_pts'            => round($netPts, 2),
            'net_pnl'            => round($netPts * $lotSize, 2),
            'gross_profit_pts'   => round($grossProfitPts, 2),
            'gross_loss_pts'     => round($grossLossPts, 2),
            'profit_factor'      => $profitFactor,
            'avg_pts'            => $avgPts,
            'avg_pnl'            => round($avgPts * $lotSize, 2),
            'avg_win_pts'        => $avgWinPts,
            'avg_win_pnl'        => round($avgWinPts * $lotSize, 2),
            'avg_loss_pts'       => $avgLossPts,
            'avg_loss_pnl'       => round($avgLossPts * $lotSize, 2),
            'risk_reward_ratio'  => $riskReward,
            'max_win_pts'        => round($maxWinPts, 2),
            'max_win_pnl'        => round($maxWinPts * $lotSize, 2),
            'max_loss_pts'       => round($maxLossPts, 2),
            'max_loss_pnl'       => round($maxLossPts * $lotSize, 2),
            'avg_duration_mins'  => round($totalDuration / $totalTrades, 1),
            'avg_candles_held'   => round($totalCandles / $totalTrades, 1),
            'avg_mfe_pts'        => round($totalMfe / $totalTrades, 2),
            'avg_mae_pts'        => round($totalMae / $totalTrades, 2),
            'max_drawdown_pts'   => round($maxDrawdownPts, 2),
            'max_drawdown_pnl'   => round($maxDrawdownPts * $lotSize, 2),
            'exit_reasons'       => $exitReasons,
            'direction_stats'    => $directionStats,
        ];
    }
}
