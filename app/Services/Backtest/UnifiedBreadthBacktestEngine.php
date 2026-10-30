<?php

namespace App\Services\Backtest;

use App\Http\Controllers\NiftyWeightageMonitorController;
use App\Http\Controllers\OptionsBreadthMonitorController;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UnifiedBreadthBacktestEngine
{
    protected NiftyWeightageMonitorController $stockController;
    protected OptionsBreadthMonitorController $optionsController;

    public function __construct()
    {
        $this->stockController = app(NiftyWeightageMonitorController::class);
        $this->optionsController = app(OptionsBreadthMonitorController::class);
    }

    /**
     * Run unified backtest across specified dates.
     *
     * @param array $config
     *   - mode: 'stocks' | 'confluence' | 'both'
     *   - from_date: YYYY-MM-DD
     *   - to_date: YYYY-MM-DD
     *   - stock_threshold: int (default 80)
     *   - option_threshold: int (default 75)
     *   - sl_buffer: float (default 15.0)
     *   - tp1: float (default 100.0)
     *   - tp2: float (default 150.0)
     *   - basket_mode: 'dynamic' | 'fixed' (default 'dynamic')
     *   - max_trades_per_day: int (default 2)
     *   - allow_reentry: bool (default false - wait for trade completion)
     * @param callable|null $progressCallback fn(string $date, int $currentIndex, int $totalDays, array $dayTrades)
     * @return array
     */
    public function run(array $config = [], ?callable $progressCallback = null): array
    {
        $mode = $config['mode'] ?? 'both';
        $stockThreshold = (float)($config['stock_threshold'] ?? 80.0);
        $optionThreshold = (float)($config['option_threshold'] ?? 75.0);
        $slBuffer = (float)($config['sl_buffer'] ?? 15.0);
        $tp1 = (float)($config['tp1'] ?? 100.0);
        $tp2 = (float)($config['tp2'] ?? 150.0);
        $basketMode = $config['basket_mode'] ?? 'dynamic';
        $maxTradesPerDay = (int)($config['max_trades_per_day'] ?? 2);
        $startTime = $config['start_time'] ?? '09:30';
        $endTime = $config['end_time'] ?? '14:30';
        $fromDate = $config['from_date'] ?? null;
        $toDate = $config['to_date'] ?? null;

        // Resolve trading dates
        $dates = $this->resolveDates($mode, $fromDate, $toDate);

        $results = [
            'mode' => $mode,
            'config' => [
                'stock_threshold' => $stockThreshold,
                'option_threshold' => $optionThreshold,
                'sl_buffer' => $slBuffer,
                'tp1' => $tp1,
                'tp2' => $tp2,
                'basket_mode' => $basketMode,
                'max_trades_per_day' => $maxTradesPerDay,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'from_date' => $fromDate ?: ($dates[0] ?? null),
                'to_date' => $toDate ?: (end($dates) ?: null),
                'total_trading_days' => count($dates),
            ],
            'trades' => [],
            'stats' => [],
        ];

        $allTrades = [];
        $totalDays = count($dates);

        foreach ($dates as $idx => $date) {
            $dayTrades = $this->simulateDay(
                $date,
                $mode,
                $stockThreshold,
                $optionThreshold,
                $slBuffer,
                $tp1,
                $tp2,
                $basketMode,
                $maxTradesPerDay,
                $startTime,
                $endTime
            );

            foreach ($dayTrades as $tr) {
                $allTrades[] = $tr;
            }

            if ($progressCallback) {
                $progressCallback($date, $idx + 1, $totalDays, $dayTrades);
            }
        }

        $results['trades'] = $allTrades;
        $results['stats'] = $this->calculateStatistics($allTrades, count($dates));

        return $results;
    }

    /**
     * Resolve eligible trading dates based on selected mode.
     */
    public function resolveDates(string $mode, ?string $fromDate, ?string $toDate): array
    {
        $query = DB::table('ohlc_5m_quotes')
            ->where(function($q) {
                $q->where('instrument_key', 'like', '%Nifty 50%')
                  ->orWhere('trading_symbol', 'Nifty 50')
                  ->orWhere('trading_symbol', 'NIFTY');
            })
            ->where('instrument_type', 'INDEX')
            ->selectRaw('DATE(ts_at) as dt')
            ->distinct()
            ->orderBy('dt', 'asc');

        if ($fromDate) {
            $query->whereDate('ts_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('ts_at', '<=', $toDate);
        }

        // If mode requires options, restrict to dates available in option_chains_history
        if ($mode === 'confluence') {
            $optDates = DB::table('option_chains_history')
                ->where('trading_symbol', 'NIFTY')
                ->selectRaw('DISTINCT DATE(captured_at) as dt')
                ->pluck('dt')
                ->toArray();
            
            if (!empty($optDates)) {
                $query->whereIn(DB::raw('DATE(ts_at)'), $optDates);
            }
        }

        return $query->pluck('dt')->toArray();
    }

    /**
     * Simulate a single trading day.
     */
    public function simulateDay(
        string $date,
        string $mode,
        float $stockThreshold,
        float $optionThreshold,
        float $slBuffer,
        float $tp1,
        float $tp2,
        string $basketMode,
        int $maxTradesPerDay,
        string $startTime = '09:30',
        string $endTime = '14:30'
    ): array {
        // 1. Fetch 5m Stock Breadth data
        $stockReq = new Request(['date' => $date]);
        $stockRes = $this->stockController->getData($stockReq)->getData(true);

        if (empty($stockRes['success']) || empty($stockRes['breadth_by_time']) || empty($stockRes['nifty_candles'])) {
            return [];
        }

        $niftyCandles = $stockRes['nifty_candles'];
        $stockBreadth = $stockRes['breadth_by_time'];

        // Index candles by time_str for quick lookup
        $candlesByTime = [];
        foreach ($niftyCandles as $c) {
            $candlesByTime[$c['time_str']] = $c;
        }

        // 2. Fetch Options Breadth if mode requires options
        $optBreadth = [];
        if ($mode === 'confluence' || $mode === 'both') {
            try {
                $optReq = new Request(['date' => $date, 'range' => 5, 'basket_mode' => $basketMode]);
                $optRes = $this->optionsController->getData($optReq)->getData(true);
                if (!empty($optRes['success']) && !empty($optRes['breadth_by_time'])) {
                    $optBreadth = $optRes['breadth_by_time'];
                }
            } catch (\Throwable $e) {
                $optBreadth = [];
            }
        }

        $trades = [];
        $activeTrade = null;
        $dayTradeCount = 0;

        $timeKeys = array_keys($candlesByTime);
        sort($timeKeys);

        foreach ($timeKeys as $cIdx => $timeStr) {
            // Ignore candles before 09:20 (09:15 is the opening setup) and after 15:25
            if ($timeStr < '09:20' || $timeStr > '15:25') {
                continue;
            }

            $currentCandle = $candlesByTime[$timeStr];
            $currentStock = $stockBreadth[$timeStr] ?? null;
            $currentOpt = $optBreadth[$timeStr] ?? null;

            if (!$currentStock) {
                continue;
            }

            // ── CHECK EXISTING ACTIVE TRADE EXIT ──
            if ($activeTrade !== null) {
                $exitResult = $this->checkTradeExit($activeTrade, $currentCandle, $timeStr, $cIdx === count($timeKeys) - 1);
                if ($exitResult['exited']) {
                    $activeTrade = array_merge($activeTrade, $exitResult);
                    $trades[] = $activeTrade;
                    $activeTrade = null;
                }
                // If trade is still active, do not enter another trade concurrently
                if ($activeTrade !== null) {
                    continue;
                }
            }

            // Check if day trade limit reached
            if ($dayTradeCount >= $maxTradesPerDay) {
                continue;
            }

            // Skip new entries outside user-specified trading window (e.g. before 09:30 or after 14:30)
            if ($timeStr < $startTime || $timeStr > $endTime) {
                continue;
            }

            // ── EVALUATE ENTRY TRIGGERS ──
            $stockAdvPct = (float)($currentStock['green_count_pct'] ?? $currentStock['up_weight'] ?? 0);
            $stockDecPct = (float)($currentStock['red_count_pct'] ?? $currentStock['down_weight'] ?? 0);
            $stockAdvWeight = (float)($currentStock['green_weight'] ?? 0);
            $stockDecWeight = (float)($currentStock['red_weight'] ?? 0);

            $optBullPct = (float)($currentOpt['up_weight'] ?? $currentOpt['bull_pct'] ?? 50.0);
            $optBearPct = (float)($currentOpt['down_weight'] ?? $currentOpt['bear_pct'] ?? 50.0);

            $bullSignal = false;
            $bearSignal = false;
            $signalType = 'NONE';

            // Check triggers based on mode
            if ($mode === 'stocks') {
                if ($stockAdvPct >= $stockThreshold || $stockAdvWeight >= $stockThreshold) {
                    $bullSignal = true;
                    $signalType = 'STOCK_SURGE';
                } elseif ($stockDecPct >= $stockThreshold || $stockDecWeight >= $stockThreshold) {
                    $bearSignal = true;
                    $signalType = 'STOCK_SURGE';
                }
            } elseif ($mode === 'confluence') {
                if (empty($currentOpt)) {
                    continue; // Requires options data
                }
                if (($stockAdvPct >= $stockThreshold || $stockAdvWeight >= $stockThreshold) && $optBullPct >= $optionThreshold) {
                    $bullSignal = true;
                    $signalType = 'CONFLUENCE';
                } elseif (($stockDecPct >= $stockThreshold || $stockDecWeight >= $stockThreshold) && $optBearPct >= $optionThreshold) {
                    $bearSignal = true;
                    $signalType = 'CONFLUENCE';
                }
            } else { // 'both'
                $hasConfluence = !empty($currentOpt);
                if ($hasConfluence && ($stockAdvPct >= $stockThreshold || $stockAdvWeight >= $stockThreshold) && $optBullPct >= $optionThreshold) {
                    $bullSignal = true;
                    $signalType = 'CONFLUENCE';
                } elseif ($hasConfluence && ($stockDecPct >= $stockThreshold || $stockDecWeight >= $stockThreshold) && $optBearPct >= $optionThreshold) {
                    $bearSignal = true;
                    $signalType = 'CONFLUENCE';
                } elseif ($stockAdvPct >= $stockThreshold || $stockAdvWeight >= $stockThreshold) {
                    $bullSignal = true;
                    $signalType = 'STOCK_SURGE';
                } elseif ($stockDecPct >= $stockThreshold || $stockDecWeight >= $stockThreshold) {
                    $bearSignal = true;
                    $signalType = 'STOCK_SURGE';
                }
            }

            if (!$bullSignal && !$bearSignal) {
                continue;
            }

            // ── CREATE NEW TRADE ──
            $direction = $bullSignal ? 'BULL' : 'BEAR';
            $entrySpot = (float)$currentCandle['close'];
            $candleHigh = (float)$currentCandle['high'];
            $candleLow = (float)$currentCandle['low'];

            // SL Logic: Alarm candle Low - buffer (for Bull) or High + buffer (for Bear)
            if ($direction === 'BULL') {
                $sl = round($candleLow - $slBuffer, 2);
                $tp1Price = round($entrySpot + $tp1, 2);
                $tp2Price = round($entrySpot + $tp2, 2);
            } else {
                $sl = round($candleHigh + $slBuffer, 2);
                $tp1Price = round($entrySpot - $tp1, 2);
                $tp2Price = round($entrySpot - $tp2, 2);
            }

            $riskPts = round(abs($entrySpot - $sl), 2);
            if ($riskPts <= 2.0) {
                $riskPts = 20.0; // fallback minimum risk guard
                $sl = ($direction === 'BULL') ? $entrySpot - 20.0 : $entrySpot + 20.0;
            }

            $activeTrade = [
                'date' => $date,
                'direction' => $direction,
                'signal_type' => $signalType,
                'entry_time' => $timeStr,
                'entry_price' => $entrySpot,
                'candle_high' => $candleHigh,
                'candle_low' => $candleLow,
                'sl_price' => $sl,
                'risk_pts' => $riskPts,
                'tp1_price' => $tp1Price,
                'tp2_price' => $tp2Price,
                'tp1_target_pts' => $tp1,
                'tp2_target_pts' => $tp2,
                'stock_adv_pct' => $stockAdvPct,
                'stock_dec_pct' => $stockDecPct,
                'opt_bull_pct' => $optBullPct,
                'opt_bear_pct' => $optBearPct,
                'mfe_pts' => 0.0,
                'mae_pts' => 0.0,
                'candles_held' => 0,
            ];

            $dayTradeCount++;
        }

        // Close any trade still open at EOD (15:25)
        if ($activeTrade !== null) {
            $lastCandle = end($candlesByTime);
            $lastTime = $lastCandle['time_str'] ?? '15:25';
            $exitPrice = (float)($lastCandle['close'] ?? $activeTrade['entry_price']);
            
            $rawPts = ($activeTrade['direction'] === 'BULL') 
                ? ($exitPrice - $activeTrade['entry_price']) 
                : ($activeTrade['entry_price'] - $exitPrice);
            
            $outcome = ($rawPts > 0) ? 'EOD_PROFIT' : 'EOD_LOSS';

            $activeTrade = array_merge($activeTrade, [
                'exit_time' => $lastTime,
                'exit_price' => $exitPrice,
                'outcome' => $outcome,
                'net_pts' => round($rawPts, 2),
                'pnl_pts_tp1' => round($rawPts, 2),
                'pnl_pts_tp2' => round($rawPts, 2),
                'rr_realized' => round($rawPts / max(1.0, $activeTrade['risk_pts']), 2),
            ]);
            $trades[] = $activeTrade;
        }

        return $trades;
    }

    /**
     * Check if active trade hits SL, TP1, TP2, or EOD exit during a candle walk.
     */
    protected function checkTradeExit(array &$trade, array $candle, string $timeStr, bool $isLastCandle): array
    {
        $direction = $trade['direction'];
        $entry = $trade['entry_price'];
        $sl = $trade['sl_price'];
        $tp1 = $trade['tp1_price'];
        $tp2 = $trade['tp2_price'];
        $high = (float)$candle['high'];
        $low = (float)$candle['low'];
        $close = (float)$candle['close'];

        $trade['candles_held']++;

        // Update MFE (Max Favorable Excursion) & MAE (Max Adverse Excursion)
        if ($direction === 'BULL') {
            $fav = max(0.0, $high - $entry);
            $adv = max(0.0, $entry - $low);
        } else {
            $fav = max(0.0, $entry - $low);
            $adv = max(0.0, $high - $entry);
        }
        $trade['mfe_pts'] = max($trade['mfe_pts'], round($fav, 2));
        $trade['mae_pts'] = max($trade['mae_pts'], round($adv, 2));

        // 1. Check Stop Loss breach
        $slHit = false;
        if ($direction === 'BULL' && $low <= $sl) {
            $slHit = true;
        } elseif ($direction === 'BEAR' && $high >= $sl) {
            $slHit = true;
        }

        if ($slHit) {
            $netPts = -1.0 * $trade['risk_pts'];
            return [
                'exited' => true,
                'exit_time' => $timeStr,
                'exit_price' => $sl,
                'outcome' => 'SL_HIT',
                'net_pts' => round($netPts, 2),
                'pnl_pts_tp1' => round($netPts, 2),
                'pnl_pts_tp2' => round($netPts, 2),
                'rr_realized' => -1.0,
            ];
        }

        // 2. Check Target 2 breach (Full 150-pt runner target)
        $tp2Hit = false;
        if ($direction === 'BULL' && $high >= $tp2) {
            $tp2Hit = true;
        } elseif ($direction === 'BEAR' && $low <= $tp2) {
            $tp2Hit = true;
        }

        if ($tp2Hit) {
            // Half exited at TP1 (100 pts) and half at TP2 (150 pts) -> Average = 125 pts
            $avgPts = round(($trade['tp1_target_pts'] + $trade['tp2_target_pts']) / 2.0, 2);
            return [
                'exited' => true,
                'exit_time' => $timeStr,
                'exit_price' => $tp2,
                'outcome' => 'TP2_HIT',
                'net_pts' => $avgPts,
                'pnl_pts_tp1' => $trade['tp1_target_pts'],
                'pnl_pts_tp2' => $trade['tp2_target_pts'],
                'rr_realized' => round($avgPts / max(1.0, $trade['risk_pts']), 2),
            ];
        }

        // 3. Check Target 1 breach (100 pts)
        $tp1Hit = false;
        if ($direction === 'BULL' && $high >= $tp1) {
            $tp1Hit = true;
        } elseif ($direction === 'BEAR' && $low <= $tp1) {
            $tp1Hit = true;
        }

        // 4. EOD Exit (if past 15:20 or last candle)
        if ($timeStr >= '15:20' || $isLastCandle) {
            $rawPts = ($direction === 'BULL') ? ($close - $entry) : ($entry - $close);
            $outcome = $tp1Hit ? 'TP1_HIT' : (($rawPts > 0) ? 'EOD_PROFIT' : 'EOD_LOSS');
            $netPts = $tp1Hit ? $trade['tp1_target_pts'] : round($rawPts, 2);

            return [
                'exited' => true,
                'exit_time' => $timeStr,
                'exit_price' => $close,
                'outcome' => $outcome,
                'net_pts' => $netPts,
                'pnl_pts_tp1' => round($rawPts, 2),
                'pnl_pts_tp2' => round($rawPts, 2),
                'rr_realized' => round($netPts / max(1.0, $trade['risk_pts']), 2),
            ];
        }

        return ['exited' => false];
    }

    /**
     * Compute comprehensive statistics from executed trades.
     */
    public function calculateStatistics(array $trades, int $totalDays): array
    {
        $totalTrades = count($trades);
        if ($totalTrades === 0) {
            return [
                'total_trading_days' => $totalDays,
                'total_trades' => 0,
                'win_rate_pct' => 0.0,
                'net_pnl_pts' => 0.0,
                'profit_factor' => 0.0,
            ];
        }

        $wins = 0;
        $losses = 0;
        $tp1Hits = 0;
        $tp2Hits = 0;
        $slHits = 0;
        $eodProfits = 0;
        $eodLosses = 0;

        $grossWins = 0.0;
        $grossLosses = 0.0;
        $netPnl = 0.0;

        $bullTrades = 0;
        $bullWins = 0;
        $bearTrades = 0;
        $bearWins = 0;

        $equityCurve = [0.0];
        $peak = 0.0;
        $maxDrawdown = 0.0;

        $curStreakWins = 0;
        $curStreakLosses = 0;
        $maxWinStreak = 0;
        $maxLossStreak = 0;

        $durations = [];
        $rrRatios = [];

        foreach ($trades as $t) {
            $pts = (float)$t['net_pts'];
            $netPnl += $pts;
            $equityCurve[] = round($netPnl, 2);

            // Peak & Drawdown
            if ($netPnl > $peak) {
                $peak = $netPnl;
            }
            $dd = $peak - $netPnl;
            if ($dd > $maxDrawdown) {
                $maxDrawdown = $dd;
            }

            if ($pts > 0) {
                $wins++;
                $grossWins += $pts;
                $curStreakWins++;
                $curStreakLosses = 0;
                $maxWinStreak = max($maxWinStreak, $curStreakWins);

                if ($t['direction'] === 'BULL') $bullWins++;
                if ($t['direction'] === 'BEAR') $bearWins++;
            } else {
                $losses++;
                $grossLosses += abs($pts);
                $curStreakLosses++;
                $curStreakWins = 0;
                $maxLossStreak = max($maxLossStreak, $curStreakLosses);
            }

            if ($t['direction'] === 'BULL') $bullTrades++;
            if ($t['direction'] === 'BEAR') $bearTrades++;

            // Outcome breakdown
            switch ($t['outcome']) {
                case 'TP2_HIT':
                    $tp2Hits++;
                    $tp1Hits++;
                    break;
                case 'TP1_HIT':
                    $tp1Hits++;
                    break;
                case 'SL_HIT':
                    $slHits++;
                    break;
                case 'EOD_PROFIT':
                    $eodProfits++;
                    break;
                case 'EOD_LOSS':
                    $eodLosses++;
                    break;
            }

            if (isset($t['candles_held'])) {
                $durations[] = $t['candles_held'] * 5; // minutes
            }
            if (isset($t['rr_realized'])) {
                $rrRatios[] = (float)$t['rr_realized'];
            }
        }

        $winRate = ($totalTrades > 0) ? round(($wins / $totalTrades) * 100, 1) : 0.0;
        $profitFactor = ($grossLosses > 0) ? round($grossWins / $grossLosses, 2) : ($grossWins > 0 ? 99.9 : 0.0);
        $avgWin = ($wins > 0) ? round($grossWins / $wins, 1) : 0.0;
        $avgLoss = ($losses > 0) ? round($grossLosses / $losses, 1) : 0.0;
        $avgTradePts = round($netPnl / $totalTrades, 1);
        $avgDuration = !empty($durations) ? round(array_sum($durations) / count($durations), 1) : 0;
        $avgRR = !empty($rrRatios) ? round(array_sum($rrRatios) / count($rrRatios), 2) : 0;

        return [
            'total_trading_days' => $totalDays,
            'total_trades' => $totalTrades,
            'wins' => $wins,
            'losses' => $losses,
            'win_rate_pct' => $winRate,
            'net_pnl_pts' => round($netPnl, 1),
            'gross_wins_pts' => round($grossWins, 1),
            'gross_losses_pts' => round($grossLosses, 1),
            'profit_factor' => $profitFactor,
            'avg_trade_pts' => $avgTradePts,
            'avg_win_pts' => $avgWin,
            'avg_loss_pts' => $avgLoss,
            'max_drawdown_pts' => round($maxDrawdown, 1),
            'avg_rr_ratio' => $avgRR,
            'avg_duration_minutes' => $avgDuration,
            'max_win_streak' => $maxWinStreak,
            'max_loss_streak' => $maxLossStreak,
            'outcomes' => [
                'tp2_hits' => $tp2Hits,
                'tp1_hits' => $tp1Hits,
                'sl_hits' => $slHits,
                'eod_profits' => $eodProfits,
                'eod_losses' => $eodLosses,
            ],
            'direction_breakdown' => [
                'bull' => [
                    'trades' => $bullTrades,
                    'wins' => $bullWins,
                    'win_rate_pct' => ($bullTrades > 0) ? round(($bullWins / $bullTrades) * 100, 1) : 0.0,
                ],
                'bear' => [
                    'trades' => $bearTrades,
                    'wins' => $bearWins,
                    'win_rate_pct' => ($bearTrades > 0) ? round(($bearWins / $bearTrades) * 100, 1) : 0.0,
                ],
            ],
        ];
    }
}
