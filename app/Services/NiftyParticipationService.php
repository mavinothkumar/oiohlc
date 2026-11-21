<?php

namespace App\Services;

use App\Models\OrderFlowTradedVolume;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NiftyParticipationService
{
    /**
     * Compute the full multi-timeframe Participation Engine dataset for a given date.
     */
    public function getParticipationData(
        string $date,
        int $intervalPrimary = 5,
        int $intervalSecondary = 15,
        string $basis = 'spot',
        bool $forceRefresh = false
    ): array {
        $nowKolkata = Carbon::now('Asia/Kolkata');
        $todayStr = $nowKolkata->toDateString();
        $isToday = ($date === $todayStr);

        $curTimeStr = $nowKolkata->format('H:i');
        $isWeekday = !in_array($nowKolkata->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY]);
        $isMarketHours = ($isToday && $isWeekday && $curTimeStr >= '09:15' && $curTimeStr <= '15:30');
        $isPreMarket = ($isToday && $isWeekday && $curTimeStr >= '09:00' && $curTimeStr < '09:15');

        $intervalPrimary = TimeframeResampler::normalizeInterval($intervalPrimary);
        $intervalSecondary = TimeframeResampler::normalizeInterval($intervalSecondary);
        $basis = strtolower($basis) === 'future' ? 'future' : 'spot';

        // Cache TTL: 15s during live market, 60s outside market today, 86400s (1 day) for historical
        $cacheTtl = $isToday ? ($isMarketHours ? 15 : 60) : 86400;
        $cacheKey = "nifty_participation_engine_{$date}_{$intervalPrimary}_{$intervalSecondary}_{$basis}";

        if ($forceRefresh) {
            Cache::store('file')->forget($cacheKey);
        }

        return Cache::store('file')->remember($cacheKey, $cacheTtl, function () use (
            $date, $todayStr, $isToday, $isMarketHours, $isPreMarket, $nowKolkata, $curTimeStr,
            $isWeekday, $intervalPrimary, $intervalSecondary, $basis
        ) {
            // 1. Resolve constituents for this date
            $periodInfo = NiftyConstituentManager::getConstituentsForDate($date);
            $constituents = $periodInfo['constituents'];

            // 2. Fetch Nifty candles for Primary (e.g. 5m) & Secondary (e.g. 15m)
            $niftyCandlesPrimary = $this->loadNiftyCandles($date, $intervalPrimary, $basis, $isToday, $isMarketHours, $curTimeStr, $isWeekday, $isPreMarket);
            $niftyCandlesSecondary = $this->loadNiftyCandles($date, $intervalSecondary, $basis, $isToday, $isMarketHours, $curTimeStr, $isWeekday, $isPreMarket);

            // Fetch yesterday's candles for seamless context
            $prevTradingDate = $this->getPreviousTradingDate($date);
            $yesterdayNiftyPrimary = $this->loadNiftyCandles($prevTradingDate, $intervalPrimary, $basis, false, false, '15:30', true, false);

            // 3. Load all 50 constituent stock quotes
            $stockBucketsPrimary = $this->loadStockBuckets($date, $intervalPrimary, $constituents);
            $stockBucketsSecondary = $this->loadStockBuckets($date, $intervalSecondary, $constituents);

            // 4. Compute primary timeframe matrix and metrics
            $primaryData = $this->computeTimeframeMatrix(
                $niftyCandlesPrimary,
                $stockBucketsPrimary,
                $constituents,
                $intervalPrimary,
                $date,
                $isToday,
                $nowKolkata
            );

            // 5. Compute secondary timeframe matrix and metrics
            $secondaryData = $this->computeTimeframeMatrix(
                $niftyCandlesSecondary,
                $stockBucketsSecondary,
                $constituents,
                $intervalSecondary,
                $date,
                $isToday,
                $nowKolkata
            );

            // 6. Cross-Timeframe Consistency Matrix & Alignment Engine
            $consistencyEngine = $this->evaluateConsistencyMatrix(
                $primaryData['timeline'],
                $secondaryData['timeline'],
                $intervalPrimary,
                $intervalSecondary
            );

            // 7. Divergence Engine (Index Price slope vs Participation Score slope)
            $divergenceEngine = $this->evaluateDivergenceEngine(
                $niftyCandlesPrimary,
                $primaryData['timeline'],
                $consistencyEngine['composite_scores']
            );

            // 8. Session Summary Stats
            $latestPrimaryCandle = !empty($niftyCandlesPrimary) ? end($niftyCandlesPrimary) : null;
            $firstPrimaryCandle = !empty($niftyCandlesPrimary) ? $niftyCandlesPrimary[0] : null;

            $dayOpenPrice = $firstPrimaryCandle ? (float)$firstPrimaryCandle['open'] : 0.0;
            $latestPrice = $latestPrimaryCandle ? (float)$latestPrimaryCandle['close'] : 0.0;
            $dayChangePts = round($latestPrice - $dayOpenPrice, 2);
            $dayChangePct = $dayOpenPrice > 0 ? round(($dayChangePts / $dayOpenPrice) * 100, 2) : 0.0;

            return [
                'success'             => true,
                'date'                => $date,
                'is_today'            => $isToday,
                'is_live'             => $isMarketHours,
                'is_pre_market'       => $isPreMarket,
                'server_time'         => $nowKolkata->format('H:i:s'),
                'basis'               => $basis,
                'primary_interval'    => $intervalPrimary,
                'secondary_interval'  => $intervalSecondary,
                'rebalancing_period'  => $periodInfo['period_name'],
                'rebalancing_notes'   => $periodInfo['notes'],
                'session_summary'     => [
                    'day_open'        => $dayOpenPrice,
                    'latest_price'    => $latestPrice,
                    'change_pts'      => $dayChangePts,
                    'change_pct'      => $dayChangePct,
                    'yesterday_date'  => $prevTradingDate,
                    'total_bars_p'    => count($niftyCandlesPrimary),
                    'total_bars_s'    => count($niftyCandlesSecondary),
                ],
                'nifty_candles_p'     => $niftyCandlesPrimary,
                'nifty_candles_s'     => $niftyCandlesSecondary,
                'yesterday_candles_p' => $yesterdayNiftyPrimary,
                'primary'             => $primaryData,
                'secondary'           => $secondaryData,
                'consistency'         => $consistencyEngine,
                'divergence'          => $divergenceEngine,
                'constituents_master' => $constituents,
            ];
        });
    }

    /**
     * Compute full constituent matrix, VWAP, Relative Strength, Breadth, and Strength Scores for an interval.
     */
    protected function computeTimeframeMatrix(
        array $niftyCandles,
        array $stockBuckets,
        array $constituents,
        int $interval,
        string $date,
        bool $isToday,
        Carbon $nowKolkata
    ): array {
        $timeline = [];
        $constituentMatrix = [];
        $hasRealEquity = !empty($stockBuckets);

        // Track rolling state across the trading day for each stock:
        // Cumulative volume, cumulative (TP * Vol) for VWAP, day open price
        $stockState = [];
        foreach ($constituents as $stock) {
            $sym = $stock['symbol'];
            $stockState[$sym] = [
                'day_open'       => 0.0,
                'cum_tp_vol'     => 0.0,
                'cum_vol'        => 0,
                'current_price'  => (float)($stock['base_price'] ?? 1000.0),
                'rolling_vol'    => [],
            ];
            $constituentMatrix[$sym] = [];
        }

        $prevBreadth = null;
        $prevDeltaB = 0.0;
        $firstNiftyOpen = !empty($niftyCandles) ? (float)$niftyCandles[0]['open'] : 25000.0;

        foreach ($niftyCandles as $cIndex => $nCandle) {
            $timeStr = $nCandle['time_str'];
            $niftyOpen = (float)$nCandle['open'];
            $niftyClose = (float)$nCandle['close'];
            $niftyMovePts = (float)$nCandle['move_pts'];
            $niftyMovePct = (float)$nCandle['move_pct'];
            $niftyDayReturn = $firstNiftyOpen > 0 ? (($niftyClose - $firstNiftyOpen) / $firstNiftyOpen) * 100 : 0.0;

            // Step 1: Compute each constituent for this candle
            $candleStocks = [];
            $bullishCount = 0;
            $bearishCount = 0;
            $neutralCount = 0;

            $bullishWeight = 0.0;
            $bearishWeight = 0.0;
            $neutralWeight = 0.0;

            $aboveVwapCount = 0;
            $aboveVwapWeight = 0.0;

            $rsOutperformCount = 0;
            $rsOutperformWeight = 0.0;

            $netPoints = 0.0;

            foreach ($constituents as $stock) {
                $sym = $stock['symbol'];
                $weight = (float)$stock['weight'];
                $state = &$stockState[$sym];

                if ($hasRealEquity && isset($stockBuckets[$sym][$timeStr])) {
                    $b = $stockBuckets[$sym][$timeStr];
                    $open = (float)$b['open'];
                    $high = (float)$b['high'];
                    $low = (float)$b['low'];
                    $close = (float)$b['close'];
                    $vol = (int)$b['volume'];
                } else {
                    $prevPrice = $state['current_price'];
                    $open = $prevPrice;
                    $close = $prevPrice;
                    $high = $prevPrice;
                    $low = $prevPrice;
                    $vol = 0;
                }

                if ($state['day_open'] <= 0.0 && $open > 0.0) {
                    $state['day_open'] = $open;
                }
                $dayOpen = $state['day_open'] > 0.0 ? $state['day_open'] : $open;
                $state['current_price'] = $close;

                // Update cumulative VWAP
                $typicalPrice = ($high + $low + $close) / 3.0;
                $state['cum_tp_vol'] += ($typicalPrice * $vol);
                $state['cum_vol'] += $vol;
                $vwap = $state['cum_vol'] > 0 ? round($state['cum_tp_vol'] / $state['cum_vol'], 2) : $close;

                // Relative Volume (RVOL)
                $state['rolling_vol'][] = $vol;
                if (count($state['rolling_vol']) > 15) {
                    array_shift($state['rolling_vol']);
                }
                $avgVol = !empty($state['rolling_vol']) ? array_sum($state['rolling_vol']) / count($state['rolling_vol']) : $vol;
                $rvol = $avgVol > 0 ? round($vol / $avgVol, 2) : 1.0;

                // Day Return & Relative Strength (RS) vs Nifty
                $stockDayReturn = $dayOpen > 0.0 ? round((($close - $dayOpen) / $dayOpen) * 100, 3) : 0.0;
                $rsSpread = round($stockDayReturn - $niftyDayReturn, 3);
                $isOutperforming = ($rsSpread > 0.0);

                // Candle Return & Contribution Points
                $candleMovePct = $open > 0.0 ? round((($close - $open) / $open) * 100, 3) : 0.0;
                $stockPts = round(($weight / 100.0) * ($candleMovePct / 100.0) * $niftyOpen, 2);
                $netPoints += $stockPts;

                $isAboveVwap = ($close >= $vwap);
                if ($isAboveVwap) {
                    $aboveVwapCount++;
                    $aboveVwapWeight += $weight;
                }
                if ($isOutperforming) {
                    $rsOutperformCount++;
                    $rsOutperformWeight += $weight;
                }

                // Institutional 3-State Classification (Bullish / Bearish / Neutral)
                $candleColor = ($close > $open) ? 'green' : (($close < $open) ? 'red' : 'flat');
                $priceVwapSpreadPct = $vwap > 0 ? (($close - $vwap) / $vwap) * 100 : 0.0;

                $participationState = 'NEUTRAL';
                if ($isAboveVwap && ($candleColor === 'green' || $rsSpread > 0.05)) {
                    $participationState = 'BULLISH';
                    $bullishCount++;
                    $bullishWeight += $weight;
                } elseif (!$isAboveVwap && ($candleColor === 'red' || $rsSpread < -0.05)) {
                    $participationState = 'BEARISH';
                    $bearishCount++;
                    $bearishWeight += $weight;
                } else {
                    $participationState = 'NEUTRAL';
                    $neutralCount++;
                    $neutralWeight += $weight;
                }

                // Individual Constituent Strength Score (0 to 100)
                $rsScore = max(0, min(100, 50 + ($rsSpread * 25)));
                $vwapScore = $isAboveVwap ? 80 : 20;
                $rvolScore = min(100, (int)($rvol * 50));
                $persistenceScore = ($candleColor === 'green' && $isAboveVwap) ? 90 : (($candleColor === 'red' && !$isAboveVwap) ? 10 : 50);

                $stockStrengthScore = round((0.40 * $rsScore) + (0.30 * $vwapScore) + (0.20 * $rvolScore) + (0.10 * $persistenceScore), 1);

                $stockDetail = [
                    'symbol'          => $sym,
                    'name'            => $stock['name'],
                    'sector'          => $stock['sector'],
                    'weight'          => $weight,
                    'open'            => $open,
                    'high'            => $high,
                    'low'             => $low,
                    'close'           => $close,
                    'volume'          => $vol,
                    'vwap'            => $vwap,
                    'is_above_vwap'   => $isAboveVwap,
                    'vwap_spread_pct' => round($priceVwapSpreadPct, 2),
                    'day_return_pct'  => $stockDayReturn,
                    'rs_spread'       => $rsSpread,
                    'is_outperform'   => $isOutperforming,
                    'candle_move_pct' => $candleMovePct,
                    'pts'             => $stockPts,
                    'color'           => $candleColor,
                    'state'           => $participationState,
                    'rvol'            => $rvol,
                    'score'           => $stockStrengthScore,
                ];

                $candleStocks[] = $stockDetail;
                $constituentMatrix[$sym][] = $stockDetail;
            }

            // Weighted average constituent strength score for the candle
            $weightedScoreSum = 0;
            $totalWeight = 0;
            foreach ($candleStocks as $cs) {
                $weightedScoreSum += ($cs['score'] * $cs['weight']);
                $totalWeight += $cs['weight'];
            }
            $avgConstituentScore = $totalWeight > 0 ? round($weightedScoreSum / $totalWeight, 1) : 50.0;

            // Zero-Flicker Carry-Forward for forming live bars
            if (($bullishCount + $bearishCount) === 0 && $prevBreadth) {
                $bullishCount = $prevBreadth['bullish_count'];
                $bearishCount = $prevBreadth['bearish_count'];
                $neutralCount = $prevBreadth['neutral_count'];
                $bullishWeight = $prevBreadth['bullish_weight'];
                $bearishWeight = $prevBreadth['bearish_weight'];
                $neutralWeight = $prevBreadth['neutral_weight'];
                $aboveVwapCount = $prevBreadth['vwap_above_count'];
                $aboveVwapWeight = $prevBreadth['vwap_above_weight'];
                $rsOutperformCount = $prevBreadth['rs_outperform_count'];
                $rsOutperformWeight = $prevBreadth['rs_outperform_weight'];
            }

            // Sort stocks for drivers & draggers
            usort($candleStocks, fn($a, $b) => $b['pts'] <=> $a['pts']);
            $topDrivers = array_slice($candleStocks, 0, 5);
            $topDraggers = array_reverse(array_slice($candleStocks, -5));

            usort($candleStocks, fn($a, $b) => $b['rs_spread'] <=> $a['rs_spread']);
            $topRsLeaders = array_slice($candleStocks, 0, 5);
            $topRsLaggards = array_reverse(array_slice($candleStocks, -5));

            // Breadth Ratios
            $bullishPct = round(($bullishCount / 50) * 100, 1);
            $bearishPct = round(($bearishCount / 50) * 100, 1);
            $neutralPct = round(($neutralCount / 50) * 100, 1);
            $netBreadth = $bullishCount - $bearishCount; // Range -50 to +50

            // Strength Ratios
            $vwapParticipationPct = round(($aboveVwapCount / 50) * 100, 1);
            $rsParticipationPct = round(($rsOutperformCount / 50) * 100, 1);

            // Momentum & Acceleration
            $prevBullishPct = $prevBreadth ? $prevBreadth['bullish_pct'] : $bullishPct;
            $deltaB = round($bullishPct - $prevBullishPct, 1);
            $acceleration = round($deltaB - $prevDeltaB, 1);
            $prevDeltaB = $deltaB;

            // Momentum Classification
            $momentumRegime = 'STABLE_EQUILIBRIUM';
            if ($deltaB > 4.0 && $acceleration >= 0) {
                $momentumRegime = 'ACCELERATING_EXPANSION';
            } elseif ($deltaB > 0) {
                $momentumRegime = 'STEADY_EXPANSION';
            } elseif ($deltaB < -4.0 && $acceleration <= 0) {
                $momentumRegime = 'ACCELERATING_CONTRACTION';
            } elseif ($deltaB < 0) {
                $momentumRegime = 'STEADY_CONTRACTION';
            }

            // Sector Aggregation
            $sectorMap = [];
            foreach ($candleStocks as $cs) {
                $sec = $cs['sector'];
                if (!isset($sectorMap[$sec])) {
                    $sectorMap[$sec] = [
                        'sector'    => $sec,
                        'weight'    => 0.0,
                        'pts'       => 0.0,
                        'bull_cnt'  => 0,
                        'bear_cnt'  => 0,
                        'neu_cnt'   => 0,
                        'above_vwap'=> 0,
                    ];
                }
                $sectorMap[$sec]['weight'] += $cs['weight'];
                $sectorMap[$sec]['pts'] += $cs['pts'];
                if ($cs['state'] === 'BULLISH') $sectorMap[$sec]['bull_cnt']++;
                elseif ($cs['state'] === 'BEARISH') $sectorMap[$sec]['bear_cnt']++;
                else $sectorMap[$sec]['neu_cnt']++;
                if ($cs['is_above_vwap']) $sectorMap[$sec]['above_vwap']++;
            }
            foreach ($sectorMap as $k => $v) {
                $sectorMap[$k]['weight'] = round($v['weight'], 1);
                $sectorMap[$k]['pts'] = round($v['pts'], 2);
            }
            uasort($sectorMap, fn($a, $b) => $b['pts'] <=> $a['pts']);

            // Candle Completion status
            $candleDurationSec = $interval * 60;
            $candleStartTs = Carbon::parse("{$date} {$timeStr}:00", 'Asia/Kolkata')->timestamp;
            $candleEndTs = $candleStartTs + $candleDurationSec;
            $nowTs = $nowKolkata->timestamp;
            $isComplete = (!$isToday) || ($nowTs >= $candleEndTs);

            $breadthItem = [
                'time'                  => $timeStr,
                'timestamp'             => $nCandle['timestamp'] ?? 0,
                'is_complete'           => $isComplete,
                'status'                => $isComplete ? 'CONFIRMED' : 'FORMING',
                'nifty_open'            => $niftyOpen,
                'nifty_close'           => $niftyClose,
                'nifty_move_pts'        => $niftyMovePts,
                'nifty_move_pct'        => $niftyMovePct,
                // 1. Breadth
                'bullish_count'         => $bullishCount,
                'bearish_count'         => $bearishCount,
                'neutral_count'         => $neutralCount,
                'bullish_pct'           => $bullishPct,
                'bearish_pct'           => $bearishPct,
                'neutral_pct'           => $neutralPct,
                'net_breadth'           => $netBreadth,
                'bullish_weight'        => round($bullishWeight, 1),
                'bearish_weight'        => round($bearishWeight, 1),
                'neutral_weight'        => round($neutralWeight, 1),
                // 2. Strength
                'vwap_above_count'      => $aboveVwapCount,
                'vwap_participation_pct'=> $vwapParticipationPct,
                'vwap_above_weight'     => round($aboveVwapWeight, 1),
                'rs_outperform_count'   => $rsOutperformCount,
                'rs_participation_pct'  => $rsParticipationPct,
                'rs_outperform_weight'  => round($rsOutperformWeight, 1),
                'net_points'            => round($netPoints, 2),
                'avg_constituent_score' => $avgConstituentScore,
                // 3. Momentum
                'breadth_delta'         => $deltaB,
                'breadth_acceleration'  => $acceleration,
                'momentum_regime'       => $momentumRegime,
                // Top Movers
                'top_drivers'           => $topDrivers,
                'top_draggers'          => $topDraggers,
                'top_rs_leaders'        => $topRsLeaders,
                'top_rs_laggards'       => $topRsLaggards,
                'sector_summary'        => array_values($sectorMap),
            ];

            $timeline[$timeStr] = $breadthItem;
            $prevBreadth = $breadthItem;
        }

        return [
            'interval'    => $interval,
            'timeline'    => $timeline,
            'matrix'      => $constituentMatrix,
            'timestamps'  => array_keys($timeline),
        ];
    }

    /**
     * Cross-Timeframe Consistency Matrix: Evaluates agreement between Primary (e.g. 5m) & Secondary (e.g. 15m).
     */
    protected function evaluateConsistencyMatrix(
        array $primaryTimeline,
        array $secondaryTimeline,
        int $intPrimary,
        int $intSecondary
    ): array {
        $alignmentTimeline = [];
        $compositeScores = [];

        foreach ($primaryTimeline as $timeStr => $p) {
            // Find corresponding secondary parent candle (e.g. 09:25 maps to 09:15-09:30 15m candle)
            $s = $this->resolveParentCandle($timeStr, $secondaryTimeline, $intSecondary);
            if (!$s && !empty($secondaryTimeline)) {
                $s = end($secondaryTimeline);
            }

            $pNet = $p['net_breadth'];
            $sNet = $s ? $s['net_breadth'] : $pNet;

            // Classify 15m state: Bullish (Net >= +10), Bearish (Net <= -10), Neutral
            $sState = ($sNet >= 10) ? 'BULLISH' : (($sNet <= -10) ? 'BEARISH' : 'NEUTRAL');
            // Classify 5m state: Bullish (Net >= +6), Bearish (Net <= -6), Neutral
            $pState = ($pNet >= 6) ? 'BULLISH' : (($pNet <= -6) ? 'BEARISH' : 'NEUTRAL');

            $alignmentVerdict = 'CHOP_EQUILIBRIUM';
            $verdictLabel = '⚖️ Indecisive Chop / Consolidation';
            $badgeColor = 'slate';
            $consistencyScore = 50;

            if ($sState === 'BULLISH' && $pState === 'BULLISH') {
                $alignmentVerdict = 'FULL_DIRECTIONAL_AGREEMENT_BULL';
                $verdictLabel = '🚀 Directional Agreement (Strong Rally)';
                $badgeColor = 'emerald';
                $consistencyScore = 95;
            } elseif ($sState === 'BEARISH' && $pState === 'BEARISH') {
                $alignmentVerdict = 'FULL_DIRECTIONAL_AGREEMENT_BEAR';
                $verdictLabel = '💥 Directional Agreement (Strong Selloff)';
                $badgeColor = 'rose';
                $consistencyScore = 5;
            } elseif ($sState === 'BULLISH' && $pState === 'NEUTRAL') {
                $alignmentVerdict = 'BULLISH_INDECISION';
                $verdictLabel = '🟡 Broader Positive, Short-term Pause';
                $badgeColor = 'amber';
                $consistencyScore = 70;
            } elseif ($sState === 'BULLISH' && $pState === 'BEARISH') {
                $alignmentVerdict = 'BULLISH_PULLBACK';
                $verdictLabel = '🟠 Pullback within Bullish Structure';
                $badgeColor = 'orange';
                $consistencyScore = 55;
            } elseif ($sState === 'BEARISH' && $pState === 'NEUTRAL') {
                $alignmentVerdict = 'BEARISH_INDECISION';
                $verdictLabel = '🟡 Broader Negative, Short-term Pause';
                $badgeColor = 'amber';
                $consistencyScore = 30;
            } elseif ($sState === 'BEARISH' && $pState === 'BULLISH') {
                $alignmentVerdict = 'BEARISH_COUNTER_BOUNCE';
                $verdictLabel = '🔵 Counter-Bounce within Bearish Structure';
                $badgeColor = 'sky';
                $consistencyScore = 45;
            }

            // Composite Market-Strength Directional Score (0 to 100, 100=Strong Bull, 0=Strong Bear)
            // S = 0.30*Breadth + 0.30*Strength + 0.20*Momentum + 0.20*Consistency
            $breadthScore = max(0, min(100, 50 + ($p['net_breadth'] * 1.0)));
            $avgStockScore = $p['avg_constituent_score'] ?? 50.0;
            $strengthScore = round((0.5 * $p['vwap_participation_pct']) + (0.5 * $avgStockScore), 1);
            $momentumScore = max(0, min(100, 50 + ($p['breadth_delta'] * 1.8) + ($p['breadth_acceleration'] * 1.0)));

            $compositeScore = round(
                (0.30 * $breadthScore) +
                (0.30 * $strengthScore) +
                (0.20 * $momentumScore) +
                (0.20 * $consistencyScore),
                1
            );

            $alignmentItem = [
                'time'              => $timeStr,
                'timestamp'         => $p['timestamp'] ?? 0,
                'p_net'             => $pNet,
                's_net'             => $sNet,
                'p_state'           => $pState,
                's_state'           => $sState,
                'verdict'           => $alignmentVerdict,
                'verdict_label'     => $verdictLabel,
                'badge_color'       => $badgeColor,
                'consistency_score' => $consistencyScore,
                'composite_score'   => $compositeScore,
                'breadth_score'     => round($breadthScore, 1),
                'strength_score'    => round($strengthScore, 1),
                'momentum_score'    => round($momentumScore, 1),
            ];

            $alignmentTimeline[$timeStr] = $alignmentItem;
            $compositeScores[$timeStr] = $compositeScore;
        }

        return [
            'alignment_timeline' => $alignmentTimeline,
            'composite_scores'   => $compositeScores,
        ];
    }

    /**
     * Divergence Diagnostic Engine: Detects divergences between Nifty Index Price slope & Participation Score slope.
     */
    protected function evaluateDivergenceEngine(
        array $niftyCandles,
        array $primaryTimeline,
        array $compositeScores
    ): array {
        $divergenceTimeline = [];
        $candlesByTime = [];
        foreach ($niftyCandles as $c) {
            $candlesByTime[$c['time_str']] = $c;
        }

        $times = array_keys($primaryTimeline);
        for ($i = 0; $i < count($times); $i++) {
            $t = $times[$i];
            $curCandle = $candlesByTime[$t] ?? null;
            $curScore = $compositeScores[$t] ?? 50;

            if ($i < 2 || !$curCandle) {
                $divergenceTimeline[$t] = [
                    'time'        => $t,
                    'type'        => 'NO_DIVERGENCE',
                    'badge'       => 'Normal Alignment',
                    'color'       => 'gray',
                    'description' => 'Baseline market structure establishing.',
                ];
                continue;
            }

            // 3-Bar Lookback
            $prev3Time = $times[$i - 2];
            $prev3Candle = $candlesByTime[$prev3Time] ?? null;
            $prev3Score = $compositeScores[$prev3Time] ?? 50;

            $priceSlope = ($prev3Candle) ? ((float)$curCandle['close'] - (float)$prev3Candle['open']) : 0.0;
            $scoreSlope = $curScore - $prev3Score;

            $divType = 'NO_DIVERGENCE';
            $badge = 'Confirmed Agreement';
            $color = 'emerald';
            $desc = 'Price and constituent participation moving synchronously.';

            if ($priceSlope > 15.0 && $scoreSlope < -8.0) {
                $divType = 'BEARISH_DIVERGENCE_WARNING';
                $badge = '⚠️ Exhaustion / Heavyweight Masking';
                $color = 'amber';
                $desc = "Nifty price is rising (+{$priceSlope} pts) while constituent participation is deteriorating (-{$scoreSlope} pts).";
            } elseif ($priceSlope < -15.0 && $scoreSlope > 8.0) {
                $divType = 'BULLISH_DIVERGENCE_WARNING';
                $badge = '⚡ Institutional Absorption / Defense';
                $color = 'cyan';
                $desc = "Nifty price is dropping ({$priceSlope} pts) while constituent participation is quietly improving (+{$scoreSlope} pts).";
            } elseif ($priceSlope > 15.0 && $scoreSlope > 5.0) {
                $divType = 'CONFIRMED_RALLY';
                $badge = '🚀 Broad-Based Rally Confirmed';
                $color = 'emerald';
                $desc = 'Price gains supported by expanding constituent participation and relative strength.';
            } elseif ($priceSlope < -15.0 && $scoreSlope < -5.0) {
                $divType = 'CONFIRMED_SELLOFF';
                $badge = '💥 Broad-Based Selloff Confirmed';
                $color = 'rose';
                $desc = 'Price drop confirmed by expanding negative constituent participation and weak VWAP.';
            }

            $divergenceTimeline[$t] = [
                'time'        => $t,
                'type'        => $divType,
                'badge'       => $badge,
                'color'       => $color,
                'description' => $desc,
                'price_slope' => round($priceSlope, 1),
                'score_slope' => round($scoreSlope, 1),
            ];
        }

        return [
            'divergence_timeline' => $divergenceTimeline,
        ];
    }

    /**
     * Map a primary candle time (e.g. 5m) to its containing parent secondary candle (e.g. 15m).
     */
    protected function resolveParentCandle(string $timeStr, array $secondaryTimeline, int $secondaryMinutes): ?array
    {
        if (isset($secondaryTimeline[$timeStr])) {
            return $secondaryTimeline[$timeStr];
        }

        // Secondary candle times are multiples of secondaryMinutes (e.g. 09:15, 09:30, 09:45...)
        [$h, $m] = explode(':', $timeStr);
        $totalMinutes = ((int)$h * 60) + (int)$m;
        $marketOpenMinutes = (9 * 60) + 15;

        $offset = $totalMinutes - $marketOpenMinutes;
        if ($offset < 0) return reset($secondaryTimeline) ?: null;

        $bucketIndex = (int)floor($offset / $secondaryMinutes);
        $parentMinutes = $marketOpenMinutes + ($bucketIndex * $secondaryMinutes);
        $parentH = str_pad((string)floor($parentMinutes / 60), 2, '0', STR_PAD_LEFT);
        $parentM = str_pad((string)($parentMinutes % 60), 2, '0', STR_PAD_LEFT);
        $parentTimeStr = "{$parentH}:{$parentM}";

        return $secondaryTimeline[$parentTimeStr] ?? (end($secondaryTimeline) ?: null);
    }

    /**
     * Fetch real NIFTY candles (Spot or Futures) and format for Lightweight Charts.
     */
    protected function loadNiftyCandles(
        string $date,
        int $interval,
        string $basis,
        bool $isToday,
        bool $isMarketHours,
        string $curTimeStr,
        bool $isWeekday,
        bool $isPreMarket
    ): array {
        if ($basis === 'future') {
            $candles = \App\Services\FuturesCandleService::loadCandlesForDate('NIFTY', $date, $interval);
            if (!empty($candles)) {
                return $candles;
            }
        }

        // Query real 5m index quotes
        $dbQuotes = DB::table('ohlc_5m_quotes')
            ->where(function($q) {
                $q->where('instrument_key', 'like', '%Nifty 50%')
                  ->orWhere('trading_symbol', 'Nifty 50')
                  ->orWhere('trading_symbol', 'NIFTY');
            })
            ->where('instrument_type', 'INDEX')
            ->where('ts_at', '>=', "{$date} 00:00:00")
            ->where('ts_at', '<=', "{$date} 23:59:59")
            ->orderBy('ts_at')
            ->get();

        $raw5m = [];
        $seen = [];
        foreach ($dbQuotes as $q) {
            $ts = Carbon::parse($q->ts_at, 'Asia/Kolkata');
            $tStr = $ts->format('H:i');
            if ($tStr < '09:15' || $tStr > '15:25' || isset($seen[$tStr])) continue;
            if ($isToday && $isWeekday && $curTimeStr <= '15:30' && $tStr > $curTimeStr) continue;
            $seen[$tStr] = true;

            $open = round((float)$q->open, 2);
            $high = round((float)$q->high, 2);
            $low = round((float)$q->low, 2);
            $close = round((float)$q->close, 2);

            $raw5m[] = [
                'ts_at'     => $q->ts_at,
                'time_str'  => $tStr,
                'timestamp' => Carbon::parse($q->ts_at, 'UTC')->timestamp,
                'open'      => $open,
                'high'      => $high,
                'low'       => $low,
                'close'     => $close,
                'volume'    => (int)$q->volume,
                'move_pts'  => round($close - $open, 2),
                'move_pct'  => $open > 0 ? round((($close - $open) / $open) * 100, 3) : 0.0,
                'color'     => $close >= $open ? 'green' : 'red',
            ];
        }

        if (empty($raw5m)) {
            // Check 1m quotes in ohlc_quotes
            $db1m = DB::table('ohlc_quotes')
                ->where(function($q) {
                    $q->where('instrument_key', 'like', '%Nifty 50%')
                      ->orWhere('trading_symbol', 'Nifty 50')
                      ->orWhere('trading_symbol', 'NIFTY');
                })
                ->where('instrument_type', 'INDEX')
                ->where('ts_at', '>=', "{$date} 00:00:00")
                ->where('ts_at', '<=', "{$date} 23:59:59")
                ->orderBy('ts_at')
                ->get();

            if ($db1m->isNotEmpty()) {
                $raw1m = [];
                foreach ($db1m as $q) {
                    $tStr = Carbon::parse($q->ts_at, 'Asia/Kolkata')->format('H:i');
                    if ($tStr < '09:15' || $tStr > '15:29') continue;
                    $raw1m[] = [
                        'ts_at'     => $q->ts_at,
                        'time_str'  => $tStr,
                        'timestamp' => Carbon::parse($q->ts_at, 'UTC')->timestamp,
                        'open'      => (float)$q->open,
                        'high'      => (float)$q->high,
                        'low'       => (float)$q->low,
                        'close'     => (float)$q->close,
                        'volume'    => (int)$q->volume,
                    ];
                }
                return TimeframeResampler::resampleCandles($raw1m, $interval, $date);
            }
        }

        if ($interval === 5) {
            return $raw5m;
        }

        return TimeframeResampler::resampleCandles($raw5m, $interval, $date);
    }

    /**
     * Load constituent stock quotes organized by symbol and time string.
     */
    protected function loadStockBuckets(string $date, int $interval, array $constituents): array
    {
        $dbQuotes = DB::table('ohlc_5m_quotes')
            ->where('instrument_type', 'EQ')
            ->where('ts_at', '>=', "{$date} 00:00:00")
            ->where('ts_at', '<=', "{$date} 23:59:59")
            ->orderBy('ts_at')
            ->get()
            ->groupBy('trading_symbol');

        $stockBuckets = [];

        if ($dbQuotes->count() >= 30) {
            if ($interval === 5) {
                foreach ($dbQuotes as $sym => $quotes) {
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
                $stockBuckets = TimeframeResampler::resampleStockQuotes($dbQuotes->toArray(), $interval);
            }
        } else {
            // Fallback to 1m quotes table
            $db1m = DB::table('ohlc_quotes')
                ->where('instrument_type', 'EQ')
                ->where('ts_at', '>=', "{$date} 00:00:00")
                ->where('ts_at', '<=', "{$date} 23:59:59")
                ->orderBy('ts_at')
                ->get()
                ->groupBy('trading_symbol');

            if ($db1m->count() >= 30) {
                $stockBuckets = TimeframeResampler::resampleStockQuotes($db1m->toArray(), $interval);
            }
        }

        return $stockBuckets;
    }

    /**
     * Resolve the previous valid trading session date.
     */
    protected function getPreviousTradingDate(string $date): string
    {
        if (Schema::hasTable('nse_working_days')) {
            $prev = DB::table('nse_working_days')
                ->where('working_date', '<', $date)
                ->orderByDesc('working_date')
                ->value('working_date');
            if ($prev) return $prev;
        }

        return Carbon::parse($date)->subWeekday()->toDateString();
    }
}
