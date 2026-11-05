<?php

namespace App\Services\OrderFlow;

use App\Models\DailyTrend;
use App\Models\OrderFlowAlertLog;
use App\Models\OrderFlowSignalLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderFlowCumulativeReportService
{
    /**
     * Generate Enterprise-Grade Cumulative EOD Report based on Alerts, Journal, and Market Criteria.
     */
    public function generateReport(string $date, string $symbol = 'NIFTY', ?string $mode = null): array
    {
        // 1. Fetch Journal Opportunity Calls (Signal Logs) first for real-time live intraday spot quotes
        $journalQuery = OrderFlowSignalLog::where('trade_date', $date)
            ->where('symbol', $symbol);
        if ($mode) {
            $journalQuery->where('mode', $mode);
        }
        $journalLogs = $journalQuery->orderBy('signal_time')->get();

        // 2. Resolve Market Quotes & Baseline Context (Prioritizing Live Intraday Data)
        $marketContext = $this->resolveMarketContext($date, $symbol, $journalLogs);
        $openPrice   = $marketContext['open'];
        $closePrice  = $marketContext['close'];
        $highPrice   = $marketContext['high'];
        $lowPrice    = $marketContext['low'];
        $dayRangePts = $marketContext['range_pts'];
        $dayChangePts = $marketContext['change_pts'];
        $dayChangePct = $marketContext['change_pct'];
        $dailyTrend   = $marketContext['daily_trend'];

        // 3. Fetch and Analyze Order Flow Alerts for the Day
        $alertsQuery = OrderFlowAlertLog::where('trade_date', $date)
            ->where('symbol', $symbol);
        if ($mode) {
            $alertsQuery->where('mode', $mode);
        }
        $alerts = $alertsQuery->orderBy('alert_time')->get();

        $alertsAnalysis = $this->analyzeAlerts($alerts, $openPrice, $highPrice, $lowPrice, $closePrice);

        $journalAnalysis = $this->analyzeJournal($journalLogs);

        // 4. Synthesize Value Area & Range Distribution
        $rangeAnalysis = $this->analyzeRangeAndValueArea($alerts, $journalLogs, $openPrice, $highPrice, $lowPrice, $closePrice, $dailyTrend);

        // 5. Synthesize The 5 Core Pillars: Who, When, How, Which, Range
        $dominanceSynthesis = $this->synthesizeDominance(
            $alertsAnalysis,
            $journalAnalysis,
            $rangeAnalysis,
            $dayChangePts,
            $openPrice,
            $closePrice
        );

        // 6. Compute Tomorrow Support & Resistance and Actionable Playbook
        $tomorrowPlaybook = $this->computeTomorrowLevelsAndPlaybook(
            $rangeAnalysis,
            $alertsAnalysis,
            $dominanceSynthesis,
            $dailyTrend,
            $closePrice
        );

        // 7. Extract Top Heavy Order Flow Block Transactions (Institutional Footprint)
        $heavyBlocks = $this->extractHeavyBlockTransactions($journalLogs, $alerts);

        return [
            'meta' => [
                'symbol'        => $symbol,
                'trade_date'    => $date,
                'mode'          => $mode ?: 'all',
                'generated_at'  => Carbon::now('Asia/Kolkata')->toDateTimeString(),
                'version'       => 'Enterprise v2.5 Microstructure AI',
            ],
            'market_context' => [
                'open'          => $openPrice,
                'high'          => $highPrice,
                'low'           => $lowPrice,
                'close'         => $closePrice,
                'range_pts'     => $dayRangePts,
                'change_pts'    => $dayChangePts,
                'change_pct'    => $dayChangePct,
                'bias'          => $dayChangePts >= 0 ? 'BULLISH' : 'BEARISH',
            ],
            'alerts_summary'    => $alertsAnalysis,
            'journal_summary'   => $journalAnalysis,
            'range_summary'     => $rangeAnalysis,
            'dominance_matrix'  => $dominanceSynthesis,
            'tomorrow_playbook' => $tomorrowPlaybook,
            'heavy_blocks'      => $heavyBlocks,
        ];
    }

    /**
     * Resolve accurate Market Context (Open, High, Low, Close) prioritizing Live Intraday Data.
     */
    protected function resolveMarketContext(string $date, string $symbol, Collection $journalLogs): array
    {
        $dailyTrend = $this->resolveDailyTrend($date, $symbol);

        // Priority 1: Check Weightage 5m candles cache for full real session OHLC (09:15 - 15:30)
        $weightageData = Cache::store('file')->get("nifty_weightage_data_{$date}");
        $niftyCandles = $weightageData['candles'] ?? $weightageData['nifty_candles'] ?? null;

        $firstSpot = $journalLogs->first()?->spot_price ? (float)$journalLogs->first()->spot_price : null;
        $lastSpot  = $journalLogs->last()?->spot_price ? (float)$journalLogs->last()->spot_price : null;
        $maxSpot   = $journalLogs->max('spot_price') ? (float)$journalLogs->max('spot_price') : null;
        $minSpot   = $journalLogs->min('spot_price') ? (float)$journalLogs->min('spot_price') : null;

        if (!empty($niftyCandles)) {
            $open  = (float) $niftyCandles[0]['open'];
            $close = (float) end($niftyCandles)['close'];
            $high  = (float) max(array_column($niftyCandles, 'high'));
            $low   = (float) min(array_column($niftyCandles, 'low'));
        } elseif ($dailyTrend && !empty($dailyTrend->current_day_index_open) && !empty($dailyTrend->index_close)) {
            // Priority 2: Daily trend EOD record
            $open  = (float) $dailyTrend->current_day_index_open;
            $close = (float) $dailyTrend->index_close;
            $high  = (float) ($dailyTrend->index_high ?? max($open, $close));
            $low   = (float) ($dailyTrend->index_low ?? min($open, $close));
        } elseif (Schema::hasTable('option_chains') && ($liveSpot = DB::table('option_chains')->where('trading_symbol', $symbol)->where('underlying_spot_price', '>', 0)->value('underlying_spot_price'))) {
            // Priority 3: Live option chain spot
            $close = (float) $liveSpot;
            $open  = (float) ($dailyTrend->current_day_index_open ?? $close);
            $high  = (float) max($open, $close) + 20.0;
            $low   = (float) min($open, $close) - 20.0;
        } elseif ($firstSpot && $lastSpot && $firstSpot > 0 && $lastSpot > 0) {
            // Priority 4: Fallback to Journal spot logs
            $open  = $firstSpot;
            $close = $lastSpot;
            $high  = (float) max($maxSpot, max($open, $close));
            $low   = (float) min($minSpot, min($open, $close));
        } else {
            // Priority 5: Fallback to daily trend record
            $open  = (float) ($dailyTrend->current_day_index_open ?? 24500.0);
            $close = (float) ($dailyTrend->index_close ?? $open);
            $high  = (float) ($dailyTrend->index_high ?? max($open, $close));
            $low   = (float) ($dailyTrend->index_low ?? min($open, $close));
        }

        if ($high <= $low) {
            $high = max($open, $close) + 25.0;
            $low  = min($open, $close) - 25.0;
        }

        $dayRangePts  = round($high - $low, 2);
        $dayChangePts = round($close - $open, 2);
        $dayChangePct = $open > 0 ? round(($dayChangePts / $open) * 100, 2) : 0.0;

        return [
            'open'        => $open,
            'high'        => $high,
            'low'         => $low,
            'close'       => $close,
            'range_pts'   => $dayRangePts,
            'change_pts'  => $dayChangePts,
            'change_pct'  => $dayChangePct,
            'bias'        => $dayChangePts >= 0 ? 'BULLISH' : 'BEARISH',
            'daily_trend' => $dailyTrend,
        ];
    }

    /**
     * Resolve Daily Trend record for symbol and date.
     */
    protected function resolveDailyTrend(string $date, string $symbol): ?DailyTrend
    {
        if (!Schema::hasTable('daily_trend')) {
            return null;
        }

        $trend = DailyTrend::where('symbol_name', $symbol)
            ->where(function ($q) use ($date) {
                $q->where('quote_date', $date)
                  ->orWhere('trading_date', $date);
            })
            ->orderByDesc('id')
            ->first();

        if (!$trend) {
            $trend = DailyTrend::where('symbol_name', $symbol)
                ->where('quote_date', '<=', $date)
                ->orderByDesc('quote_date')
                ->first();
        }

        return $trend;
    }

    /**
     * Analyze all order flow alerts triggered during the day.
     */
    protected function analyzeAlerts(Collection $alerts, float $open, float $high, float $low, float $close = 0.0): array
    {
        $total = $alerts->count();
        $buyAlerts  = $alerts->where('side', 'BUY');
        $sellAlerts = $alerts->where('side', 'SELL');

        $buyCount  = $buyAlerts->count();
        $sellCount = $sellAlerts->count();

        // Categorical alert breakdown
        $stackBuyCount  = $alerts->where('alert_type', 'STACK_BUY')->count();
        $stackSellCount = $alerts->where('alert_type', 'STACK_SELL')->count();
        $blockBuyCount  = $alerts->where('alert_type', 'BLOCK_BUY')->count();
        $blockSellCount = $alerts->where('alert_type', 'BLOCK_SELL')->count();
        $strategyBuy    = $alerts->where('alert_type', 'STRATEGY_BUY')->count();
        $strategySell   = $alerts->where('alert_type', 'STRATEGY_SELL')->count();

        // Institutional Liquidity Committed (Quantities from Blocks & Stacks)
        $bidLiquidityQty = (int) $alerts->whereIn('alert_type', ['BLOCK_BUY', 'STACK_BUY'])->sum('quantity');
        $askLiquidityQty = (int) $alerts->whereIn('alert_type', ['BLOCK_SELL', 'STACK_SELL'])->sum('quantity');

        // Microstructure Liquidity Absorption & Structural Wall Outcomes (Swept vs Defended)
        $sweptAskCount    = 0;
        $defendedAskCount = 0;
        $defendedBidCount = 0;
        $sweptBidCount    = 0;
        $bullAlertEvents  = 0;
        $bearAlertEvents  = 0;

        foreach ($alerts as $a) {
            $price = (float) $a->price;
            if ($a->alert_type === 'BLOCK_SELL' || $a->alert_type === 'STACK_SELL') {
                if ($close > 0 && $close >= $price) {
                    $sweptAskCount++;
                    $bullAlertEvents++;
                } else {
                    $defendedAskCount++;
                    $bearAlertEvents++;
                }
            } elseif ($a->alert_type === 'BLOCK_BUY' || $a->alert_type === 'STACK_BUY') {
                if ($close > 0 && $close >= $price) {
                    $defendedBidCount++;
                    $bullAlertEvents++;
                } else {
                    $sweptBidCount++;
                    $bearAlertEvents++;
                }
            } else {
                if ($a->side === 'BUY') {
                    $bullAlertEvents++;
                } else {
                    $bearAlertEvents++;
                }
            }
        }

        $totalAbsorptionEvents = $bullAlertEvents + $bearAlertEvents;
        $absorptionBullPct = $totalAbsorptionEvents > 0 ? round(($bullAlertEvents / $totalAbsorptionEvents) * 100, 1) : 50.0;
        $absorptionBearPct = round(100.0 - $absorptionBullPct, 1);

        // Significant Institutional Resting Walls (Prices where heavy blocks appeared)
        $bidWalls = $alerts->where('alert_type', 'BLOCK_BUY')
            ->filter(fn($a) => $a->price > 0)
            ->groupBy(fn($a) => (string) round($a->price, 0))
            ->map(function ($group, $price) {
                return [
                    'price'     => (float) $price,
                    'count'     => $group->count(),
                    'total_qty' => (int) $group->sum('quantity'),
                    'last_seen' => $group->max('alert_time'),
                ];
            })
            ->sortByDesc('total_qty')
            ->values()
            ->take(5);

        $askWalls = $alerts->where('alert_type', 'BLOCK_SELL')
            ->filter(fn($a) => $a->price > 0)
            ->groupBy(fn($a) => (string) round($a->price, 0))
            ->map(function ($group, $price) {
                return [
                    'price'     => (float) $price,
                    'count'     => $group->count(),
                    'total_qty' => (int) $group->sum('quantity'),
                    'last_seen' => $group->max('alert_time'),
                ];
            })
            ->sortByDesc('total_qty')
            ->values()
            ->take(5);

        // Hourly Alert Distribution (09:00 to 15:00 IST)
        $hourlyDistribution = [];
        $hours = ['09', '10', '11', '12', '13', '14', '15'];
        foreach ($hours as $h) {
            $matching = $alerts->filter(fn($a) => str_starts_with((string)$a->alert_time, $h . ':'));
            $hBuys  = $matching->where('side', 'BUY')->count();
            $hSells = $matching->where('side', 'SELL')->count();
            $hourlyDistribution[] = [
                'hour'       => $h . ':00',
                'total'      => $matching->count(),
                'buy_count'  => $hBuys,
                'sell_count' => $hSells,
                'dominant'   => $hBuys > $hSells ? 'BULL' : ($hSells > $hBuys ? 'BEAR' : 'NEUTRAL'),
            ];
        }

        // Market Phase Grouping: Opening Drive, Midday Rotation, Closing Auction
        $phaseOpening = $alerts->filter(fn($a) => strcmp($a->alert_time, '09:15:00') >= 0 && strcmp($a->alert_time, '10:30:00') <= 0);
        $phaseMidday  = $alerts->filter(fn($a) => strcmp($a->alert_time, '10:30:00') > 0 && strcmp($a->alert_time, '13:30:00') <= 0);
        $phaseClosing = $alerts->filter(fn($a) => strcmp($a->alert_time, '13:30:00') > 0 && strcmp($a->alert_time, '15:30:00') <= 0);

        $phases = [
            'opening_drive' => [
                'name'        => 'Opening Drive (09:15 - 10:30)',
                'total'       => $phaseOpening->count(),
                'buys'        => $phaseOpening->where('side', 'BUY')->count(),
                'sells'       => $phaseOpening->where('side', 'SELL')->count(),
                'bias'        => $phaseOpening->where('side', 'BUY')->count() >= $phaseOpening->where('side', 'SELL')->count() ? 'BULLISH' : 'BEARISH',
                'description' => 'Initial balance establishment and opening liquidity block placement.',
            ],
            'midday_rotation' => [
                'name'        => 'Midday Rotation (10:30 - 13:30)',
                'total'       => $phaseMidday->count(),
                'buys'        => $phaseMidday->where('side', 'BUY')->count(),
                'sells'       => $phaseMidday->where('side', 'SELL')->count(),
                'bias'        => $phaseMidday->where('side', 'BUY')->count() >= $phaseMidday->where('side', 'SELL')->count() ? 'BULLISH' : 'BEARISH',
                'description' => 'Structural order book absorption, consolidation nodes, and range rotations.',
            ],
            'closing_auction' => [
                'name'        => 'Closing Auction (13:30 - 15:30)',
                'total'       => $phaseClosing->count(),
                'buys'        => $phaseClosing->where('side', 'BUY')->count(),
                'sells'       => $phaseClosing->where('side', 'SELL')->count(),
                'bias'        => $phaseClosing->where('side', 'BUY')->count() >= $phaseClosing->where('side', 'SELL')->count() ? 'BULLISH' : 'BEARISH',
                'description' => 'Institutional closing positioning, trend acceleration, and end-of-day rebalancing.',
            ],
        ];

        // Critical Turning Points (Top alerts by size or confluence)
        $turningPoints = $alerts->filter(fn($a) => in_array($a->alert_type, ['BLOCK_BUY', 'BLOCK_SELL', 'STRATEGY_BUY', 'STRATEGY_SELL']))
            ->sortByDesc(fn($a) => ($a->quantity ?: 0) + ($a->confluence_score ?: 0) * 100)
            ->take(8)
            ->map(function ($a) use ($close) {
                $p = (float) $a->price;
                $isBuy = ($a->side === 'BUY');
                if ($a->alert_type === 'BLOCK_SELL' || $a->alert_type === 'STACK_SELL') {
                    $significance = ($close > 0 && $close >= $p)
                        ? 'Institutional Ask Wall Swept / Conquered'
                        : 'Institutional Supply Wall Capped';
                } elseif ($a->alert_type === 'BLOCK_BUY' || $a->alert_type === 'STACK_BUY') {
                    $significance = ($close > 0 && $close >= $p)
                        ? 'Institutional Demand Wall Defended'
                        : 'Institutional Demand Wall Broken / Swept';
                } else {
                    $significance = $isBuy ? 'Bullish Strategy Trigger' : 'Bearish Strategy Trigger';
                }
                return [
                    'time'        => $a->alert_time,
                    'type'        => $a->alert_type,
                    'side'        => $a->side,
                    'price'       => $p,
                    'qty'         => (int) $a->quantity,
                    'title'       => $a->title,
                    'significance'=> $significance,
                ];
            })
            ->values();

        $buyPct = $total > 0 ? round(($buyCount / $total) * 100, 1) : 50.0;
        $sellPct = $total > 0 ? round(($sellCount / $total) * 100, 1) : 50.0;

        return [
            'total_alerts'       => $total,
            'buy_count'          => $buyCount,
            'sell_count'         => $sellCount,
            'buy_pct'            => $buyPct,
            'sell_pct'           => $sellPct,
            'absorption_bull_pct'=> $absorptionBullPct,
            'absorption_bear_pct'=> $absorptionBearPct,
            'swept_ask_count'    => $sweptAskCount,
            'defended_ask_count' => $defendedAskCount,
            'defended_bid_count' => $defendedBidCount,
            'swept_bid_count'    => $sweptBidCount,
            'stack_buy_count'    => $stackBuyCount,
            'stack_sell_count'   => $stackSellCount,
            'block_buy_count'    => $blockBuyCount,
            'block_sell_count'   => $blockSellCount,
            'strategy_buy_count' => $strategyBuy,
            'strategy_sell_count'=> $strategySell,
            'bid_liquidity_qty'  => $bidLiquidityQty,
            'ask_liquidity_qty'  => $askLiquidityQty,
            'key_bid_walls'      => $bidWalls,
            'key_ask_walls'      => $askWalls,
            'hourly_trend'       => $hourlyDistribution,
            'market_phases'      => $phases,
            'turning_points'     => $turningPoints,
        ];
    }

    /**
     * Analyze Trade Journal execution scorecard and outcomes.
     */
    protected function analyzeJournal(Collection $logs): array
    {
        $total = $logs->count();
        $targetHit  = $logs->where('status', 'TARGET_HIT')->count();
        $stoppedOut = $logs->where('status', 'STOPPED_OUT')->count();
        $active     = $logs->where('status', 'ACTIVE')->count();
        $reversed   = $logs->where('status', 'REVERSED')->count();
        $decided    = $targetHit + $stoppedOut;
        $winRate    = $decided > 0 ? round(($targetHit / $decided) * 100, 1) : 0.0;

        $netPnlPts    = (float) $logs->sum('pnl_points');
        $winningTrades = $logs->where('pnl_points', '>', 0);
        $losingTrades  = $logs->where('pnl_points', '<', 0);

        $grossWinPts  = (float) $winningTrades->sum('pnl_points');
        $grossLossPts = abs((float) $losingTrades->sum('pnl_points'));
        $profitFactor = $grossLossPts > 0 ? round($grossWinPts / $grossLossPts, 2) : ($grossWinPts > 0 ? 99.9 : 1.0);

        $avgMfe = $logs->avg('max_favorable_pts') ? round($logs->avg('max_favorable_pts'), 2) : 0.0;
        $avgMae = $logs->avg('max_adverse_pts') ? round($logs->avg('max_adverse_pts'), 2) : 0.0;
        $avgConfluence = $logs->avg('confluence_score') ? round($logs->avg('confluence_score'), 1) : 0.0;

        // Trade style breakdown (Option Buying vs Option Selling vs Futures)
        $callSetups = $logs->filter(fn($l) => str_contains($l->signal_type, 'BUY') || str_contains($l->action, 'CALL'))->count();
        $putSetups  = $logs->filter(fn($l) => str_contains($l->signal_type, 'SELL') || str_contains($l->action, 'PUT'))->count();

        // Microstructure Order Flow Conquest Analysis:
        // 1. Call Target Hits (Buyers winning on upside follow-through)
        $callHits  = $logs->filter(fn($l) => (str_contains($l->signal_type, 'BUY') || str_contains($l->action, 'CALL')) && $l->status === 'TARGET_HIT')->count();
        $callStops = $logs->filter(fn($l) => (str_contains($l->signal_type, 'BUY') || str_contains($l->action, 'CALL')) && $l->status === 'STOPPED_OUT')->count();
        // 2. Put / Resistance Target Hits (Sellers defending resistance walls)
        $putHits   = $logs->filter(fn($l) => (str_contains($l->signal_type, 'SELL') || str_contains($l->action, 'PUT') || str_contains($l->action, 'RESISTANCE')) && $l->status === 'TARGET_HIT')->count();
        // 3. Put / Resistance Stopped Out: When an Ask/Resistance wall is stopped out, BUYERS CONQUERED AND SWEPT OVERHEAD ASKS
        $putStops  = $logs->filter(fn($l) => (str_contains($l->signal_type, 'SELL') || str_contains($l->action, 'PUT') || str_contains($l->action, 'RESISTANCE')) && $l->status === 'STOPPED_OUT')->count();

        $bullConquests  = $callHits + $putStops;
        $bearConquests  = $putHits + $callStops;
        $totalConquests = $bullConquests + $bearConquests;
        $conquestBullPct = $totalConquests > 0 ? round(($bullConquests / $totalConquests) * 100, 1) : 50.0;

        // 0Click Webhook Dispatches
        $dispatchedCount = $logs->filter(fn($l) => str_contains((string)$l->notes, '0Click: SENT'))->count();
        $failedCount     = $logs->filter(fn($l) => str_contains((string)$l->notes, '0Click: FAILED'))->count();

        return [
            'total_signals'     => $total,
            'target_hit'        => $targetHit,
            'stopped_out'       => $stoppedOut,
            'active'            => $active,
            'reversed'          => $reversed,
            'win_rate_pct'      => $winRate,
            'net_pnl_pts'       => round($netPnlPts, 2),
            'gross_win_pts'     => round($grossWinPts, 2),
            'gross_loss_pts'    => round($grossLossPts, 2),
            'profit_factor'     => $profitFactor,
            'avg_mfe_pts'       => $avgMfe,
            'avg_mae_pts'       => $avgMae,
            'avg_confluence'    => $avgConfluence,
            'call_setups_count' => $callSetups,
            'put_setups_count'  => $putSetups,
            'bull_conquests'    => $bullConquests,
            'bear_conquests'    => $bearConquests,
            'conquest_bull_pct' => $conquestBullPct,
            'webhook_audit'     => [
                'dispatched' => $dispatchedCount,
                'failed'     => $failedCount,
                'rate_pct'   => ($dispatchedCount + $failedCount) > 0 ? round(($dispatchedCount / ($dispatchedCount + $failedCount)) * 100, 1) : 100.0,
            ],
            'recent_setups'     => $logs->take(10)->values(),
        ];
    }

    /**
     * Synthesize Value Area, Point of Control (POC), and Price Shelf Distribution.
     */
    protected function analyzeRangeAndValueArea(
        Collection $alerts,
        Collection $journalLogs,
        float $open,
        float $high,
        float $low,
        float $close,
        ?DailyTrend $trend
    ): array {
        // Collect all price points recorded in alerts, journal, and trend pivots
        $prices = collect();
        foreach ($alerts as $a) {
            if ($a->price > 0) $prices->push((float)$a->price);
        }
        foreach ($journalLogs as $j) {
            if ($j->spot_price > 0) $prices->push((float)$j->spot_price);
            if ($j->entry_price > 0) $prices->push((float)$j->entry_price);
        }

        // If prices are empty, seed with range milestones
        if ($prices->isEmpty()) {
            $prices->push($open, $close, ($high + $low) / 2);
        }

        // Point of Control (POC): Strike/Price node with highest density
        $roundedPrices = $prices->map(fn($p) => (int) (round($p / 50) * 50));
        $modeStrike = $roundedPrices->countBy()->sortDesc()->keys()->first();
        $poc = $modeStrike ? (float) $modeStrike : (float) (round(($high + $low) / 100) * 50);

        // Value Area boundaries (70% liquidity corridor)
        $range = max(1.0, $high - $low);
        $vah = round(min($high, $poc + ($range * 0.35)), 2);
        $val = round(max($low, $poc - ($range * 0.35)), 2);

        if ($vah <= $val) {
            $vah = round($high - ($range * 0.2), 2);
            $val = round($low + ($range * 0.2), 2);
        }

        // Dominant Price Shelf (Fair Value Zone where most action clustered)
        $dominantShelf = "₹" . number_format($val, 0) . " — ₹" . number_format($vah, 0);

        // Daily Trend Earth/Pivot reference if available
        $earthValue = (float) ($trend->earth_value ?? 0);
        $midPoint   = (float) ($trend->mid_point ?? (($high + $low) / 2));

        return [
            'day_high'          => $high,
            'day_low'           => $low,
            'day_open'          => $open,
            'day_close'         => $close,
            'day_range_pts'     => round($high - $low, 2),
            'poc_price'         => $poc,
            'vah_price'         => $vah,
            'val_price'         => $val,
            'dominant_shelf'    => $dominantShelf,
            'mid_point'         => round($midPoint, 2),
            'earth_value'       => $earthValue > 0 ? $earthValue : null,
            'value_area_width'  => round($vah - $val, 2),
        ];
    }

    /**
     * Synthesize "Who, When, How, Which, Range" Institutional Dominance.
     */
    protected function synthesizeDominance(
        array $alerts,
        array $journal,
        array $range,
        float $netChange,
        float $open,
        float $close
    ): array {
        // Multi-Factor Quantitative Institutional Dominance Model
        // 1. Intraday Price Extension & Trend Trajectory (40% weight)
        $pctChange = $open > 0 ? round(($netChange / $open) * 100, 2) : 0.0;
        if ($pctChange >= 0.75) {
            $priceTrendScore = 92.0; // Strong Bull Expansion
        } elseif ($pctChange >= 0.25) {
            $priceTrendScore = 80.0; // Moderate Bull Up Day
        } elseif ($pctChange > 0.05) {
            $priceTrendScore = 65.0; // Mild Bull Drift
        } elseif ($pctChange <= -0.75) {
            $priceTrendScore = 8.0;  // Strong Bear Liquidation
        } elseif ($pctChange <= -0.25) {
            $priceTrendScore = 20.0; // Moderate Bear Down Day
        } elseif ($pctChange < -0.05) {
            $priceTrendScore = 35.0; // Mild Bear Drift
        } else {
            $priceTrendScore = 50.0; // Neutral Flat
        }

        // Close location within the day's high-low range (Auction theory: closing near high = buyer accumulation)
        $rangeSpan = max(1.0, $range['day_high'] - $range['day_low']);
        $closeLocationScore = round(max(0.0, min(100.0, (($close - $range['day_low']) / $rangeSpan) * 100)), 1);
        $compositePriceScore = round(($priceTrendScore * 0.55) + ($closeLocationScore * 0.45), 1);

        // 2. Order Flow Conquest & Microstructure Liquidity Sweep (40% weight)
        // Auction mechanics: Liquidity absorption (swept ask walls & defended bid floors) vs trade journal conquest
        $absorptionScore = $alerts['absorption_bull_pct'] ?? ($alerts['buy_pct'] ?? 50.0);
        $conquestScore   = $journal['conquest_bull_pct'] ?? 50.0;
        $journalCount    = $journal['total_signals'] ?? 0;

        // Dynamic weighting: if journal has fewer than 6 trades, alert absorption dominates
        $journalWeight = min(0.35, $journalCount * 0.07);
        $alertWeight   = 1.0 - $journalWeight;
        $orderFlowScore = round(($absorptionScore * $alertWeight) + ($conquestScore * $journalWeight), 1);

        // 3. Value Area Migration & Capital Capture (20% weight)
        $valueAreaScore = 50.0;
        if ($range['poc_price'] >= $open) {
            $valueAreaScore += 25.0; // Value accepted higher
        } else {
            $valueAreaScore -= 25.0; // Value accepted lower
        }
        if ($close >= $range['poc_price']) {
            $valueAreaScore = min(100.0, $valueAreaScore + 15.0);
        }
        if ($journal['net_pnl_pts'] > 0) {
            $valueAreaScore = min(100.0, $valueAreaScore + 10.0);
        }

        $compositeBullScore = round(
            ($compositePriceScore * 0.40) +
            ($orderFlowScore * 0.40) +
            ($valueAreaScore * 0.20),
            1
        );
        $compositeBearScore = round(100.0 - $compositeBullScore, 1);

        // Archetype Classification & Narrative
        if ($compositeBullScore >= 68) {
            $archetype = 'INSTITUTIONAL ACCUMULATION & RESISTANCE SWEPT (STRONG BULL EXPANSION)';
            $archetypeTag = 'BULLISH DOMINANCE';
            $whoDescription = 'Aggressive institutional buyers dominated the session, driving NIFTY up +' . abs($netChange) . ' pts (' . ($pctChange >= 0 ? '+' : '') . $pctChange . '%). Overhead resting ask walls were repeatedly absorbed and swept through as buyers pushed value higher into the closing auction.';
        } elseif ($compositeBullScore >= 56) {
            $archetype = 'MODERATE BULLISH BUY-THE-DIP EXPANSION';
            $archetypeTag = 'MILD BULLISH';
            $whoDescription = 'Buyers held structural control with higher bid support, defending intraday pullbacks and pushing prices higher against passive resistance.';
        } elseif ($compositeBullScore <= 32) {
            $archetype = 'INSTITUTIONAL DISTRIBUTION & AGGRESSIVE SUPPLY CAPPING';
            $archetypeTag = 'BEARISH DOMINANCE';
            $whoDescription = 'Institutional sellers dominated the tape with heavy ask walls and stacked sell liquidity. Every attempt to rally was capped by resting supply, triggering long liquidation.';
        } elseif ($compositeBullScore <= 44) {
            $archetype = 'MODERATE BEARISH SELL-THE-RALLY BIAS';
            $archetypeTag = 'MILD BEARISH';
            $whoDescription = 'Sellers maintained the upper hand through persistent ask pressure, grinding prices lower despite occasional short covering bounces.';
        } else {
            $archetype = 'BALANCED TWO-WAY ROTATION (DELTA NEUTRAL EQUILIBRIUM)';
            $archetypeTag = 'NEUTRAL / CHOP';
            $whoDescription = 'Market participants were balanced in a two-way auction. Strong resting bid walls defended support while equal ask resistance capped rallies, creating ideal conditions for option sellers.';
        }

        // When Dominated
        $peakHour = collect($alerts['hourly_trend'])->sortByDesc('total')->first();
        $peakWindow = $peakHour && $peakHour['total'] > 0 ? $peakHour['hour'] . ' (' . $peakHour['total'] . ' alerts)' : 'Morning Open (09:15 - 10:15)';

        // Which Instruments & Strikes Dominated
        $topBidWall = ($alerts['key_bid_walls'] instanceof Collection) ? $alerts['key_bid_walls']->first() : ($alerts['key_bid_walls'][0] ?? null);
        $topAskWall = ($alerts['key_ask_walls'] instanceof Collection) ? $alerts['key_ask_walls']->first() : ($alerts['key_ask_walls'][0] ?? null);

        $magnetStrike = $range['poc_price'];
        $resistanceStrike = $topAskWall ? round($topAskWall['price'] / 50) * 50 : ($range['poc_price'] + 100);
        $supportStrike    = $topBidWall ? round($topBidWall['price'] / 50) * 50 : ($range['poc_price'] - 100);

        return [
            'bull_score_pct'      => $compositeBullScore,
            'bear_score_pct'      => $compositeBearScore,
            'dominant_side'       => $compositeBullScore >= 50 ? 'BULLS' : 'BEARS',
            'archetype'           => $archetype,
            'archetype_tag'       => $archetypeTag,
            'who' => [
                'verdict'     => $compositeBullScore >= 50 ? 'Institutional Buyers / Bullish Liquidity Providers' : 'Institutional Sellers / Bearish Liquidity Walls',
                'description' => $whoDescription,
                'aggression'  => $compositeBullScore >= 50 ? 'Aggressive buyers lifted the offers and repeatedly swept overhead ask walls' : 'Aggressive sellers hit the bids and broke structural support walls',
            ],
            'when' => [
                'peak_window'      => $peakWindow,
                'opening_verdict'  => $alerts['market_phases']['opening_drive']['bias'],
                'midday_verdict'   => $alerts['market_phases']['midday_rotation']['bias'],
                'closing_verdict'  => $alerts['market_phases']['closing_auction']['bias'],
            ],
            'how' => [
                'mechanism'        => 'Liquidity Stacks (' . ($alerts['stack_buy_count'] + $alerts['stack_sell_count']) . ' events) combined with Heavy Block Walls (' . ($alerts['block_buy_count'] + $alerts['block_sell_count']) . ' events)',
                'journal_win_rate' => $journal['win_rate_pct'] . '% Target Hit Rate (' . $journal['target_hit'] . ' Wins / ' . $journal['stopped_out'] . ' Losses)',
                'net_capture'      => ($journal['net_pnl_pts'] >= 0 ? '+' : '') . $journal['net_pnl_pts'] . ' NIFTY Index Pts',
            ],
            'which' => [
                'magnet_strike'     => 'NIFTY ' . (int)$magnetStrike . ' (Center of Gravity)',
                'resistance_strike' => 'NIFTY ' . (int)$resistanceStrike . ' CE (Heavy Ask Wall Supply)',
                'support_strike'    => 'NIFTY ' . (int)$supportStrike . ' PE (Heavy Bid Wall Demand)',
                'active_instrument' => 'NIFTY Index Futures & ATM Options',
            ],
            'range' => [
                'acceptance_corridor' => $range['dominant_shelf'],
                'poc'                 => '₹' . number_format($range['poc_price'], 2),
                'day_range'           => $range['day_range_pts'] . ' Pts (High: ₹' . number_format($range['day_high'], 2) . ' / Low: ₹' . number_format($range['day_low'], 2) . ')',
                'structural_mode'     => $range['day_range_pts'] > 120 ? 'Expanded Trend Day' : 'Consolidation / Value Area Rotation',
            ],
        ];
    }

    /**
     * Compute Tomorrow's Quantitative Support & Resistance Levels and Strategy Playbook.
     */
    protected function computeTomorrowLevelsAndPlaybook(
        array $range,
        array $alerts,
        array $dominance,
        ?DailyTrend $trend,
        float $closePrice
    ): array {
        $high = $range['day_high'];
        $low  = $range['day_low'];
        $close = $closePrice;
        $poc   = $range['poc_price'];
        $vah   = $range['vah_price'];
        $val   = $range['val_price'];

        // Standard Floor Pivot Math as anchor
        $pivot = round(($high + $low + $close) / 3, 2);

        // Microstructure-adjusted S/R Levels:
        // R3: Day High or Highest Ask Wall
        $r3 = round(max($high, $trend->atm_r_3 ?? ($pivot + ($high - $low))), 2);
        // R2: Value Area High or Major Ask Wall
        $firstAsk = ($alerts['key_ask_walls'] instanceof Collection) ? $alerts['key_ask_walls']->first() : ($alerts['key_ask_walls'][0] ?? null);
        $topAsk = $firstAsk ? (float)$firstAsk['price'] : $vah;
        $r2 = round(max($vah, $topAsk), 2);
        // R1: Immediate Resistance (POC / Midday Rejection node)
        $r1 = round(min($r2 - 10, max($close, $pivot + (($pivot - $low) * 0.5))), 2);

        // S1: Immediate Support (POC / Retest shelf)
        $s1 = round(max($val, min($close, $pivot - (($high - $pivot) * 0.5))), 2);
        // S2: Value Area Low or Major Bid Wall
        $firstBid = ($alerts['key_bid_walls'] instanceof Collection) ? $alerts['key_bid_walls']->first() : ($alerts['key_bid_walls'][0] ?? null);
        $topBid = $firstBid ? (float)$firstBid['price'] : $val;
        $s2 = round(min($val, $topBid), 2);
        // S3: Day Low or Lowest Bid Wall
        $s3 = round(min($low, $trend->atm_s_3 ?? ($pivot - ($high - $low))), 2);

        // Central Balance Equilibrium Line
        $centralPivot = round(($poc + $pivot) / 2, 2);

        $levels = [
            [
                'code'        => 'R3',
                'level_name'  => 'Major Ceiling / Day High',
                'price'       => $r3,
                'type'        => 'RESISTANCE',
                'strength'    => 'CRITICAL',
                'description' => 'Extreme upper supply boundary and breakout exhaustion node.',
            ],
            [
                'code'        => 'R2',
                'level_name'  => 'Value Area High (VAH) / Ask Wall',
                'price'       => $r2,
                'type'        => 'RESISTANCE',
                'strength'    => 'STRONG',
                'description' => 'Institutional resting ask block zone. Heavy selling expected on first test.',
            ],
            [
                'code'        => 'R1',
                'level_name'  => 'Immediate Supply Pivot',
                'price'       => $r1,
                'type'        => 'RESISTANCE',
                'strength'    => 'MODERATE',
                'description' => 'Intraday tactical resistance. Bull acceptance above this targets R2.',
            ],
            [
                'code'        => 'PIVOT',
                'level_name'  => 'Central Equilibrium / POC Anchor',
                'price'       => $centralPivot,
                'type'        => 'EQUILIBRIUM',
                'strength'    => 'ANCHOR',
                'description' => 'The institutional balance line for tomorrow. Bullish above, Bearish below.',
            ],
            [
                'code'        => 'S1',
                'level_name'  => 'Immediate Demand Pivot',
                'price'       => $s1,
                'type'        => 'SUPPORT',
                'strength'    => 'MODERATE',
                'description' => 'First line of defense for intraday dip buyers.',
            ],
            [
                'code'        => 'S2',
                'level_name'  => 'Value Area Low (VAL) / Bid Wall',
                'price'       => $s2,
                'type'        => 'SUPPORT',
                'strength'    => 'STRONG',
                'description' => 'Institutional resting bid wall zone. High probability bounce location.',
            ],
            [
                'code'        => 'S3',
                'level_name'  => 'Major Floor / Day Low',
                'price'       => $s3,
                'type'        => 'SUPPORT',
                'strength'    => 'CRITICAL',
                'description' => 'Structural invalidation boundary. A break below opens liquidity cascade.',
            ],
        ];

        // Actionable Tomorrow Trade Plans
        $plans = [
            'bull_plan' => [
                'title'       => 'Plan A: Bullish Long Continuation / Breakout',
                'trigger'     => "Price sustains above Central Pivot (₹" . number_format($centralPivot, 0) . ") with Bid Stack confirmation",
                'entry_zone'  => "₹" . number_format($centralPivot, 0) . " — ₹" . number_format($r1, 0),
                'target_1'    => "₹" . number_format($r2, 0) . " (VAH / Institutional Ask Shelf)",
                'target_2'    => "₹" . number_format($r3, 0) . " (Day High Expansion)",
                'stop_loss'   => "₹" . number_format($s1 - 5, 0) . " (Below immediate S1 shelf)",
                'strategy'    => "Buy ATM Call / Sell OTM Put on pullback to Pivot with Positive OBI (>+15%)",
            ],
            'bear_plan' => [
                'title'       => 'Plan B: Bearish Short Reversal / Breakdown',
                'trigger'     => "Price rejected at R1/R2 OR sustains below Central Pivot (₹" . number_format($centralPivot, 0) . ") with Ask Stack",
                'entry_zone'  => "₹" . number_format($centralPivot, 0) . " — ₹" . number_format($s1, 0),
                'target_1'    => "₹" . number_format($s2, 0) . " (VAL / Institutional Bid Shelf)",
                'target_2'    => "₹" . number_format($s3, 0) . " (Day Low Breakdown)",
                'stop_loss'   => "₹" . number_format($r1 + 5, 0) . " (Above immediate R1 shelf)",
                'strategy'    => "Buy ATM Put / Sell OTM Call on bounce into Resistance with Negative OBI (<-15%)",
            ],
            'range_plan' => [
                'title'       => 'Plan C: Neutral Value Area Mean Reversion (Option Selling)',
                'trigger'     => "Market opens inside Value Area (₹" . number_format($val, 0) . " — ₹" . number_format($vah, 0) . ") without directional drive",
                'entry_zone'  => "Fade R2 on Ask Wall defense; Fade S2 on Bid Wall defense",
                'target_1'    => "₹" . number_format($centralPivot, 0) . " (POC Return to Equilibrium)",
                'stop_loss'   => "Exit if price accepts 15 minutes outside VAH/VAL corridor",
                'strategy'    => "Deploy Short Strangle / Iron Condor (Sell " . (round($r3/50)*50) . " CE & " . (round($s3/50)*50) . " PE)",
            ],
        ];

        return [
            'central_pivot' => $centralPivot,
            'levels'        => $levels,
            'trade_plans'   => $plans,
            'summary_guidance' => "Tomorrow's institutional bias remains " . $dominance['archetype_tag'] . ". Use ₹" . number_format($centralPivot, 0) . " as the definitive intraday boundary: sustain above signals buyer control targeting ₹" . number_format($r2, 0) . "; failure below signals seller dominance down to ₹" . number_format($s2, 0) . ".",
        ];
    }

    /**
     * Extract Top Heavy Order Flow Block Transactions from Journal DOM setups and Depth Alerts.
     */
    protected function extractHeavyBlockTransactions(Collection $journalLogs, Collection $alerts): array
    {
        $blocks = [];

        // 1. Process Journal Opportunity Logs (contains 30-depth order book DOM walls, VPOC volume, CVD, OI flow)
        foreach ($journalLogs as $log) {
            $notes = json_decode($log->notes, true) ?? [];
            $factors = $notes['factors'] ?? [];
            $dom = $factors['dom_depth'] ?? [];
            $oiFlow = $factors['oi_flow'] ?? [];
            $trendingOi = $factors['trending_oi'] ?? [];

            $wall = $dom['wall'] ?? '';
            $vpocVol = (int)($dom['vpoc_volume'] ?? 0);
            $vpocPrice = isset($dom['vpoc_price']) ? (float)$dom['vpoc_price'] : null;
            $cvd = isset($dom['cvd']) ? (float)$dom['cvd'] : null;
            $pcr = isset($oiFlow['pcr']) ? (float)$oiFlow['pcr'] : null;
            $oiDetails = $oiFlow['details'] ?? ($trendingOi['details'] ?? '');

            $wallQty = 0;
            if (preg_match('/\((\d+[\d,]*)\)/', $wall, $matches)) {
                $wallQty = (int) str_replace(',', '', $matches[1]);
            }

            $isBuy = str_contains($wall, 'Bid') || str_contains($log->signal_type, 'BUY') || str_contains($log->action, 'BUY') || str_contains($log->action, 'SUPPORT');
            $side = $isBuy ? 'BID' : 'ASK';

            $timeStr = is_string($log->signal_time) ? $log->signal_time : ($log->signal_time ? $log->signal_time->format('H:i:s') : '00:00:00');

            $isOpening   = strcmp($timeStr, '09:15:00') >= 0 && strcmp($timeStr, '09:30:00') <= 0;
            $isClosing   = strcmp($timeStr, '15:15:00') >= 0 && strcmp($timeStr, '15:30:00') <= 0;
            $isCoreHours = strcmp($timeStr, '09:30:00') > 0 && strcmp($timeStr, '15:15:00') < 0;
            $isMidday    = strcmp($timeStr, '10:30:00') >= 0 && strcmp($timeStr, '13:30:00') <= 0;

            $sessionTag = 'CORE_HOURS';
            if ($isOpening) $sessionTag = 'OPENING';
            elseif ($isClosing) $sessionTag = 'CLOSING';
            elseif ($isMidday) $sessionTag = 'MIDDAY';

            $strike = $log->opt_strike ?: (int)(round($log->spot_price / 50) * 50);
            $effectiveQty = $wallQty > 0 ? $wallQty : ($vpocVol > 0 ? $vpocVol : 5000);

            $outcome = 'ACTIVE';
            $outcomeDesc = 'In-Play / Unbroken';
            if ($log->status === 'TARGET_HIT') {
                $outcome = 'DEFENDED';
                $outcomeDesc = 'Defended (Target Hit)';
            } elseif ($log->status === 'STOPPED_OUT') {
                $outcome = 'SWEPT';
                $outcomeDesc = 'Swept / Broken';
            }

            $blocks[] = [
                'id'            => 'sig_' . $log->id,
                'source'        => 'JOURNAL',
                'time'          => $timeStr,
                'strike'        => $strike,
                'instrument'    => $log->instrument ?: ($log->opt_strike ? "NIFTY {$log->opt_strike} " . ($log->opt_type ?: 'OPT') : 'NIFTY FUT'),
                'side'          => $side,
                'action'        => $log->action,
                'spot_price'    => (float) $log->spot_price,
                'wall_type'     => $wall ?: ($side === 'BID' ? 'Bid Support Wall' : 'Ask Resistance Wall'),
                'wall_qty'      => $effectiveQty,
                'vpoc_price'    => $vpocPrice,
                'vpoc_volume'   => $vpocVol,
                'cvd'           => $cvd,
                'pcr'           => $pcr,
                'oi_details'    => $oiDetails,
                'status'        => $log->status,
                'outcome'       => $outcome,
                'outcome_desc'  => $outcomeDesc,
                'is_opening'    => $isOpening,
                'is_closing'    => $isClosing,
                'is_core_hours' => $isCoreHours,
                'is_midday'     => $isMidday,
                'session_tag'   => $sessionTag,
            ];
        }

        // 2. Process Depth Alert Logs (logged directly from DOM 30-depth radar)
        foreach ($alerts as $alt) {
            if ($alt->quantity > 0) {
                $timeStr = is_string($alt->alert_time) ? $alt->alert_time : ($alt->alert_time ? $alt->alert_time->format('H:i:s') : '00:00:00');

                $isOpening   = strcmp($timeStr, '09:15:00') >= 0 && strcmp($timeStr, '09:30:00') <= 0;
                $isClosing   = strcmp($timeStr, '15:15:00') >= 0 && strcmp($timeStr, '15:30:00') <= 0;
                $isCoreHours = strcmp($timeStr, '09:30:00') > 0 && strcmp($timeStr, '15:15:00') < 0;
                $isMidday    = strcmp($timeStr, '10:30:00') >= 0 && strcmp($timeStr, '13:30:00') <= 0;

                $sessionTag = 'CORE_HOURS';
                if ($isOpening) $sessionTag = 'OPENING';
                elseif ($isClosing) $sessionTag = 'CLOSING';
                elseif ($isMidday) $sessionTag = 'MIDDAY';

                $strike = (int)(round($alt->price / 50) * 50);
                $side = $alt->side ?: (str_contains($alt->alert_type, 'BID') ? 'BID' : 'ASK');

                $blocks[] = [
                    'id'            => 'alt_' . $alt->id,
                    'source'        => 'ALERT',
                    'time'          => $timeStr,
                    'strike'        => $strike,
                    'instrument'    => $alt->instrument ?: 'NIFTY',
                    'side'          => $side,
                    'action'        => $alt->title ?: $alt->alert_type,
                    'spot_price'    => (float) $alt->price,
                    'wall_type'     => $alt->alert_type,
                    'wall_qty'      => (int) $alt->quantity,
                    'vpoc_price'    => null,
                    'vpoc_volume'   => 0,
                    'cvd'           => null,
                    'pcr'           => null,
                    'oi_details'    => $alt->message ?: 'Resting Liquidity Wall Alert',
                    'status'        => 'ACTIVE',
                    'outcome'       => 'DEFENDED',
                    'outcome_desc'  => 'Defended (Resting Wall)',
                    'is_opening'    => $isOpening,
                    'is_closing'    => $isClosing,
                    'is_core_hours' => $isCoreHours,
                    'is_midday'     => $isMidday,
                    'session_tag'   => $sessionTag,
                ];
            }
        }

        // Sort descending by wall quantity
        usort($blocks, fn($a, $b) => $b['wall_qty'] <=> $a['wall_qty']);

        $coreBlocks = array_values(array_filter($blocks, fn($b) => $b['is_core_hours']));
        $openingBlocks = array_values(array_filter($blocks, fn($b) => $b['is_opening']));
        $closingBlocks = array_values(array_filter($blocks, fn($b) => $b['is_closing']));
        $middayBlocks = array_values(array_filter($blocks, fn($b) => $b['is_midday']));

        return [
            'total_count'       => count($blocks),
            'all'               => $blocks,
            'top_20_all'        => array_slice($blocks, 0, 20),
            'top_20_core'       => array_slice($coreBlocks, 0, 20),
            'top_20_opening'    => array_slice($openingBlocks, 0, 20),
            'top_20_closing'    => array_slice($closingBlocks, 0, 20),
            'top_20_midday'     => array_slice($middayBlocks, 0, 20),
            'summary'           => [
                'max_block_qty'     => count($blocks) > 0 ? max(array_column($blocks, 'wall_qty')) : 0,
                'core_hours_count'  => count($coreBlocks),
                'opening_count'     => count($openingBlocks),
                'closing_count'     => count($closingBlocks),
                'bid_blocks_count'  => count(array_filter($blocks, fn($b) => $b['side'] === 'BID')),
                'ask_blocks_count'  => count(array_filter($blocks, fn($b) => $b['side'] === 'ASK')),
            ],
        ];
    }
}
