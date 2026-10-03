<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StrategyPremiumAnalyticsController extends Controller
{
    /**
     * Main View.
     */
    public function index(Request $request): View
    {
        $symbol = strtoupper($request->input('symbol', 'NIFTY'));
        $table = $this->resolveOptionChainsTable();

        // 1. Available Dates from nse_working_days (instant index lookup)
        $availableDates = [];
        if (SchemaHasTable('nse_working_days')) {
            $availableDates = DB::table('nse_working_days')
                ->where('working_date', '<=', today()->toDateString())
                ->orderByDesc('working_date')
                ->take(30)
                ->pluck('working_date')
                ->toArray();
        }
        if (empty($availableDates)) {
            $availableDates = [today()->toDateString()];
        }

        $defaultDate = !empty($availableDates) ? $availableDates[0] : today()->toDateString();
        $selectedDate = $request->input('date', $defaultDate);

        // 2. Available Expiries from nse_expiries (instant lookup)
        $availableExpiries = [];
        if (SchemaHasTable('nse_expiries')) {
            $availableExpiries = DB::table('nse_expiries')
                ->where('trading_symbol', $symbol)
                ->where('instrument_type', 'OPT')
                ->where('expiry_date', '>=', $selectedDate)
                ->orderBy('expiry_date')
                ->take(10)
                ->pluck('expiry_date')
                ->toArray();
        }

        if (empty($availableExpiries)) {
            $availableExpiries = DB::table($table)
                ->where('trading_symbol', $symbol)
                ->where('captured_at', '>=', "{$selectedDate} 09:15:00")
                ->where('captured_at', '<=', "{$selectedDate} 15:30:00")
                ->distinct()
                ->orderBy('expiry')
                ->take(10)
                ->pluck('expiry')
                ->toArray();
        }

        $defaultExpiry = !empty($availableExpiries) ? $availableExpiries[0] : today()->toDateString();
        $selectedExpiry = $request->input('expiry', $defaultExpiry);

        // 3. Available Strategies from /backtest/strategies
        $backtestStrategies = DB::table('backtest_strategies')
            ->where('is_active', true)
            ->orWhereNotNull('name')
            ->orderBy('name')
            ->get();

        $selectedStrategyId = $request->input('strategy_id', $backtestStrategies->first()?->id ?? 1);
        $mode = $request->input('mode', 'strategy'); // 'strategy' or 'manual'

        // 4. All Strikes for Manual Dropdowns (Fast timestamp-ranged lookup)
        $allStrikes = DB::table($table)
            ->where('trading_symbol', $symbol)
            ->where('expiry', $selectedExpiry)
            ->where('captured_at', '>=', "{$selectedDate} 09:15:00")
            ->where('captured_at', '<=', "{$selectedDate} 09:30:00")
            ->distinct()
            ->orderBy('strike_price')
            ->pluck('strike_price')
            ->map(fn($s) => (float)$s)
            ->unique()
            ->values()
            ->toArray();

        // If no strikes found on opening window, expand to trading day range
        if (empty($allStrikes)) {
            $allStrikes = DB::table($table)
                ->where('trading_symbol', $symbol)
                ->where('expiry', $selectedExpiry)
                ->where('captured_at', '>=', "{$selectedDate} 09:15:00")
                ->where('captured_at', '<=', "{$selectedDate} 15:30:00")
                ->distinct()
                ->orderBy('strike_price')
                ->pluck('strike_price')
                ->map(fn($s) => (float)$s)
                ->unique()
                ->values()
                ->toArray();
        }

        // 5. Chart Height & End Time
        $chartHeight = (int)$request->input('chart_height', 650);
        $endTime = $request->input('end_time', '15:30');
        $startTime = $request->input('start_time', '09:15');

        // 6. Custom ATM Override (Optional)
        $customAtm = $request->input('custom_atm');
        $customAtm = ($customAtm !== null && $customAtm !== '' && is_numeric($customAtm)) ? (float)$customAtm : null;

        // 7. Manual strikes inputs
        $manualCallStrikes = $request->input('call_strikes', []);
        $manualCallLots = $request->input('call_lots', []);
        $manualPutStrikes = $request->input('put_strikes', []);
        $manualPutLots = $request->input('put_lots', []);

        // 8. Calculate Analytics Data
        $analytics = $this->computeAnalytics(
            mode: $mode,
            strategyId: (int)$selectedStrategyId,
            symbol: $symbol,
            selectedDate: $selectedDate,
            selectedExpiry: $selectedExpiry,
            startTime: $startTime,
            endTime: $endTime,
            manualCallStrikes: $manualCallStrikes,
            manualCallLots: $manualCallLots,
            manualPutStrikes: $manualPutStrikes,
            manualPutLots: $manualPutLots,
            table: $table,
            customAtm: $customAtm
        );

        $calculatedAtm = $analytics['calculated_atm'] ?? ($analytics['atm_strike'] ?? null);
        $atmStrike = $analytics['atm_strike'] ?? $calculatedAtm;

        return view('strategy-premium-analytics.index', compact(
            'symbol',
            'availableDates',
            'selectedDate',
            'availableExpiries',
            'selectedExpiry',
            'backtestStrategies',
            'selectedStrategyId',
            'mode',
            'allStrikes',
            'chartHeight',
            'startTime',
            'endTime',
            'manualCallStrikes',
            'manualCallLots',
            'manualPutStrikes',
            'manualPutLots',
            'customAtm',
            'calculatedAtm',
            'atmStrike',
            'analytics'
        ));
    }

    /**
     * API: Fetch recalculated data dynamically.
     */
    public function getData(Request $request): JsonResponse
    {
        $table = $this->resolveOptionChainsTable();
        $customAtm = $request->input('custom_atm');
        $customAtm = ($customAtm !== null && $customAtm !== '' && is_numeric($customAtm)) ? (float)$customAtm : null;

        $analytics = $this->computeAnalytics(
            mode: $request->input('mode', 'strategy'),
            strategyId: (int)$request->input('strategy_id', 1),
            symbol: strtoupper($request->input('symbol', 'NIFTY')),
            selectedDate: $request->input('date', today()->toDateString()),
            selectedExpiry: $request->input('expiry', today()->toDateString()),
            startTime: $request->input('start_time', '09:15'),
            endTime: $request->input('end_time', '15:30'),
            manualCallStrikes: $request->input('call_strikes', []),
            manualCallLots: $request->input('call_lots', []),
            manualPutStrikes: $request->input('put_strikes', []),
            manualPutLots: $request->input('put_lots', []),
            table: $table,
            customAtm: $customAtm
        );

        return response()->json($analytics);
    }

    /**
     * Core Computation Engine for Combined Premium & Greek Velocity.
     */
    protected function computeAnalytics(
        string $mode,
        int $strategyId,
        string $symbol,
        string $selectedDate,
        string $selectedExpiry,
        string $startTime,
        string $endTime,
        array $manualCallStrikes,
        array $manualCallLots,
        array $manualPutStrikes,
        array $manualPutLots,
        string $table,
        ?float $customAtm = null
    ): array {
        $step = $symbol === 'BANKNIFTY' ? 100.0 : 50.0;

        // 1. Resolve ATM spot price at market open (09:15)
        $startDt = Carbon::parse("{$selectedDate} {$startTime}:00");
        $endDt = Carbon::parse("{$selectedDate} {$endTime}:00");

        $openRow = DB::table($table)
            ->where('trading_symbol', $symbol)
            ->where('captured_at', '>=', $startDt->toDateTimeString())
            ->where('captured_at', '<=', $startDt->copy()->addMinutes(15)->toDateTimeString())
            ->orderBy('captured_at')
            ->first();

        if (!$openRow) {
            $openRow = DB::table($table)
                ->where('trading_symbol', $symbol)
                ->where('captured_at', '>=', $startDt->toDateTimeString())
                ->where('captured_at', '<=', $endDt->toDateTimeString())
                ->orderBy('captured_at')
                ->first();
        }

        $openSpot = (float)($openRow->underlying_spot_price ?? 22550.0);
        $calculatedAtm = round($openSpot / $step) * $step;
        $atmStrike = ($customAtm !== null && $customAtm > 0) ? $customAtm : $calculatedAtm;

        // 2. Resolve Strategy Legs (Strikes, Types, Sides, and Lots)
        $resolvedLegs = [];
        $strategyName = 'Custom Multi-Strike';

        if ($mode === 'strategy') {
            $stratRecord = DB::table('backtest_strategies')->find($strategyId);
            if ($stratRecord) {
                $strategyName = $stratRecord->name;
                $def = json_decode($stratRecord->definition, true);
                $rawLegs = $def['legs'] ?? [];

                foreach ($rawLegs as $idx => $leg) {
                    $lots = max(1, (int)($leg['lots'] ?? 1));
                    $side = strtoupper($leg['side'] ?? 'SELL');
                    $opt = strtoupper($leg['option_type'] ?? 'CE');
                    $mon = strtoupper($leg['moneyness'] ?? 'ATM');
                    $offset = (float)($leg['strike_offset'] ?? 0);

                    // Canonical BasketBuilder formula: ITM = -step - offset, OTM = +step + offset, ATM = 0
                    $moneynessAdjustment = match ($mon) {
                        'ITM' => -$step - $offset,
                        'OTM' => $step + $offset,
                        default => 0.0,
                    };

                    $strike = (float)($atmStrike + $moneynessAdjustment);

                    $resolvedLegs[] = [
                        'leg_id' => $idx + 1,
                        'strike' => (float)$strike,
                        'option_type' => $opt,
                        'side' => $side,
                        'lots' => $lots,
                        'moneyness' => $mon,
                        'offset' => (int)$offset,
                        'label' => "{$side} {$lots}x " . number_format($strike, 0, '', '') . " {$opt}",
                    ];
                }
            }
        }

        // If manual mode or strategy had no legs, resolve manual selections
        if ($mode === 'manual' || empty($resolvedLegs)) {
            $strategyName = 'Manual Multi-Strike Basket';
            $idx = 1;
            // CE Legs
            foreach ($manualCallStrikes as $i => $s) {
                if ($s) {
                    $lots = isset($manualCallLots[$i]) ? max(1, (int)$manualCallLots[$i]) : 1;
                    $resolvedLegs[] = [
                        'leg_id' => $idx++,
                        'strike' => (float)$s,
                        'option_type' => 'CE',
                        'side' => 'SELL',
                        'lots' => $lots,
                        'moneyness' => $s == $atmStrike ? 'ATM' : ($s > $atmStrike ? 'OTM' : 'ITM'),
                        'offset' => (int)abs($s - $atmStrike),
                        'label' => "SELL {$lots}x {$s} CE",
                    ];
                }
            }
            // PE Legs
            foreach ($manualPutStrikes as $i => $s) {
                if ($s) {
                    $lots = isset($manualPutLots[$i]) ? max(1, (int)$manualPutLots[$i]) : 1;
                    $resolvedLegs[] = [
                        'leg_id' => $idx++,
                        'strike' => (float)$s,
                        'option_type' => 'PE',
                        'side' => 'SELL',
                        'lots' => $lots,
                        'moneyness' => $s == $atmStrike ? 'ATM' : ($s < $atmStrike ? 'OTM' : 'ITM'),
                        'offset' => (int)abs($s - $atmStrike),
                        'label' => "SELL {$lots}x {$s} PE",
                    ];
                }
            }
        }

        // Fallback default: If nothing selected, default to ATM Straddle (1x CE, 1x PE)
        if (empty($resolvedLegs)) {
            $strategyName = 'ATM Straddle (Default)';
            $resolvedLegs = [
                ['leg_id' => 1, 'strike' => (float)$atmStrike, 'option_type' => 'CE', 'side' => 'SELL', 'lots' => 1, 'moneyness' => 'ATM', 'offset' => 0, 'label' => "SELL 1x {$atmStrike} CE"],
                ['leg_id' => 2, 'strike' => (float)$atmStrike, 'option_type' => 'PE', 'side' => 'SELL', 'lots' => 1, 'moneyness' => 'ATM', 'offset' => 0, 'label' => "SELL 1x {$atmStrike} PE"],
            ];
        }

        // 3. Query Option Chains for all resolved strikes
        $strikesFloats = array_unique(array_map(fn($s) => (float)$s, array_column($resolvedLegs, 'strike')));
        $strikesFormatted = array_map(fn($s) => number_format((float)$s, 2, '.', ''), $strikesFloats);
        $allStrikeVariants = array_values(array_unique(array_merge($strikesFloats, $strikesFormatted)));

        $isMysql = DB::connection()->getDriverName() === 'mysql';
        $fromClause = ($isMysql && $table === 'option_chains_history')
            ? DB::raw("`{$table}` USE INDEX (option_chains_captured_at_index)")
            : $table;

        $query = DB::table($fromClause)
            ->select([
                'captured_at',
                'strike_price',
                'option_type',
                'ltp',
                'volume',
                'diff_oi',
                'delta',
                'gamma',
                'theta',
                'vega',
                'iv',
                'underlying_spot_price',
            ])
            ->where('trading_symbol', $symbol)
            ->whereIn('strike_price', $allStrikeVariants)
            ->where('captured_at', '>=', $startDt->toDateTimeString())
            ->where('captured_at', '<=', $endDt->toDateTimeString())
            ->orderBy('captured_at');

        if (!empty($selectedExpiry)) {
            $query->where('expiry', $selectedExpiry);
        }

        $rawRows = $query->get();

        // 4. Index rows by captured_at and strike_price_option_type
        $groupedByTime = [];
        foreach ($rawRows as $r) {
            $tKey = $r->captured_at;
            $strikeKey = (string)(float)$r->strike_price;
            $optType = strtoupper(trim($r->option_type));
            $legKey = "{$strikeKey}_{$optType}";
            if (!isset($groupedByTime[$tKey])) {
                $groupedByTime[$tKey] = [
                    'captured_at' => $r->captured_at,
                    'spot' => (float)($r->underlying_spot_price ?? $openSpot),
                    'contracts' => [],
                ];
            }
            $groupedByTime[$tKey]['contracts'][$legKey] = $r;
        }

        ksort($groupedByTime);

        // 5. Build Combined Time-Series & Progressive Metrics
        $labels = [];
        $combinedLtp = [];
        $combinedVwap = [];
        $combinedOiVwap = [];
        $netOiChangeSeries = [];
        $combinedDeltaSeries = [];
        $combinedGammaSeries = [];
        $combinedThetaSeries = [];
        $combinedVegaSeries = [];
        $combinedIvSeries = [];

        $cumulativePV = 0.0;
        $cumulativeVol = 0.0;
        $cumulativeOIPV = 0.0;
        $cumulativeOIWeight = 0.0;
        $runningNetOI = 0.0;

        $progressionTable = [];
        $baseTimestamp = null;
        $basePremium = 0.0;
        $baseDelta = 0.0;
        $baseTheta = 0.0;
        $baseGamma = 0.0;
        $baseVega = 0.0;
        $baseIv = 0.0;

        $totalLotsAcrossLegs = array_sum(array_column($resolvedLegs, 'lots'));

        foreach ($groupedByTime as $tKey => $slot) {
            $timeLabel = Carbon::parse($tKey)->format('H:i');
            $labels[] = $timeLabel;

            $slotLtp = 0.0;
            $slotVol = 0.0;
            $slotDiffOi = 0.0;
            $slotDelta = 0.0;
            $slotGamma = 0.0;
            $slotTheta = 0.0;
            $slotVega = 0.0;
            $slotIvSum = 0.0;

            foreach ($resolvedLegs as $leg) {
                $strikeKey = (string)(float)$leg['strike'];
                $optType = strtoupper(trim($leg['option_type']));
                $key = "{$strikeKey}_{$optType}";
                $contract = $slot['contracts'][$key] ?? null;

                $ltp = (float)($contract->ltp ?? 0.0);
                $vol = (int)($contract->volume ?? 0);
                $diffOi = (float)($contract->diff_oi ?? 0.0);
                $delta = (float)($contract->delta ?? 0.0);
                $gamma = (float)($contract->gamma ?? 0.0);
                $theta = (float)($contract->theta ?? 0.0);
                $vega = (float)($contract->vega ?? 0.0);
                $iv = (float)($contract->iv ?? 0.0);

                $sign = $leg['side'] === 'BUY' ? 1.0 : -1.0;
                $lots = $leg['lots'];

                // Lots-weighted aggregations
                $slotLtp += ($lots * $ltp);
                $slotVol += ($lots * $vol);
                $slotDiffOi += ($lots * $diffOi);

                // Combined Greeks with side sign & lots
                $slotDelta += ($lots * $sign * $delta);
                $slotGamma += ($lots * $sign * $gamma);
                $slotTheta += ($lots * $sign * $theta);
                $slotVega += ($lots * $sign * $vega);
                $slotIvSum += ($lots * $iv);
            }

            $avgSlotIv = $totalLotsAcrossLegs > 0 ? ($slotIvSum / $totalLotsAcrossLegs) : 0.0;

            // Cumulative VWAP
            $cumulativePV += ($slotLtp * max(1, $slotVol));
            $cumulativeVol += max(1, $slotVol);
            $vwapVal = round($cumulativePV / $cumulativeVol, 2);

            // Cumulative OI-VWAP
            $weight = max(1.0, abs($slotDiffOi));
            $cumulativeOIPV += ($slotLtp * $weight);
            $cumulativeOIWeight += $weight;
            $oiVwapVal = round($cumulativeOIPV / $cumulativeOIWeight, 2);

            // Cumulative Net OI Change
            $runningNetOI += $slotDiffOi;

            $combinedLtp[] = round($slotLtp, 2);
            $combinedVwap[] = $vwapVal;
            $combinedOiVwap[] = $oiVwapVal;
            $netOiChangeSeries[] = round($runningNetOI, 0);
            $combinedDeltaSeries[] = round($slotDelta, 3);
            $combinedGammaSeries[] = round($slotGamma, 5);
            $combinedThetaSeries[] = round($slotTheta, 2);
            $combinedVegaSeries[] = round($slotVega, 2);
            $combinedIvSeries[] = round($avgSlotIv, 2);

            // Inception Baseline capture (at 09:15 or first time-step)
            if ($baseTimestamp === null) {
                $baseTimestamp = Carbon::parse($tKey);
                $basePremium = $slotLtp;
                $baseDelta = $slotDelta;
                $baseTheta = $slotTheta;
                $baseGamma = $slotGamma;
                $baseVega = $slotVega;
                $baseIv = $avgSlotIv;
            } else {
                if ($baseDelta == 0.0 && $slotDelta != 0.0) {
                    $baseDelta = $slotDelta;
                    $baseTheta = $slotTheta;
                    $baseGamma = $slotGamma;
                    $baseVega = $slotVega;
                }
                if ($baseIv == 0.0 && $avgSlotIv != 0.0) {
                    $baseIv = $avgSlotIv;
                }
            }

            // Progression Analysis & Velocity
            $elapsedHours = max(0.08, Carbon::parse($tKey)->diffInMinutes($baseTimestamp) / 60.0);
            $realizedDecay = $basePremium - $slotLtp;
            $decayVelocity = round($realizedDecay / $elapsedHours, 2); // pts/hour
            $deltaDrift = round($slotDelta - $baseDelta, 3);
            $vwapDiff = round($slotLtp - $vwapVal, 2);

            // Interval signal determination
            $signal = 'NEUTRAL';
            $signalBadge = 'bg-slate-800 text-slate-300';
            if ($slotLtp < $vwapVal && $decayVelocity > 5.0 && abs($slotDelta) < 15.0) {
                $signal = 'OPTIMAL DECAY';
                $signalBadge = 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30';
            } elseif ($slotLtp > $vwapVal && $decayVelocity < 0.0) {
                $signal = 'PREMIUM EXPANSION';
                $signalBadge = 'bg-rose-500/20 text-rose-300 border border-rose-500/30';
            } elseif (abs($slotDelta) >= 25.0) {
                $signal = 'DELTA DRIFT';
                $signalBadge = 'bg-amber-500/20 text-amber-300 border border-amber-500/30';
            } elseif (abs($slotGamma) > abs($baseGamma) * 1.5 && abs($baseGamma) > 0.0001) {
                $signal = 'GAMMA SPIKE';
                $signalBadge = 'bg-purple-500/20 text-purple-300 border border-purple-500/30';
            }

            $progressionTable[] = [
                'timestamp' => $tKey,
                'time' => $timeLabel,
                'spot' => $slot['spot'],
                'combined_ltp' => round($slotLtp, 2),
                'vwap' => $vwapVal,
                'vwap_diff' => $vwapDiff,
                'net_oi_change' => round($runningNetOI, 0),
                'delta' => round($slotDelta, 3),
                'delta_drift' => $deltaDrift,
                'gamma' => round($slotGamma, 5),
                'theta' => round($slotTheta, 2),
                'vega' => round($slotVega, 2),
                'iv' => round($avgSlotIv, 2),
                'realized_decay' => round($realizedDecay, 2),
                'decay_velocity' => $decayVelocity,
                'signal' => $signal,
                'signal_badge' => $signalBadge,
            ];
        }

        // 6. Overall Judgment & Executive Strategy Suggestion
        $latestSlot = end($progressionTable);
        $totalMinutes = !empty($groupedByTime) ? Carbon::parse(array_key_last($groupedByTime))->diffInMinutes($baseTimestamp) : 0;
        $totalHours = max(0.1, $totalMinutes / 60.0);

        $latestPremium = $latestSlot['combined_ltp'] ?? $basePremium;
        $netDecayPoints = $basePremium - $latestPremium;
        $decayPct = $basePremium > 0.01 ? round(($netDecayPoints / $basePremium) * 100.0, 1) : 0.0;
        $latestVwap = $latestSlot['vwap'] ?? $latestPremium;
        $latestDelta = $latestSlot['delta'] ?? 0.0;
        $latestGamma = $latestSlot['gamma'] ?? 0.0;
        $latestTheta = $latestSlot['theta'] ?? 0.0;
        $latestVega = $latestSlot['vega'] ?? 0.0;
        $latestIv = $latestSlot['iv'] ?? 0.0;
        $latestDecayVelocity = $latestSlot['decay_velocity'] ?? 0.0;

        // Executive Judgment Classification
        $judgment = $this->evaluateExecutiveJudgment(
            basePremium: $basePremium,
            currentPremium: $latestPremium,
            decayPoints: $netDecayPoints,
            decayPct: $decayPct,
            decayVelocity: $latestDecayVelocity,
            currentVwap: $latestVwap,
            currentDelta: $latestDelta,
            currentGamma: $latestGamma,
            baseGamma: $baseGamma,
            currentTheta: $latestTheta,
            currentIv: $latestIv,
            baseIv: $baseIv,
            totalHours: $totalHours
        );

        // Grouped strikes matrix for Basket Builder parity
        $groupedStrikesMap = [];
        foreach ($resolvedLegs as $rl) {
            $stk = (float)$rl['strike'];
            if (!isset($groupedStrikesMap[$stk])) {
                $groupedStrikesMap[$stk] = [
                    'strike' => $stk,
                    'ce_lots' => 0,
                    'pe_lots' => 0,
                    'is_atm' => ($stk == $atmStrike),
                ];
            }
            if ($rl['option_type'] === 'CE') {
                $groupedStrikesMap[$stk]['ce_lots'] += $rl['lots'];
            } elseif ($rl['option_type'] === 'PE') {
                $groupedStrikesMap[$stk]['pe_lots'] += $rl['lots'];
            }
        }
        ksort($groupedStrikesMap);
        $optionChainView = array_values($groupedStrikesMap);
        $progressionTableLatestToOld = array_reverse($progressionTable);

        return [
            'strategy_name' => $strategyName,
            'resolved_legs' => $resolvedLegs,
            'option_chain_view' => $optionChainView,
            'total_lots' => $totalLotsAcrossLegs,
            'atm_strike' => $atmStrike,
            'calculated_atm' => $calculatedAtm,
            'is_custom_atm' => ($customAtm !== null && $customAtm > 0),
            'open_spot' => $openSpot,
            'summary' => [
                'start_time' => $labels[0] ?? $startTime,
                'latest_time' => end($labels) ?: $endTime,
                'base_premium' => round($basePremium, 2),
                'latest_premium' => round($latestPremium, 2),
                'net_decay_points' => round($netDecayPoints, 2),
                'decay_pct' => $decayPct,
                'decay_velocity' => $latestDecayVelocity,
                'latest_vwap' => $latestVwap,
                'latest_delta' => $latestDelta,
                'latest_gamma' => $latestGamma,
                'latest_theta' => $latestTheta,
                'latest_vega' => $latestVega,
                'latest_iv' => $latestIv,
                'total_hours' => round($totalHours, 1),
            ],
            'judgment' => $judgment,
            'chart_data' => [
                'labels' => $labels,
                'combined_ltp' => $combinedLtp,
                'vwap' => $combinedVwap,
                'oi_vwap' => $combinedOiVwap,
                'net_oi' => $netOiChangeSeries,
                'delta' => $combinedDeltaSeries,
                'gamma' => $combinedGammaSeries,
                'theta' => $combinedThetaSeries,
                'vega' => $combinedVegaSeries,
                'iv' => $combinedIvSeries,
            ],
            'progression_table' => $progressionTableLatestToOld,
        ];
    }

    /**
     * Compute Executive Judgment & Suggestion.
     */
    protected function evaluateExecutiveJudgment(
        float $basePremium,
        float $currentPremium,
        float $decayPoints,
        float $decayPct,
        float $decayVelocity,
        float $currentVwap,
        float $currentDelta,
        float $currentGamma,
        float $baseGamma,
        float $currentTheta,
        float $currentIv,
        float $baseIv,
        float $totalHours
    ): array {
        $status = 'ON TRACK';
        $statusColor = 'emerald';
        $headline = 'Decay Progressing Within Expectations';
        $reasons = [];
        $actionableSuggestion = 'Maintain existing position. Greek decay velocity is stable.';

        $isBelowVwap = $currentPremium <= $currentVwap;
        $absDelta = abs($currentDelta);
        $ivDrift = $currentIv - $baseIv;

        if ($decayPct >= 60.0) {
            $status = 'PROFIT TARGET REACHED';
            $statusColor = 'cyan';
            $headline = sprintf("Substantial Premium Decay Captured (+%.1f%% / +%.1f pts)", $decayPct, $decayPoints);
            $reasons[] = sprintf("Over 60%% of combined entry premium has decayed successfully in %.1f hours.", $totalHours);
            $reasons[] = "Risk-to-reward ratio of remaining decay is deteriorating against tail-risk.";
            $actionableSuggestion = "Consider booking profits or tightening stop-loss to entry premium.";
        } elseif ($absDelta >= 35.0) {
            $status = 'CAUTION: DELTA IMBALANCE';
            $statusColor = 'amber';
            $headline = sprintf("Significant Directional Exposure (&Delta; = %+.1f)", $currentDelta);
            $reasons[] = sprintf("Net Delta has drifted to %+.1f, exceeding neutral monitoring boundaries.", $currentDelta);
            $reasons[] = "One side of the strategy is absorbing directional momentum.";
            $actionableSuggestion = $currentDelta > 0
                ? "Position is net long delta. Consider rolling PE strike higher or hedging tested CE."
                : "Position is net short delta. Consider rolling CE strike lower or hedging tested PE.";
        } elseif (!$isBelowVwap && $decayPoints < 0) {
            $status = 'STRESS: PREMIUM EXPANSION';
            $statusColor = 'rose';
            $headline = sprintf("Combined Premium Trading Above VWAP (Bleeding %.1f pts)", abs($decayPoints));
            $reasons[] = sprintf("Current premium (₹%.2f) has broken above VWAP (₹%.2f).", $currentPremium, $currentVwap);
            $reasons[] = "Adverse volatility expansion or sharp intraday rally challenging short legs.";
            $actionableSuggestion = "Review risk thresholds. If premium remains sustained above VWAP, consider partial de-risking.";
        } elseif ($absDelta < 15.0 && $isBelowVwap && $decayVelocity > 0) {
            $status = 'HEALTHY: OPTIMAL DECAY';
            $statusColor = 'emerald';
            $headline = sprintf("Optimal Theta Acceleration (+%.1f pts/hr)", $decayVelocity);
            $reasons[] = sprintf("Realized decay velocity (%.1f pts/hr) confirms solid theta capture.", $decayVelocity);
            $reasons[] = sprintf("Net delta (%.1f) remains near delta-neutral balance.", $currentDelta);
            $reasons[] = sprintf("Combined price is comfortably below VWAP cushion by %.2f pts.", $currentVwap - $currentPremium);
            $actionableSuggestion = "Favorable environment for short premium. Hold position without intervention.";
        } else {
            $status = 'NEUTRAL CONSOLIDATION';
            $statusColor = 'slate';
            $headline = "Balanced Sideways Decay Dynamics";
            $reasons[] = sprintf("Net decay of %.1f pts observed over %.1f hours.", $decayPoints, $totalHours);
            $reasons[] = "Greeks and directional pressure remain within standard tolerances.";
            $actionableSuggestion = "Monitor underlying spot movement relative to ATM boundaries.";
        }

        if ($ivDrift >= 2.0) {
            $reasons[] = sprintf("IV increased by +%.1f%% since 09:15, slowing down time decay.", $ivDrift);
        } elseif ($ivDrift <= -2.0) {
            $reasons[] = sprintf("IV crushed by %.1f%%, providing an additional volatility tailwind.", abs($ivDrift));
        }

        return [
            'status' => $status,
            'status_color' => $statusColor,
            'headline' => $headline,
            'reasons' => $reasons,
            'actionable_suggestion' => $actionableSuggestion,
        ];
    }

    /**
     * Resolve active option chains table name.
     */
    protected function resolveOptionChainsTable(): string
    {
        if (SchemaHasTable('nse_working_days') && function_exists('getTableName')) {
            $tbl = getTableName('option_chains');
            if (SchemaHasTable($tbl) && DB::table($tbl)->exists()) {
                return $tbl;
            }
        }
        if (SchemaHasTable('option_chains_history') && DB::table('option_chains_history')->exists()) {
            return 'option_chains_history';
        }
        if (SchemaHasTable('option_chains_3m') && DB::table('option_chains_3m')->exists()) {
            return 'option_chains_3m';
        }
        if (SchemaHasTable('option_chains')) {
            return 'option_chains';
        }
        return 'option_chains';
    }
}
function SchemaHasTable(string $table): bool {
    return \Illuminate\Support\Facades\Schema::hasTable($table);
}
