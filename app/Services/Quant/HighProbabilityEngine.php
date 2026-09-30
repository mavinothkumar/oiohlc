<?php

namespace App\Services\Quant;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HighProbabilityEngine
{
    /**
     * Nifty Lot size (75 for Nifty index options)
     */
    const LOT_SIZE = 75;

    /**
     * Known high-impact event dates or keywords (Budget, Election, RBI MPC).
     */
    protected array $blacklistedEventDates = [
        '2026-02-01', // Union Budget
        '2026-06-04', // Election counts
    ];

    /**
     * Analyze and generate the High-Probability Trade Signal for a specific date, expiry, and entry window.
     *
     * @param string|null $date Y-m-d (defaults to today/latest available)
     * @param string|null $expiry Y-m-d (defaults to nearest active expiry)
     * @param string $mode 'live' or 'history'
     * @param int $wingWidth Default 100 points
     * @param string $entryTimeStr Target entry time e.g. '09:45'
     * @return array
     */
    public function generateSignal(
        ?string $date = null,
        ?string $expiry = null,
        string $mode = 'live',
        int $wingWidth = 100,
        string $entryTimeStr = '09:45'
    ): array {
        $table = ($mode === 'history') ? 'option_chains_history' : getTableName('option_chains');

        // 1. Resolve date
        if (!$date) {
            $workingDay = DB::table('nse_working_days')->where('current', 1)->first()
                ?? DB::table('nse_working_days')->where('previous', 1)->orderByDesc('working_date')->first();
            $date = $workingDay ? $workingDay->working_date : today()->toDateString();
        }

        // 2. Resolve expiry
        if (!$expiry) {
            $expiry = DB::table($table)
                ->where('underlying_key', 'NSE_INDEX|Nifty 50')
                ->whereDate('captured_at', $date)
                ->orderBy('expiry')
                ->value('expiry');

            if (!$expiry) {
                $expiry = DB::table('nse_expiries')
                    ->where('trading_symbol', 'NIFTY')
                    ->where('instrument_type', 'OPT')
                    ->where('expiry_date', '>=', $date)
                    ->orderBy('expiry_date')
                    ->value('expiry_date') ?? $date;
            }
        }

        // 3. Resolve Dual Snapshots: (A) Entry Window (e.g. 09:45 AM) & (B) Latest Live
        $entryTimestamp = DB::table($table)
            ->where('underlying_key', 'NSE_INDEX|Nifty 50')
            ->where('expiry', $expiry)
            ->whereDate('captured_at', $date)
            ->whereTime('captured_at', '>=', $entryTimeStr . ':00')
            ->orderBy('captured_at', 'asc')
            ->value('captured_at');

        if (!$entryTimestamp) {
            // Check fallback in option_chains_history
            $entryTimestamp = DB::table('option_chains_history')
                ->where('underlying_key', 'NSE_INDEX|Nifty 50')
                ->where('expiry', $expiry)
                ->whereDate('captured_at', $date)
                ->whereTime('captured_at', '>=', $entryTimeStr . ':00')
                ->orderBy('captured_at', 'asc')
                ->value('captured_at');
            if ($entryTimestamp) {
                $table = 'option_chains_history';
            } else {
                $entryTimestamp = DB::table($table)
                    ->where('underlying_key', 'NSE_INDEX|Nifty 50')
                    ->where('expiry', $expiry)
                    ->whereDate('captured_at', $date)
                    ->min('captured_at');
            }
        }

        $latestTimestamp = DB::table($table)
            ->where('underlying_key', 'NSE_INDEX|Nifty 50')
            ->where('expiry', $expiry)
            ->whereDate('captured_at', $date)
            ->max('captured_at') ?? $entryTimestamp;

        // Fetch Entry Rows and Latest Rows
        $entryRows = collect();
        if ($entryTimestamp) {
            $entryRows = DB::table($table)
                ->where('underlying_key', 'NSE_INDEX|Nifty 50')
                ->where('expiry', $expiry)
                ->where('captured_at', $entryTimestamp)
                ->get()
                ->keyBy(fn($r) => ((int)$r->strike_price) . '_' . $r->option_type);
        }

        $latestRows = collect();
        if ($latestTimestamp) {
            $latestRows = DB::table($table)
                ->where('underlying_key', 'NSE_INDEX|Nifty 50')
                ->where('expiry', $expiry)
                ->where('captured_at', $latestTimestamp)
                ->get()
                ->keyBy(fn($r) => ((int)$r->strike_price) . '_' . $r->option_type);
        }

        // Extract Spot at Entry vs Latest
        $entrySpot = 22700.0;
        $liveSpot  = 22700.0;

        if ($entryRows->isNotEmpty()) {
            $firstSpot = $entryRows->firstWhere('underlying_spot_price', '>', 0);
            if ($firstSpot && $firstSpot->underlying_spot_price > 0) {
                $entrySpot = (float) $firstSpot->underlying_spot_price;
            }
        }

        if ($latestRows->isNotEmpty()) {
            $firstLiveSpot = $latestRows->firstWhere('underlying_spot_price', '>', 0);
            if ($firstLiveSpot && $firstLiveSpot->underlying_spot_price > 0) {
                $liveSpot = (float) $firstLiveSpot->underlying_spot_price;
            } else {
                $liveSpot = $entrySpot;
            }
        }

        // Always enforce round 100 multiples for optimal Nifty liquidity
        $atmStrike = (int) (round($entrySpot / 100) * 100);

        // Days to Expiry (DTE)
        $tradeDateObj = Carbon::parse($date);
        $expiryObj    = Carbon::parse($expiry);
        $daysDiff     = $tradeDateObj->diffInDays($expiryObj, false);
        $dte          = max($daysDiff, 0.2); // at least intraday 0.2 days

        // Process Strikes, OI, and IV from Entry snapshot
        $ceRows = $entryRows->filter(fn($r) => $r->option_type === 'CE');
        $peRows = $entryRows->filter(fn($r) => $r->option_type === 'PE');

        $totalCeOi = $ceRows->sum('oi');
        $totalPeOi = $peRows->sum('oi');
        $pcr       = $totalCeOi > 0 ? round($totalPeOi / $totalCeOi, 2) : 1.0;

        // Max OI Walls
        $maxCeStrike = (int) ($ceRows->sortByDesc('oi')->first()?->strike_price ?? ($atmStrike + 400));
        $maxPeStrike = (int) ($peRows->sortByDesc('oi')->first()?->strike_price ?? ($atmStrike - 400));

        // Implied Volatility
        $atmCeIv = (float) ($ceRows->get("{$atmStrike}_CE")?->iv ?? 0);
        $atmPeIv = (float) ($peRows->get("{$atmStrike}_PE")?->iv ?? 0);
        $avgIv   = ($atmCeIv > 0 && $atmPeIv > 0) ? (($atmCeIv + $atmPeIv) / 2.0) : 13.5;
        if ($avgIv < 5.0 || $avgIv > 60.0) {
            $avgIv = 13.5;
        }

        // Expected Move via ProbabilityEngine
        $expectedMove = ProbabilityEngine::expectedMove($entrySpot, $avgIv, $dte);

        // ══════════════════════════════════════════════════════════════
        // THE 4 QUANT FILTERS & CONFLUENCE SCORING
        // ══════════════════════════════════════════════════════════════
        $filters = [];
        $confluenceScore = 0;

        // Filter 1: Calendar / Macro Event Blacklist
        $isEventDay = in_array($date, $this->blacklistedEventDates);
        $calendarStatus = !$isEventDay;
        $filters['calendar'] = [
            'title'   => 'Macro & Event Risk',
            'passed'  => $calendarStatus,
            'points'  => $calendarStatus ? 25 : 0,
            'details' => $calendarStatus ? 'No high-impact macro black swan event today.' : 'ALERT: Major event day. Probability distributions unreliable.',
        ];
        $confluenceScore += $filters['calendar']['points'];

        // Filter 2: Volatility / IV Regime
        $volStatus = ($avgIv >= 10.5 && $avgIv <= 19.5);
        $filters['volatility'] = [
            'title'   => 'Volatility Regime (India VIX / IV)',
            'passed'  => $volStatus,
            'points'  => $volStatus ? 25 : ($avgIv <= 22 ? 15 : 0),
            'details' => "Current IV is {$avgIv}%. " . ($volStatus ? 'Optimal sweet spot for stable premium decay.' : 'Caution: High volatility increases tail breach risk.'),
        ];
        $confluenceScore += $filters['volatility']['points'];

        // Filter 3: OI Support & Resistance Walls
        $wallDistanceUpper = $maxCeStrike - $entrySpot;
        $wallDistanceLower = $entrySpot - $maxPeStrike;
        $wallsAdequate     = ($wallDistanceUpper >= ($expectedMove['daily_1sigma'] * 0.8)) &&
                             ($wallDistanceLower >= ($expectedMove['daily_1sigma'] * 0.8));

        $filters['oi_walls'] = [
            'title'   => 'Open Interest Wall Defense',
            'passed'  => $wallsAdequate,
            'points'  => $wallsAdequate ? 25 : 10,
            'details' => "Max Call OI at {$maxCeStrike} (+{$wallDistanceUpper} pts), Max Put OI at {$maxPeStrike} (-{$wallDistanceLower} pts).",
        ];
        $confluenceScore += $filters['oi_walls']['points'];

        // Filter 4: PCR Congruence (0.70 to 1.35 = balanced rangebound)
        $pcrBalanced = ($pcr >= 0.70 && $pcr <= 1.35);
        $filters['pcr'] = [
            'title'   => 'PCR Balance (Put/Call Ratio)',
            'passed'  => $pcrBalanced,
            'points'  => $pcrBalanced ? 25 : 10,
            'details' => "PCR is {$pcr}. " . ($pcrBalanced ? 'Balanced option flow. No directional runaway bias.' : 'Skewed: Strong directional pressure detected.'),
        ];
        $confluenceScore += $filters['pcr']['points'];

        // ══════════════════════════════════════════════════════════════
        // STRIKE SELECTION (Only Round 100 Multiples for Maximum Liquidity)
        // ══════════════════════════════════════════════════════════════
        $targetUpperStrike = null;
        $targetLowerStrike = null;

        // Candidate Call strikes above spot (strictly 100-multiples only)
        $candidateCes = $ceRows->filter(fn($r) => (float)$r->strike_price > $entrySpot && ((int)$r->strike_price % 100 === 0));
        if ($candidateCes->isNotEmpty()) {
            $bestCe = null;
            $bestDiff = 999.0;
            foreach ($candidateCes as $c) {
                $delta = abs((float) ($c->delta ?? 0));
                if ($delta == 0) {
                    $g = ProbabilityEngine::calculateGreeks($entrySpot, (float)$c->strike_price, $dte, $avgIv);
                    $delta = abs($g['delta_ce']);
                }
                $diff = abs($delta - 0.13);
                // If DTE >= 2, skip near-zero premium garbage (< ₹3)
                if ($dte >= 2 && (float)$c->ltp < 3.0) {
                    continue;
                }
                if ($diff < $bestDiff) {
                    $bestDiff = $diff;
                    $bestCe = $c;
                }
            }
            if ($bestCe) {
                $targetUpperStrike = (int) $bestCe->strike_price;
            }
        }

        if (!$targetUpperStrike) {
            $targetUpperStrike = (int) (ceil(max($expectedMove['upper_1_5sigma'], $entrySpot + ($expectedMove['daily_1sigma'] * 1.4)) / 100) * 100);
        }

        // Candidate Put strikes below spot (strictly 100-multiples only)
        $candidatePes = $peRows->filter(fn($r) => (float)$r->strike_price < $entrySpot && ((int)$r->strike_price % 100 === 0));
        if ($candidatePes->isNotEmpty()) {
            $bestPe = null;
            $bestDiff = 999.0;
            foreach ($candidatePes as $p) {
                $delta = abs((float) ($p->delta ?? 0));
                if ($delta == 0) {
                    $g = ProbabilityEngine::calculateGreeks($entrySpot, (float)$p->strike_price, $dte, $avgIv);
                    $delta = abs($g['delta_pe']);
                }
                $diff = abs($delta - 0.13);
                if ($dte >= 2 && (float)$p->ltp < 3.0) {
                    continue;
                }
                if ($diff < $bestDiff) {
                    $bestDiff = $diff;
                    $bestPe = $p;
                }
            }
            if ($bestPe) {
                $targetLowerStrike = (int) $bestPe->strike_price;
            }
        }

        if (!$targetLowerStrike) {
            $targetLowerStrike = (int) (floor(min($expectedMove['lower_1_5sigma'], $entrySpot - ($expectedMove['daily_1sigma'] * 1.4)) / 100) * 100);
        }

        // Protective Hedge Wings (Always round 100 multiples)
        $wingWidth = (int) (round(max(100, $wingWidth) / 100) * 100);
        $upperHedgeStrike = $targetUpperStrike + $wingWidth;
        $lowerHedgeStrike = $targetLowerStrike - $wingWidth;

        // ══════════════════════════════════════════════════════════════
        // DUAL PRICING: EXACT ENTRY LTP (09:45 AM) VS LIVE LTP (NOW)
        // ══════════════════════════════════════════════════════════════
        $shortCeEntry = (float) ($entryRows->get("{$targetUpperStrike}_CE")?->ltp ?? $this->estimateOptionPrice($entrySpot, $targetUpperStrike, 'CE', $dte, $avgIv));
        $longCeEntry  = (float) ($entryRows->get("{$upperHedgeStrike}_CE")?->ltp ?? $this->estimateOptionPrice($entrySpot, $upperHedgeStrike, 'CE', $dte, $avgIv));
        $shortPeEntry = (float) ($entryRows->get("{$targetLowerStrike}_PE")?->ltp ?? $this->estimateOptionPrice($entrySpot, $targetLowerStrike, 'PE', $dte, $avgIv));
        $longPeEntry  = (float) ($entryRows->get("{$lowerHedgeStrike}_PE")?->ltp ?? $this->estimateOptionPrice($entrySpot, $lowerHedgeStrike, 'PE', $dte, $avgIv));

        $shortCeLive = (float) ($latestRows->get("{$targetUpperStrike}_CE")?->ltp ?? $shortCeEntry);
        $longCeLive  = (float) ($latestRows->get("{$upperHedgeStrike}_CE")?->ltp ?? $longCeEntry);
        $shortPeLive = (float) ($latestRows->get("{$targetLowerStrike}_PE")?->ltp ?? $shortPeEntry);
        $longPeLive  = (float) ($latestRows->get("{$lowerHedgeStrike}_PE")?->ltp ?? $longPeEntry);

        // Greeks for Short strikes at Entry
        $greeksUpper = ProbabilityEngine::calculateGreeks($entrySpot, $targetUpperStrike, $dte, $avgIv);
        $greeksLower = ProbabilityEngine::calculateGreeks($entrySpot, $targetLowerStrike, $dte, $avgIv);

        // Net Entry Credit
        $netCeCredit = max(0.5, $shortCeEntry - $longCeEntry);
        $netPeCredit = max(0.5, $shortPeEntry - $longPeEntry);
        $totalEntryCredit = round($netCeCredit + $netPeCredit, 2);

        // Current Net Premium Live
        $liveNetPremium = round(max(0.1, ($shortCeLive - $longCeLive) + ($shortPeLive - $longPeLive)), 2);

        // Live Running P&L
        $livePnlPoints = round($totalEntryCredit - $liveNetPremium, 2);
        $livePnlInr    = round($livePnlPoints * self::LOT_SIZE, 0);

        // Target Profit (Taking 60% of credit) and Hard Stop Loss (+50% on sold entry premium)
        $targetProfitPoints = round($totalEntryCredit * 0.60, 2);
        $targetExitPremium  = round($totalEntryCredit * 0.40, 2); // Exit when premium decays to this
        $ceStopLossPrice    = round($shortCeEntry * 1.50, 1);
        $peStopLossPrice    = round($shortPeEntry * 1.50, 1);

        // Plan Stop-Loss Risk per lot
        $worstLegLossPoints = max($shortCeEntry * 0.50, $shortPeEntry * 0.50);
        $planRiskPerLot     = round($worstLegLossPoints * self::LOT_SIZE, 0);
        $targetProfitPerLot = round($targetProfitPoints * self::LOT_SIZE, 0);
        $maxProfitPerLot    = round($totalEntryCredit * self::LOT_SIZE, 0);

        // Catastrophic Disaster Gap Cap
        $catastrophicGapRisk = round(($wingWidth - $totalEntryCredit) * self::LOT_SIZE, 0);
        $planRiskReward = ($planRiskPerLot > 0) ? round($targetProfitPerLot / $planRiskPerLot, 2) : 1.2;

        // Individual leg running PnLs
        $shortCePnl = round(($shortCeEntry - $shortCeLive) * self::LOT_SIZE, 0);
        $hedgeCePnl = round(($longCeLive - $longCeEntry) * self::LOT_SIZE, 0);
        $shortPePnl = round(($shortPeEntry - $shortPeLive) * self::LOT_SIZE, 0);
        $hedgePePnl = round(($longPeLive - $longPeEntry) * self::LOT_SIZE, 0);

        // Probability of Profit
        $condorPop = ProbabilityEngine::condorProbabilityOfProfit(
            $entrySpot,
            $targetLowerStrike,
            $targetUpperStrike,
            $dte,
            $avgIv
        );

        if ($condorPop < 70) {
            $condorPop = round(100 - (abs($greeksUpper['delta_ce']) * 100 + abs($greeksLower['delta_pe']) * 100), 1);
        }

        // Trade Status & Readiness
        $isTradeReady = ($confluenceScore >= 75 && $condorPop >= 78.0);
        $badge = $isTradeReady ? '🟢 HIGH CONFIDENCE ENTRY' : ($confluenceScore >= 60 ? '🟡 CAUTION / WAIT' : '🔴 NO TRADE DAY');
        $badgeColor = $isTradeReady ? 'emerald' : ($confluenceScore >= 60 ? 'amber' : 'rose');

        return [
            'trade_date'       => $date,
            'expiry'           => $expiry,
            'entry_time'       => $entryTimestamp ? Carbon::parse($entryTimestamp)->format('h:i A') : $entryTimeStr,
            'latest_time'      => $latestTimestamp ? Carbon::parse($latestTimestamp)->format('h:i:s A') : 'Live',
            'wing_width'       => $wingWidth,
            'entry_spot'       => $entrySpot,
            'live_spot'        => $liveSpot,
            'spot_change'      => round($liveSpot - $entrySpot, 2),
            'atm_strike'       => $atmStrike,
            'dte'              => $dte,
            'iv'               => $avgIv,
            'pcr'              => $pcr,
            'confluence_score' => $confluenceScore,
            'is_trade_ready'   => $isTradeReady,
            'badge'            => $badge,
            'badge_color'      => $badgeColor,
            'pop_pct'          => $condorPop,
            'expected_move'    => $expectedMove,
            'filters'          => $filters,
            'strategy_name'    => 'Nifty 50 High-Probability Iron Condor (Defined Risk)',
            'legs' => [
                'short_ce' => [
                    'action'   => 'SELL',
                    'symbol'   => "NIFTY {$targetUpperStrike} CE",
                    'strike'   => $targetUpperStrike,
                    'type'     => 'CE',
                    'delta'    => $greeksUpper['delta_ce'],
                    'entry_ltp'=> $shortCeEntry,
                    'live_ltp' => $shortCeLive,
                    'sl_ltp'   => $ceStopLossPrice,
                    'pnl_inr'  => $shortCePnl,
                    'sl_status'=> $shortCeLive >= $ceStopLossPrice ? 'SL_HIT' : ($shortCeLive >= ($shortCeEntry * 1.30) ? 'WARNING' : 'SAFE'),
                    'role'     => 'Income Leg (Short Call)',
                ],
                'hedge_ce' => [
                    'action'   => 'BUY',
                    'symbol'   => "NIFTY {$upperHedgeStrike} CE",
                    'strike'   => $upperHedgeStrike,
                    'type'     => 'CE',
                    'entry_ltp'=> $longCeEntry,
                    'live_ltp' => $longCeLive,
                    'pnl_inr'  => $hedgeCePnl,
                    'role'     => 'Protection Wing (Capped Risk)',
                ],
                'short_pe' => [
                    'action'   => 'SELL',
                    'symbol'   => "NIFTY {$targetLowerStrike} PE",
                    'strike'   => $targetLowerStrike,
                    'type'     => 'PE',
                    'delta'    => $greeksLower['delta_pe'],
                    'entry_ltp'=> $shortPeEntry,
                    'live_ltp' => $shortPeLive,
                    'sl_ltp'   => $peStopLossPrice,
                    'pnl_inr'  => $shortPePnl,
                    'sl_status'=> $shortPeLive >= $peStopLossPrice ? 'SL_HIT' : ($shortPeLive >= ($shortPeEntry * 1.30) ? 'WARNING' : 'SAFE'),
                    'role'     => 'Income Leg (Short Put)',
                ],
                'hedge_pe' => [
                    'action'   => 'BUY',
                    'symbol'   => "NIFTY {$lowerHedgeStrike} PE",
                    'strike'   => $lowerHedgeStrike,
                    'type'     => 'PE',
                    'entry_ltp'=> $longPeEntry,
                    'live_ltp' => $longPeLive,
                    'pnl_inr'  => $hedgePePnl,
                    'role'     => 'Protection Wing (Capped Risk)',
                ],
            ],
            'financials' => [
                'lot_size'               => self::LOT_SIZE,
                'wing_width'             => $wingWidth,
                'net_credit_points'      => $totalEntryCredit,
                'current_net_premium'    => $liveNetPremium,
                'live_pnl_points'        => $livePnlPoints,
                'live_pnl_inr'           => $livePnlInr,
                'target_profit_per_lot'  => $targetProfitPerLot,
                'max_profit_per_lot'     => $maxProfitPerLot,
                'plan_risk_per_lot'      => $planRiskPerLot,
                'catastrophic_gap_risk'  => $catastrophicGapRisk,
                'plan_risk_reward'       => $planRiskReward,
                'target_credit_exit'     => $targetExitPremium,
                'est_margin_per_lot'     => '₹48,000 (Hedged Benefit)',
            ],
            'execution_plan' => [
                'entry_window'       => "Exact Snapshot at {$entryTimeStr} AM",
                'exit_target'        => "Exit all legs when combined premium drops to ₹{$targetExitPremium} (+₹{$targetProfitPerLot}/lot target).",
                'exit_stop_loss'     => "If Call hits ₹{$ceStopLossPrice} or Put hits ₹{$peStopLossPrice}, exit that tested leg immediately (max loss capped at -₹{$planRiskPerLot}/lot).",
                'exit_time'          => '15:10 PM IST (Mandatory intraday square-off before 3 PM gamma spike).',
                'adjustment_rule'    => "If Spot rises towards {$targetUpperStrike} and CE is threatened, roll PE strike up by 100 points to lock in profit on winning leg and collect extra credit.",
            ]
        ];
    }

    /**
     * Approximate Black-Scholes pricing fallback if live LTP is missing for deep OTM wings.
     */
    protected function estimateOptionPrice(float $spot, float $strike, string $type, float $dte, float $iv): float
    {
        $greeks = ProbabilityEngine::calculateGreeks($spot, $strike, $dte, $iv);
        $t = max($dte, 0.08) / 365.0;
        $r = 0.065;

        if ($type === 'CE') {
            $price = $spot * ProbabilityEngine::cdf($greeks['d1']) -
                     $strike * exp(-$r * $t) * ProbabilityEngine::cdf($greeks['d2']);
        } else {
            $price = $strike * exp(-$r * $t) * ProbabilityEngine::cdf(-$greeks['d2']) -
                     $spot * ProbabilityEngine::cdf(-$greeks['d1']);
        }

        return round(max(1.5, $price), 2);
    }
}
