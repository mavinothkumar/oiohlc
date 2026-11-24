<?php

namespace App\Services\Backtest;

use App\Http\Controllers\NiftyWeightageMonitorController;
use App\Http\Controllers\OptionsBreadthMonitorController;
use App\Http\Controllers\TrendingOiController;
use App\Models\AutoTradeV2Day;
use App\Models\AutoTradeV2Trade;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AutoTradingV2Engine
{
    protected NiftyWeightageMonitorController $stockController;
    protected OptionsBreadthMonitorController $optionsController;
    protected TrendingOiController $trendingOiController;

    public function __construct(
        NiftyWeightageMonitorController $stockController,
        OptionsBreadthMonitorController $optionsController,
        TrendingOiController $trendingOiController
    ) {
        $this->stockController = $stockController;
        $this->optionsController = $optionsController;
        $this->trendingOiController = $trendingOiController;
    }

    /**
     * Run the automated trading engine for multiple dates.
     */
    public function runDays(array $dates, array $customConfig = [], ?callable $progressCallback = null): array
    {
        $config = array_merge([
            'startTime'             => '09:35',
            'endTime'               => '14:45',
            'timeFilterEnabled'     => true,
            'stockThreshold'        => 80.0,
            'optionThreshold'       => 70.0,
            'oiFilterEnabled'       => true,
            'oiVetoThreshold'       => 25.0,
            'oiSqueezeThreshold'    => 15.0,
            'stagnationEnabled'     => true,
            'stagnationMins'        => 45,
            'stagnationAction'      => 'trail_1to1', // trail_1to1 or square_off
            'stagnationTrailBuffer' => 5.0,
            'strategyMode'          => 'auto',       // auto, force_sell, force_buy, combo
            'entryLots'             => 3,
            'lotSize'               => 65,
            't1Rr'                  => 1.2,
            't2Rr'                  => 2.2,
            't3Rr'                  => 3.2,
        ], $customConfig);

        $totalDays = count($dates);
        $processedCount = 0;
        $allTrades = [];
        $daySummaries = [];

        foreach ($dates as $idx => $date) {
            $dayResult = $this->simulateDay($date, $config);

            if (!empty($dayResult['summary'])) {
                $this->saveDayToDatabase($date, $dayResult['summary'], $dayResult['trades']);
                $daySummaries[] = $dayResult['summary'];
                $allTrades = array_merge($allTrades, $dayResult['trades']);
            }

            $processedCount++;
            if ($progressCallback) {
                $progressCallback($date, $processedCount, $totalDays, $dayResult);
            }
        }

        return [
            'total_days'    => $totalDays,
            'total_trades'  => count($allTrades),
            'day_summaries' => $daySummaries,
            'trades'        => $allTrades,
        ];
    }

    /**
     * Simulate a single trading day session.
     */
    public function simulateDay(string $date, array $config): array
    {
        // 1. Fetch 5M Stock Breadth & Nifty Candles
        $stockReq = new Request(['date' => $date]);
        $stockRes = null;
        try {
            $stockRes = $this->stockController->getData($stockReq)->getData(true);
        } catch (\Throwable $e) {
            Log::warning("AutoTradingV2Engine: Failed stock breadth for {$date}: " . $e->getMessage());
            return ['summary' => null, 'trades' => []];
        }

        if (empty($stockRes['success']) || empty($stockRes['breadth_by_time']) || empty($stockRes['nifty_candles'])) {
            return ['summary' => null, 'trades' => []];
        }

        $rawCandles = $stockRes['nifty_candles'];
        $stockBreadth = $stockRes['breadth_by_time'];

        // 2. Fetch Options Breadth (Order Flow)
        $optBreadth = [];
        try {
            $optReq = new Request(['date' => $date, 'range' => 5]);
            $optRes = $this->optionsController->getData($optReq)->getData(true);
            if (!empty($optRes['success']) && !empty($optRes['breadth_by_time'])) {
                $optBreadth = $optRes['breadth_by_time'];
            }
        } catch (\Throwable $e) {
            $optBreadth = [];
        }

        // 3. Fetch Trending OI Data
        $trendingOiSnapshots = [];
        try {
            $expiry = null;

            // For historical sessions, resolve the active expiry recorded in option_chains_history for that day
            if (Schema::hasTable('option_chains_history')) {
                $pastExpiries = DB::table('option_chains_history')
                    ->where('trading_symbol', 'NIFTY')
                    ->where('captured_at', '>=', "{$date} 09:00:00")
                    ->where('captured_at', '<=', "{$date} 15:35:00")
                    ->distinct()
                    ->orderBy('expiry')
                    ->pluck('expiry')
                    ->toArray();

                foreach ($pastExpiries as $exp) {
                    if ($exp >= $date) {
                        $expiry = $exp;
                        break;
                    }
                }
                if (!$expiry && !empty($pastExpiries)) {
                    $expiry = $pastExpiries[0];
                }
            }

            // Fallback for live / upcoming sessions
            if (!$expiry && Schema::hasTable('nse_expiries')) {
                $expiry = DB::table('nse_expiries')
                    ->where('trading_symbol', 'NIFTY')
                    ->where('instrument_type', 'OPT')
                    ->where('expiry_date', '>=', $date)
                    ->orderBy('expiry_date')
                    ->value('expiry_date');
            }

            if ($expiry) {
                $toiReq = new Request([
                    'date'   => $date,
                    'expiry' => $expiry,
                    'mode'   => 'historical',
                ]);
                $toiRes = $this->trendingOiController->getData($toiReq);
                $toiData = is_array($toiRes) ? $toiRes : ($toiRes instanceof \Illuminate\Http\JsonResponse ? $toiRes->getData(true) : null);
                if (!empty($toiData['chart']['labels'])) {
                    $labels = $toiData['chart']['labels'];
                    $callOi = $toiData['chart']['call_oi'] ?? [];
                    $putOi = $toiData['chart']['put_oi'] ?? [];
                    $diffOi = $toiData['chart']['diff_oi'] ?? [];

                    foreach ($labels as $lIdx => $lTime) {
                        $cVal = (float)($callOi[$lIdx] ?? 0);
                        $pVal = (float)($putOi[$lIdx] ?? 0);
                        $dVal = (float)($diffOi[$lIdx] ?? 0);
                        $tot = $cVal + $pVal;
                        $diffPct = $tot > 0 ? (($pVal - $cVal) / $tot) * 100 : 0;
                        $trendingOiSnapshots[$lTime] = [
                            'diffPct' => round($diffPct, 1),
                            'callOi'  => $cVal,
                            'putOi'   => $pVal,
                            'diffOi'  => $dVal,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("AutoTradingV2Engine: Trending OI error for {$date}: " . $e->getMessage());
            $trendingOiSnapshots = [];
        }

        // Session Execution Loop
        $sessionTrades = [];
        $currentTrade = null;
        $telemetryLogs = [];

        $startTime = $config['startTime'] ?? '09:35';
        $endTime = $config['endTime'] ?? '14:45';
        $timeFilterActive = !empty($config['timeFilterEnabled']);
        $sUpTh = (float)($config['stockThreshold'] ?? 80.0);
        $sDownTh = (float)($config['stockThreshold'] ?? 80.0);
        $oUpTh = (float)($config['optionThreshold'] ?? 70.0);
        $oDownTh = (float)($config['optionThreshold'] ?? 70.0);
        $entryLots = (int)($config['entryLots'] ?? 3);
        $lotSize = (int)($config['lotSize'] ?? 65);
        $t1Rr = (float)($config['t1Rr'] ?? 1.2);
        $t2Rr = (float)($config['t2Rr'] ?? 2.2);
        $t3Rr = (float)($config['t3Rr'] ?? 3.2);

        $niftyOpen = null;
        $niftyHigh = -999999;
        $niftyLow = 999999;
        $niftyClose = null;

        $count = count($rawCandles);
        for ($i = 0; $i < $count; $i++) {
            $candle = $rawCandles[$i];
            $tStr = $candle['time_str'];
            $cOpen = (float)$candle['open'];
            $cHigh = (float)$candle['high'];
            $cLow = (float)$candle['low'];
            $cClose = (float)$candle['close'];

            if ($i === 0) $niftyOpen = $cOpen;
            $niftyHigh = max($niftyHigh, $cHigh);
            $niftyLow = min($niftyLow, $cLow);
            $niftyClose = $cClose;

            $b = $stockBreadth[$tStr] ?? null;
            $oB = $optBreadth[$tStr] ?? null;
            $oiSnap = $this->resolveOiSnapshot($tStr, $trendingOiSnapshots);

            $stkBullCount = $b ? (float)($b['green_count_pct'] ?? 0) : 0;
            $stkBearCount = $b ? (float)($b['red_count_pct'] ?? 0) : 0;
            $stkBullWt = $b ? (float)($b['green_weight'] ?? 0) : 0;
            $stkBearWt = $b ? (float)($b['red_weight'] ?? 0) : 0;

            $optUpWt = $oB ? (float)($oB['up_weight'] ?? 0) : 0;
            $optDownWt = $oB ? (float)($oB['down_weight'] ?? 0) : 0;

            $oiDiffPct = $oiSnap ? $oiSnap['diffPct'] : 0.0;

            $candleState = 'STANDBY';
            $verdictBadge = '';
            $diagnosticReason = '';

            // ── 1. ACTIVE TRADE PROGRESSION ──
            if ($currentTrade !== null) {
                $isBull = ($currentTrade['direction'] === 'BULL');

                // Check Targets
                if (!$currentTrade['t1_hit']) {
                    if (($isBull && $cHigh >= $currentTrade['t1_price']) || (!$isBull && $cLow <= $currentTrade['t1_price'])) {
                        $currentTrade['t1_hit'] = true;
                        if ($currentTrade['open_lots'] > 1) {
                            $t1Pts = abs($currentTrade['t1_price'] - $currentTrade['entry_spot']) * 0.55;
                            $t1Rs = round(1 * $t1Pts * $currentTrade['lot_size']);
                            $currentTrade['booked_lots'] += 1;
                            $currentTrade['open_lots'] -= 1;
                            $currentTrade['booked_pnl_rupees'] += $t1Rs;
                        }
                        $currentTrade['current_sl'] = $currentTrade['entry_spot'];
                        $candleState = 'TARGET_HIT';
                        $verdictBadge = '🎯 TARGET 1 HIT';
                        $diagnosticReason = "Target 1 hit at {$currentTrade['t1_price']}. 1 Lot booked. SL shifted to Cost Breakeven.";
                    }
                }

                if ($currentTrade['t1_hit'] && !$currentTrade['t2_hit'] && $candleState !== 'TARGET_HIT') {
                    if (($isBull && $cHigh >= $currentTrade['t2_price']) || (!$isBull && $cLow <= $currentTrade['t2_price'])) {
                        $currentTrade['t2_hit'] = true;
                        if ($currentTrade['open_lots'] > 1) {
                            $t2Pts = abs($currentTrade['t2_price'] - $currentTrade['entry_spot']) * 0.60;
                            $t2Rs = round(1 * $t2Pts * $currentTrade['lot_size']);
                            $currentTrade['booked_lots'] += 1;
                            $currentTrade['open_lots'] -= 1;
                            $currentTrade['booked_pnl_rupees'] += $t2Rs;
                        }
                        $currentTrade['current_sl'] = $currentTrade['t1_price'];
                        $candleState = 'TARGET_HIT';
                        $verdictBadge = '🎯 TARGET 2 HIT';
                        $diagnosticReason = "Target 2 hit at {$currentTrade['t2_price']}. 1 Lot booked. SL locked at Target 1.";
                    }
                }

                if ($currentTrade['t2_hit'] && !$currentTrade['t3_hit'] && $candleState !== 'TARGET_HIT') {
                    if (($isBull && $cHigh >= $currentTrade['t3_price']) || (!$isBull && $cLow <= $currentTrade['t3_price'])) {
                        $currentTrade['t3_hit'] = true;
                        $t3Pts = abs($currentTrade['t3_price'] - $currentTrade['entry_spot']) * 0.65;
                        $t3Rs = round($currentTrade['open_lots'] * $t3Pts * $currentTrade['lot_size']);
                        $currentTrade['booked_pnl_rupees'] += $t3Rs;
                        $currentTrade['booked_lots'] += $currentTrade['open_lots'];
                        $currentTrade['open_lots'] = 0;
                        $currentTrade['pnl_rupees'] = $currentTrade['booked_pnl_rupees'];
                        $currentTrade['pnl_points'] = round($currentTrade['pnl_rupees'] / ($currentTrade['entry_lots'] * $currentTrade['lot_size']), 1);
                        $currentTrade['exit_time'] = $tStr;
                        $currentTrade['exit_spot'] = $currentTrade['t3_price'];
                        $currentTrade['exit_reason'] = 'Target 3 Hit! Complete profit booked.';

                        $candleState = 'TARGET_HIT';
                        $verdictBadge = '🎯 T3 FULL PROFIT';
                        $diagnosticReason = "Target 3 hit at {$currentTrade['t3_price']}. Complete runner closed (+₹" . number_format($currentTrade['pnl_rupees']) . ").";

                        $sessionTrades[] = $currentTrade;
                        $currentTrade = null;

                        $telemetryLogs[] = [
                            'time'       => $tStr,
                            'spot'       => $cClose,
                            'state'      => $candleState,
                            'badge'      => $verdictBadge,
                            'diagnostic' => $diagnosticReason,
                        ];
                        continue;
                    }
                }

                // Track Peak Spot in Trade
                if ($isBull) {
                    $currentTrade['peak_spot'] = max($currentTrade['peak_spot'] ?? $cHigh, $cHigh);
                } else {
                    $currentTrade['peak_spot'] = min($currentTrade['peak_spot'] ?? $cLow, $cLow);
                }

                $elapsedCandles = $i - $currentTrade['entry_index'];
                $elapsedMins = $elapsedCandles * 5;
                $stagLimit = (int)($config['stagnationMins'] ?? 45);
                $stagBuffer = (float)($config['stagnationTrailBuffer'] ?? 5.0);

                $exitTriggered = false;
                $exitReason = null;
                $exitPrice = $cClose;

                // F7: Stagnation 45M Time-Stop
                if (!empty($config['stagnationEnabled']) && $elapsedMins >= $stagLimit && !$currentTrade['t1_hit']) {
                    if ($config['stagnationAction'] === 'square_off') {
                        $exitTriggered = true;
                        $exitPrice = $cClose;
                        $exitReason = "Stagnation Time-Stop Exit ({$elapsedMins}m Flat)";
                    } else {
                        $currentTrade['is_stagnation_trailed'] = true;
                        $dynamicSl = $isBull
                            ? round($currentTrade['peak_spot'] - $stagBuffer, 1)
                            : round($currentTrade['peak_spot'] + $stagBuffer, 1);

                        if ($isBull && $dynamicSl > $currentTrade['current_sl']) {
                            $currentTrade['current_sl'] = $dynamicSl;
                        } elseif (!$isBull && $dynamicSl < $currentTrade['current_sl']) {
                            $currentTrade['current_sl'] = $dynamicSl;
                        }
                    }
                }

                // F1: Trailed SL or Signal Candle SL Hit
                if (!$exitTriggered) {
                    if (($isBull && $cLow <= $currentTrade['current_sl']) || (!$isBull && $cHigh >= $currentTrade['current_sl'])) {
                        $exitTriggered = true;
                        $exitPrice = $currentTrade['current_sl'];
                        $exitReason = $currentTrade['is_stagnation_trailed']
                            ? "Stagnation 1:1 Trailed SL Hit ({$stagBuffer}pt Buffer)"
                            : ($currentTrade['t2_hit'] ? 'Trailed SL at T1 Hit (Profit Secured)' : ($currentTrade['t1_hit'] ? 'Trailed SL at Cost Hit (Breakeven Locked)' : 'Signal Candle Base SL Hit'));
                    }
                }

                // End of Session Square-Off at 15:20
                if (!$exitTriggered && $i >= $count - 2) {
                    $exitTriggered = true;
                    $exitPrice = $cClose;
                    $exitReason = 'Intraday 15:20 Market Close Square-Off';
                }

                if ($exitTriggered) {
                    if ($currentTrade['open_lots'] > 0) {
                        $ptsGain = ($isBull ? ($exitPrice - $currentTrade['entry_spot']) : ($currentTrade['entry_spot'] - $exitPrice)) * 0.55;
                        $remGainRs = round($currentTrade['open_lots'] * $ptsGain * $currentTrade['lot_size']);
                        $currentTrade['booked_pnl_rupees'] += $remGainRs;
                        $currentTrade['booked_lots'] += $currentTrade['open_lots'];
                        $currentTrade['open_lots'] = 0;
                        $currentTrade['pnl_rupees'] = $currentTrade['booked_pnl_rupees'];
                        $currentTrade['pnl_points'] = round($currentTrade['pnl_rupees'] / ($currentTrade['entry_lots'] * $currentTrade['lot_size']), 1);
                    }
                    $currentTrade['exit_time'] = $tStr;
                    $currentTrade['exit_spot'] = $exitPrice;
                    $currentTrade['exit_reason'] = $exitReason;

                    $candleState = 'STOP_LOSS';
                    $isStag = str_contains($exitReason, 'Stagnation');
                    $verdictBadge = $isStag ? '⏳ STAGNATION EXIT' : ($currentTrade['t1_hit'] ? '🛡️ TRAILED SL EXIT' : '🔴 STOPPED OUT');
                    $diagnosticReason = "{$exitReason} at {$exitPrice}. P&L: " . ($currentTrade['pnl_points'] >= 0 ? '+' : '') . "{$currentTrade['pnl_points']} pts (₹" . number_format($currentTrade['pnl_rupees']) . ").";

                    $sessionTrades[] = $currentTrade;
                    $currentTrade = null;

                    $telemetryLogs[] = [
                        'time'       => $tStr,
                        'spot'       => $cClose,
                        'state'      => $candleState,
                        'badge'      => $verdictBadge,
                        'diagnostic' => $diagnosticReason,
                    ];
                    continue;
                }
            } elseif ($i < $count - 1) {
                // ── 2. SCAN FOR ENTRY TRIGGERS ON CANDLE i ──
                $sBull = ($stkBullCount >= $sUpTh && $stkBullWt >= $sUpTh);
                $sBear = ($stkBearCount >= $sDownTh && $stkBearWt >= $sDownTh);

                $oBull = ($optUpWt >= $oUpTh);
                $oBear = ($optDownWt >= $oDownTh);

                $triggeredDirection = null;
                $isConf = false;

                if ($sBull && $oBull) { $triggeredDirection = 'BULL'; $isConf = true; }
                elseif ($sBear && $oBear) { $triggeredDirection = 'BEAR'; $isConf = true; }
                elseif ($sBull && !$oBear) { $triggeredDirection = 'BULL'; $isConf = false; }
                elseif ($sBear && !$oBull) { $triggeredDirection = 'BEAR'; $isConf = false; }

                if ($triggeredDirection !== null) {
                    $setupType = 'STANDARD';
                    $setupBadge = '🎯 STANDARD';
                    $setupReason = 'Breadth confirmation trigger';
                    $isVetoed = false;
                    $vetoReason = '';

                    $isPreOpen = $timeFilterActive && ($tStr < $startTime);
                    $isPostCutoff = $timeFilterActive && ($tStr > $endTime);

                    if ($isPreOpen || $isPostCutoff) {
                        $candleState = 'TIME_FILTER';
                        $timeLabel = $isPreOpen ? "Pre-{$startTime} Opening Noise Window" : "Post-{$endTime} Session Cutoff Window";
                        $verdictBadge = $isPreOpen ? '⏰ PRE-09:35 NOISE' : '🛑 POST-14:45 CUTOFF';
                        $diagnosticReason = "⏰ {$triggeredDirection} signal formed at {$tStr}, but blocked by {$timeLabel} filter. No trading allowed outside {$startTime}–{$endTime}.";
                    } else {
                        $vetoTh = (float)($config['oiVetoThreshold'] ?? 25.0);
                        $sqzTh = (float)($config['oiSqueezeThreshold'] ?? 15.0);

                        if (!empty($config['oiFilterEnabled']) && $oiSnap !== null) {
                            if ($triggeredDirection === 'BULL') {
                                if ($oiDiffPct <= -$vetoTh) {
                                    $isVetoed = true;
                                    $vetoReason = "Blocked by " . abs($oiDiffPct) . "% Call Writing Wall";
                                } elseif (abs($oiDiffPct) <= $sqzTh) {
                                    $setupType = 'SQUEEZE_BREAKOUT';
                                    $setupBadge = '🚀 SQUEEZE BREAKOUT';
                                    $setupReason = "Tight OI Equilibrium ({$oiDiffPct}%) ➔ Breakout Expansion";
                                } elseif ($oiDiffPct >= 20.0) {
                                    $setupType = 'GRADE_A_CONFLUENCE';
                                    $setupBadge = '🌟 GRADE A+ CONFLUENCE';
                                    $setupReason = "Breadth + Put Support Floor (+{$oiDiffPct}%) Aligned";
                                }
                            } else {
                                if ($oiDiffPct >= $vetoTh) {
                                    $isVetoed = true;
                                    $vetoReason = "Blocked by +{$oiDiffPct}% Put Writing Floor";
                                } elseif (abs($oiDiffPct) <= $sqzTh) {
                                    $setupType = 'SQUEEZE_BREAKOUT';
                                    $setupBadge = '🚀 SQUEEZE BREAKOUT';
                                    $setupReason = "Tight OI Equilibrium ({$oiDiffPct}%) ➔ Breakdown Expansion";
                                } elseif ($oiDiffPct <= -20.0) {
                                    $setupType = 'GRADE_A_CONFLUENCE';
                                    $setupBadge = '🌟 GRADE A+ CONFLUENCE';
                                    $setupReason = "Breadth + Call Writing Ceiling ({$oiDiffPct}%) Aligned";
                                }
                            }
                        }

                        if ($isVetoed) {
                            $candleState = 'VETOED';
                            $verdictBadge = '⛔ VETOED TRAP';
                            $diagnosticReason = "⛔ {$triggeredDirection} impulse formed, but VETOED by {$vetoReason}. Capital protected from false breakout trap!";
                        } else {
                            // Entry executes on OPEN of next candle (i + 1)
                            $nextCandle = $rawCandles[$i + 1];
                            $entrySpot = (float)$nextCandle['open'];
                            $atmStrike = round($entrySpot / 100) * 100;
                            $baseSl = ($triggeredDirection === 'BULL') ? $cLow : $cHigh;
                            $riskPts = max(abs($entrySpot - $baseSl), 25.0);

                            $t1 = ($triggeredDirection === 'BULL') ? round($entrySpot + ($riskPts * $t1Rr), 1) : round($entrySpot - ($riskPts * $t1Rr), 1);
                            $t2 = ($triggeredDirection === 'BULL') ? round($entrySpot + ($riskPts * $t2Rr), 1) : round($entrySpot - ($riskPts * $t2Rr), 1);
                            $t3 = ($triggeredDirection === 'BULL') ? round($entrySpot + ($riskPts * $t3Rr), 1) : round($entrySpot - ($riskPts * $t3Rr), 1);

                            $optType = ($triggeredDirection === 'BULL') ? 'PE' : 'CE';
                            $action = 'SELL';
                            $strike = ($triggeredDirection === 'BULL') ? $atmStrike - 100 : $atmStrike + 100;
                            $instrument = "NIFTY {$strike} {$optType}";

                            $tradeUid = "T_{$date}_" . str_replace(':', '', $nextCandle['time_str']) . "_{$triggeredDirection}";

                            $currentTrade = [
                                'trade_uid'             => $tradeUid,
                                'date'                  => $date,
                                'direction'             => $triggeredDirection,
                                'strategy_mode'         => $config['strategyMode'] ?? 'auto',
                                'action'                => $action,
                                'instrument'            => $instrument,
                                'strike'                => $strike,
                                'opt_type'              => $optType,
                                'entry_time'            => $nextCandle['time_str'],
                                'entry_index'           => $i + 1,
                                'entry_spot'            => $entrySpot,
                                'entry_opt_price'       => 95.0,
                                'entry_lots'            => $entryLots,
                                'lot_size'              => $lotSize,
                                'open_lots'             => $entryLots,
                                'booked_lots'           => 0,
                                'booked_pnl_rupees'     => 0,
                                'base_sl'               => $baseSl,
                                'current_sl'            => $baseSl,
                                't1_price'              => $t1,
                                't2_price'              => $t2,
                                't3_price'              => $t3,
                                't1_hit'                => false,
                                't2_hit'                => false,
                                't3_hit'                => false,
                                'exit_time'             => null,
                                'exit_spot'             => null,
                                'exit_opt_price'        => null,
                                'exit_reason'           => null,
                                'pnl_points'            => 0,
                                'pnl_rupees'            => 0,
                                'mfe_points'            => 0,
                                'mae_points'            => 0,
                                'peak_spot'             => $entrySpot,
                                'setup_type'            => $setupType,
                                'entry_reason'          => $setupReason,
                                'is_stagnation_trailed' => false,
                            ];

                            $candleState = 'TRADE_ENTRY';
                            $verdictBadge = $setupBadge;
                            $diagnosticReason = "{$setupReason}. Entered {$action} {$strike} {$optType} ({$entryLots}L) @ {$entrySpot}. T1: {$t1}, SL: {$baseSl}.";
                        }
                    }
                } else {
                    if ($i === 0) {
                        $candleState = 'MARKET_OPEN';
                        $verdictBadge = '⚪ MARKET OPEN';
                        $diagnosticReason = "Market open bar (09:15). Establishing baseline S/R levels.";
                    } elseif ($timeFilterActive && $tStr < $startTime) {
                        $candleState = 'STANDBY';
                        $verdictBadge = '⏰ PRE-09:35 NOISE';
                        $diagnosticReason = "Pre-{$startTime} Opening Window: Standby mode during initial 15-20 min noise.";
                    } elseif ($timeFilterActive && $tStr > $endTime) {
                        $candleState = 'STANDBY';
                        $verdictBadge = '🛑 POST-14:45 CUTOFF';
                        $diagnosticReason = "Post-{$endTime} Session Cutoff: Late afternoon session closed for new entries.";
                    } elseif ($stkBullCount >= 70 || $stkBearCount >= 70) {
                        $candleState = 'NEAR_MISS';
                        $verdictBadge = '🟡 NEAR-MISS (70%+)';
                        $diagnosticReason = "Sub-threshold impulse: Bull {$stkBullCount}% vs Bear {$stkBearCount}%. Did not cross 80% threshold.";
                    } else {
                        $candleState = 'STANDBY';
                        $verdictBadge = '⚪ STANDBY (RANGE)';
                        $diagnosticReason = "Market Equilibrium / Range: No edge detected.";
                    }
                }
            }

            $telemetryLogs[] = [
                'time'       => $tStr,
                'spot'       => $cClose,
                'state'      => $candleState,
                'badge'      => $verdictBadge,
                'diagnostic' => $diagnosticReason,
            ];
        }

        // Compile Daily Session Rollup
        $totalTrades = count($sessionTrades);
        $winTrades = 0;
        $lossTrades = 0;
        $netPts = 0.0;
        $netRs = 0.0;
        $stagnationCount = 0;

        foreach ($sessionTrades as &$tr) {
            $netPts += $tr['pnl_points'];
            $netRs += $tr['pnl_rupees'];
            if ($tr['pnl_rupees'] > 0) $winTrades++;
            elseif ($tr['pnl_rupees'] < 0) $lossTrades++;
            if (!empty($tr['is_stagnation_trailed'])) $stagnationCount++;
            $tr['telemetry_json'] = $telemetryLogs;
        }

        $dayStatus = 'NO_TRADE';
        if ($totalTrades > 0) {
            if ($netRs > 500) $dayStatus = 'WIN';
            elseif ($netRs < -500) $dayStatus = 'LOSS';
            else $dayStatus = 'SCRATCH';
        }

        $summaryNotes = $totalTrades > 0
            ? "{$totalTrades} trade(s) executed: {$winTrades} Win, {$lossTrades} Loss. Net: " . ($netPts >= 0 ? '+' : '') . "{$netPts} pts (₹" . number_format($netRs) . ")."
            : "No trades taken: market remained in standby or signals vetoed by opposing walls.";

        $daySummary = [
            'date'                   => $date,
            'nifty_open'             => $niftyOpen,
            'nifty_high'             => ($niftyHigh > -900000) ? $niftyHigh : null,
            'nifty_low'              => ($niftyLow < 900000) ? $niftyLow : null,
            'nifty_close'            => $niftyClose,
            'total_trades'           => $totalTrades,
            'win_trades'             => $winTrades,
            'loss_trades'            => $lossTrades,
            'net_points'             => round($netPts, 2),
            'net_pnl_rupees'         => round($netRs, 2),
            'mfe_points'             => 0,
            'mae_points'             => 0,
            'stagnation_exits_count' => $stagnationCount,
            'day_status'             => $dayStatus,
            'summary_reason'         => $summaryNotes,
        ];

        return [
            'summary' => $daySummary,
            'trades'  => $sessionTrades,
        ];
    }

    /**
     * Resolve nearest Trending OI snapshot for a given 5m timestamp.
     */
    protected function resolveOiSnapshot(string $timeStr, array $snapshots): ?array
    {
        if (empty($snapshots)) return null;
        if (isset($snapshots[$timeStr])) return $snapshots[$timeStr];

        $keys = array_keys($snapshots);
        $found = null;
        foreach ($keys as $k) {
            if ($k <= $timeStr) {
                $found = $snapshots[$k];
            } else {
                break;
            }
        }
        return $found ?: reset($snapshots);
    }

    /**
     * Idempotently save or update session and trade records in the database.
     */
    public function saveDayToDatabase(string $date, array $daySummary, array $dayTrades): void
    {
        // 1. Update or create daily session summary
        AutoTradeV2Day::updateOrCreate(
            ['date' => $date],
            $daySummary
        );

        // 2. Remove any obsolete trades for this date not present in current calculation
        $currentUids = array_column($dayTrades, 'trade_uid');
        AutoTradeV2Trade::where('date', $date)
            ->whereNotIn('trade_uid', $currentUids)
            ->delete();

        // 3. Update or create each individual trade
        foreach ($dayTrades as $tr) {
            $uid = $tr['trade_uid'];
            AutoTradeV2Trade::updateOrCreate(
                ['trade_uid' => $uid],
                [
                    'date'                  => $tr['date'],
                    'direction'             => $tr['direction'],
                    'strategy_mode'         => $tr['strategy_mode'],
                    'action'                => $tr['action'],
                    'instrument'            => $tr['instrument'],
                    'strike'                => $tr['strike'],
                    'opt_type'              => $tr['opt_type'],
                    'entry_time'            => $tr['entry_time'],
                    'entry_spot'            => $tr['entry_spot'],
                    'entry_opt_price'       => $tr['entry_opt_price'],
                    'entry_lots'            => $tr['entry_lots'],
                    'lot_size'              => $tr['lot_size'],
                    'base_sl'               => $tr['base_sl'],
                    'current_sl'            => $tr['current_sl'],
                    't1_price'              => $tr['t1_price'],
                    't2_price'              => $tr['t2_price'],
                    't3_price'              => $tr['t3_price'],
                    't1_hit'                => $tr['t1_hit'],
                    't2_hit'                => $tr['t2_hit'],
                    't3_hit'                => $tr['t3_hit'],
                    'exit_time'             => $tr['exit_time'],
                    'exit_spot'             => $tr['exit_spot'],
                    'exit_opt_price'        => $tr['exit_opt_price'],
                    'exit_reason'           => $tr['exit_reason'],
                    'pnl_points'            => $tr['pnl_points'],
                    'pnl_rupees'            => $tr['pnl_rupees'],
                    'mfe_points'            => $tr['mfe_points'],
                    'mae_points'            => $tr['mae_points'],
                    'setup_type'            => $tr['setup_type'],
                    'entry_reason'          => $tr['entry_reason'],
                    'is_stagnation_trailed' => $tr['is_stagnation_trailed'],
                    'telemetry_json'        => $tr['telemetry_json'] ?? null,
                ]
            );
        }
    }
}
