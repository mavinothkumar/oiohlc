<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class VegaAnalysisController extends Controller
{
    /**
     * Enterprise Vega & Options Breadth Monitor
     * Supports:
     * - Multi-strike ATM Baskets (±3, ±5, ±7 strikes average)
     * - Intraday 5m Breadth Matrix (Greek, OI & Volume Heatmap like Nifty Weightage Monitor)
     * - Side-by-side Chart (80%) + 2x2 Widgets (20%)
     * - Instant indexed queries without timeouts
     */
    public function index(Request $request)
    {
        $symbol = $request->input('symbol', 'NIFTY');

        // 1. Fast Working Date Resolution
        $workingDay = null;
        if (Schema::hasTable('nse_working_days')) {
            $workingDay = DB::table('nse_working_days')
                ->where('current', 1)
                ->first();

            if (!$workingDay) {
                $workingDay = DB::table('nse_working_days')
                    ->where('working_date', '<=', today()->toDateString())
                    ->orderByDesc('working_date')
                    ->first();
            }
        }

        $defaultDate = $workingDay ? $workingDay->working_date : today()->toDateString();
        $selectedDate = $request->input('date', $defaultDate);

        // Determine Table
        $isLiveToday = ($selectedDate === today()->toDateString()) && DB::table('option_chains')->limit(1)->exists();
        $table = $isLiveToday ? 'option_chains' : 'option_chains_history';

        // 2. Resolve Available Expiries
        $expiries = collect();
        if (Schema::hasTable('nse_expiries')) {
            $expiries = DB::table('nse_expiries')
                ->where('trading_symbol', $symbol)
                ->where('instrument_type', 'OPT')
                ->orderBy('expiry_date')
                ->pluck('expiry_date');
        }

        $defaultExpiry = null;
        if (Schema::hasTable('nse_expiries')) {
            $defaultExpiry = DB::table('nse_expiries')
                ->where('trading_symbol', $symbol)
                ->where('instrument_type', 'OPT')
                ->where('is_current', 1)
                ->value('expiry_date');
        }

        if (!$defaultExpiry || ($expiries->isNotEmpty() && !$expiries->contains($defaultExpiry))) {
            $defaultExpiry = $expiries->first(fn($e) => $e >= $selectedDate) ?: ($expiries->first() ?? $selectedDate);
        }

        $selectedExpiry = $request->input('expiry', $defaultExpiry);

        $startTs = "{$selectedDate} 09:00:00";
        $endTs   = "{$selectedDate} 15:35:00";

        // 3. Resolve Strikes available for this expiry & date
        $allStrikes = DB::table($table)
            ->where('trading_symbol', $symbol)
            ->where('expiry', $selectedExpiry)
            ->where('captured_at', '>=', $startTs)
            ->where('captured_at', '<=', $endTs)
            ->distinct()
            ->orderBy('strike_price')
            ->pluck('strike_price');

        if ($allStrikes->isEmpty()) {
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
                $allStrikes = $altStrikes;
            }
        }

        // 4. ATM Strike Detection
        $spotPrice = DB::table($table)
            ->where('trading_symbol', $symbol)
            ->where('expiry', $selectedExpiry)
            ->where('captured_at', '>=', $startTs)
            ->where('captured_at', '<=', $endTs)
            ->whereNotNull('underlying_spot_price')
            ->where('underlying_spot_price', '>', 0)
            ->orderBy('captured_at')
            ->value('underlying_spot_price');

        $atmStrike = null;
        if ($allStrikes->isNotEmpty()) {
            if ($spotPrice) {
                $atmStrike = $allStrikes->sortBy(fn($s) => abs($s - $spotPrice))->first();
            } else {
                $midIndex = (int) floor($allStrikes->count() / 2);
                $atmStrike = $allStrikes->values()->get($midIndex);
            }
        }

        $centerStrike = (int) $request->input('strike', $atmStrike);
        $strikeRange  = (int) $request->input('range', 3); // 0 = Single, 3 = +/-3 (7 strikes), 5 = +/-5 (11 strikes), 7 = +/-7
        $mode         = $request->input('mode', 'change'); // 'change' (Delta from Open) or 'absolute'
        $matrixMetric = $request->input('metric', 'vega'); // 'vega', 'premium', 'oi', 'signal'

        // 5. Select target strikes based on range
        $targetStrikes = collect();
        if ($allStrikes->isNotEmpty()) {
            $allStrikesArray = $allStrikes->values()->all();
            $centerIndex = array_search($centerStrike, $allStrikesArray);
            if ($centerIndex === false) {
                // Find nearest index
                $closestStrike = $allStrikes->sortBy(fn($s) => abs($s - $centerStrike))->first();
                $centerIndex = array_search($closestStrike, $allStrikesArray);
                $centerStrike = $closestStrike;
            }

            if ($strikeRange === 0) {
                $targetStrikes = collect([$centerStrike]);
            } else {
                $fromIdx = max(0, $centerIndex - $strikeRange);
                $toIdx   = min(count($allStrikesArray) - 1, $centerIndex + $strikeRange);
                $targetStrikes = collect(array_slice($allStrikesArray, $fromIdx, ($toIdx - $fromIdx + 1)));
            }
        }

        // 6. Fast indexed query for target strikes
        $labels = [];
        $callVega = [];
        $putVega = [];
        $callVegaChange = [];
        $putVegaChange = [];
        $synthFuture = [];
        $spotPrices = [];
        $currentRegime = 'NO_DATA';
        $regimeStartTime = null;

        $stats = [
            'spot' => 0,
            'synth' => 0,
            'synth_change' => 0,
            'call_vega' => 0,
            'call_vega_change' => 0,
            'put_vega' => 0,
            'put_vega_change' => 0,
            'net_vega_short' => 0,
            'open_call_vega' => 0,
            'open_put_vega' => 0,
            'open_synth' => 0,
        ];

        // Matrix structures
        $matrixTimestamps = [];
        $matrixRows = [];
        $matrixSummary = [
            'total_cells' => 0,
            'bullish_cells' => 0,
            'bearish_cells' => 0,
            'neutral_cells' => 0,
            'active_time' => null,
            'active_up_pct' => 0,
            'active_down_pct' => 0,
        ];

        if ($targetStrikes->isNotEmpty()) {
            $ceRows = DB::table($table)
                ->where('trading_symbol', $symbol)
                ->where('expiry', $selectedExpiry)
                ->whereIn('strike_price', $targetStrikes)
                ->where('option_type', 'CE')
                ->where('captured_at', '>=', $startTs)
                ->where('captured_at', '<=', $endTs)
                ->orderBy('captured_at')
                ->select('captured_at', 'strike_price', 'ltp', 'vega', 'iv', 'oi', 'volume', 'underlying_spot_price')
                ->get();

            $peRows = DB::table($table)
                ->where('trading_symbol', $symbol)
                ->where('expiry', $selectedExpiry)
                ->whereIn('strike_price', $targetStrikes)
                ->where('option_type', 'PE')
                ->where('captured_at', '>=', $startTs)
                ->where('captured_at', '<=', $endTs)
                ->orderBy('captured_at')
                ->select('captured_at', 'strike_price', 'ltp', 'vega', 'iv', 'oi', 'volume', 'underlying_spot_price')
                ->get();

            // Group by formatted timestamp
            $ceByTime = $ceRows->groupBy(fn($r) => Carbon::parse($r->captured_at)->format('H:i'));
            $peByTime = $peRows->groupBy(fn($r) => Carbon::parse($r->captured_at)->format('H:i'));

            $commonTimes = $ceByTime->keys()->intersect($peByTime->keys())->sort()->values();

            if ($commonTimes->isNotEmpty()) {
                // Calculate Basket Averages for each timestamp
                $timeAggregates = [];
                $openCallVega = null;
                $openPutVega  = null;
                $openSynth    = null;
                $prevRegime   = null;

                foreach ($commonTimes as $t) {
                    $ces = $ceByTime[$t];
                    $pes = $peByTime[$t];

                    $avgCallLtp = (float) $ces->avg('ltp');
                    $avgPutLtp  = (float) $pes->avg('ltp');
                    $avgCallV   = (float) $ces->avg('vega');
                    $avgPutV    = (float) $pes->avg('vega');
                    $spotVal    = (float) ($ces->first()->underlying_spot_price ?: ($pes->first()->underlying_spot_price ?: 0));

                    $sf = round($centerStrike + $avgCallLtp - $avgPutLtp, 2);

                    if ($openCallVega === null) {
                        $openCallVega = $avgCallV;
                        $openPutVega  = $avgPutV;
                        $openSynth    = $sf;
                    }

                    $dVCall = round($avgCallV - $openCallVega, 4);
                    $dVPut  = round($avgPutV - $openPutVega, 4);

                    $labels[] = $t;
                    $callVega[] = round($avgCallV, 4);
                    $putVega[]  = round($avgPutV, 4);
                    $callVegaChange[] = $dVCall;
                    $putVegaChange[]  = $dVPut;
                    $synthFuture[] = $sf;
                    $spotPrices[]  = $spotVal;

                    if ($dVCall < 0 && $dVPut < 0) {
                        $regCode = 'BOTH_BELOW_ZERO';
                        $regLabel = 'Dual Decay (Sell Favored)';
                    } elseif ($dVCall > 0 && $dVPut > 0) {
                        $regCode = 'BOTH_ABOVE_ZERO';
                        $regLabel = 'Dual Expansion (Buy Favored)';
                    } elseif ($dVCall > 0 && $dVPut <= 0) {
                        $regCode = 'CALL_ABOVE_PUT_BELOW';
                        $regLabel = 'Bullish Skew';
                    } else {
                        $regCode = 'CALL_BELOW_PUT_ABOVE';
                        $regLabel = 'Bearish Skew';
                    }

                    if ($regCode !== $prevRegime) {
                        $regimeStartTime = $t;
                        $prevRegime = $regCode;
                    }

                    $timeAggregates[$t] = [
                        'time' => $t,
                        'synth' => $sf,
                        'call_vega' => $avgCallV,
                        'put_vega' => $avgPutV,
                        'd_call_vega' => $dVCall,
                        'd_put_vega' => $dVPut,
                        'spot' => $spotVal,
                        'regime' => $regCode,
                        'regime_label' => $regLabel,
                    ];
                }

                $lastAgg = end($timeAggregates);
                $currentRegime = $lastAgg['regime'];

                $stats['open_call_vega'] = $openCallVega;
                $stats['open_put_vega']  = $openPutVega;
                $stats['open_synth']     = $openSynth;
                $stats['spot'] = $lastAgg['spot'];
                $stats['synth'] = $lastAgg['synth'];
                $stats['synth_change'] = round($lastAgg['synth'] - $openSynth, 2);
                $stats['call_vega'] = $lastAgg['call_vega'];
                $stats['call_vega_change'] = $lastAgg['d_call_vega'];
                $stats['put_vega'] = $lastAgg['put_vega'];
                $stats['put_vega_change'] = $lastAgg['d_put_vega'];
                $stats['net_vega_short'] = round(-($lastAgg['call_vega'] + $lastAgg['put_vega']), 4);

                // 7. Build 5-Minute Matrix (like Nifty Weightage Monitor)
                $matrixTimestamps = $commonTimes->all();

                // Group by strike and captured_at
                $ceLookup = [];
                foreach ($ceRows as $r) {
                    $t = Carbon::parse($r->captured_at)->format('H:i');
                    $ceLookup[$r->strike_price][$t] = $r;
                }
                $peLookup = [];
                foreach ($peRows as $r) {
                    $t = Carbon::parse($r->captured_at)->format('H:i');
                    $peLookup[$r->strike_price][$t] = $r;
                }

                // First Matrix Row: Master Basket Average
                $basketCells = [];
                $basketFirstTime = $commonTimes->first();
                $basketOpenC = $timeAggregates[$basketFirstTime]['call_vega'];
                $basketOpenP = $timeAggregates[$basketFirstTime]['put_vega'];

                foreach ($matrixTimestamps as $t) {
                    $agg = $timeAggregates[$t];
                    $dC = $agg['d_call_vega'];
                    $dP = $agg['d_put_vega'];

                    if ($matrixMetric === 'vega') {
                        // Vega logic: Green if both above zero (expansion), Red if both below zero (decay)
                        if ($dC > 0 && $dP > 0) {
                            $cellType = 'bull';
                            $valStr = "+$dC / +$dP";
                        } elseif ($dC < 0 && $dP < 0) {
                            $cellType = 'bear';
                            $valStr = "$dC / $dP";
                        } elseif ($dC > 0 && $dP <= 0) {
                            $cellType = 'skew_bull';
                            $valStr = "+$dC / $dP";
                        } else {
                            $cellType = 'skew_bear';
                            $valStr = "$dC / +$dP";
                        }
                    } elseif ($matrixMetric === 'premium') {
                        $premChange = round(($agg['synth'] - $openSynth), 1);
                        $cellType = ($premChange >= 0) ? 'bull' : 'bear';
                        $valStr = ($premChange >= 0 ? '+' : '') . $premChange;
                    } else { // signal
                        if ($agg['regime'] === 'BOTH_BELOW_ZERO') {
                            $cellType = 'bear'; // decay / sell
                            $valStr = 'SELL';
                        } elseif ($agg['regime'] === 'BOTH_ABOVE_ZERO') {
                            $cellType = 'bull'; // buy
                            $valStr = 'BUY';
                        } elseif ($agg['regime'] === 'CALL_ABOVE_PUT_BELOW') {
                            $cellType = 'skew_bull';
                            $valStr = 'BULL SKEW';
                        } else {
                            $cellType = 'skew_bear';
                            $valStr = 'BEAR SKEW';
                        }
                    }

                    $basketCells[$t] = [
                        'time' => $t,
                        'type' => $cellType,
                        'value' => $valStr,
                        'synth' => $agg['synth'],
                        'regime' => $agg['regime_label'],
                        'd_call' => $dC,
                        'd_put' => $dP,
                    ];
                }

                $matrixRows[] = [
                    'id' => 'basket_avg',
                    'title' => "⚡ ATM Basket (±{$strikeRange} Strikes Avg)",
                    'subtitle' => 'Composite Volatility & Parity',
                    'is_master' => true,
                    'cells' => $basketCells,
                ];

                // Individual Strike Rows
                foreach ($targetStrikes as $stk) {
                    $isAtm = ((int)$stk === (int)$atmStrike);
                    $firstCe = $ceLookup[$stk][$basketFirstTime] ?? null;
                    $firstPe = $peLookup[$stk][$basketFirstTime] ?? null;
                    $stkOpenCVega = $firstCe ? (float)$firstCe->vega : 0;
                    $stkOpenPVega = $firstPe ? (float)$firstPe->vega : 0;
                    $stkOpenCombinedLtp = ($firstCe ? (float)$firstCe->ltp : 0) + ($firstPe ? (float)$firstPe->ltp : 0);

                    $stkCells = [];
                    foreach ($matrixTimestamps as $t) {
                        $cRow = $ceLookup[$stk][$t] ?? null;
                        $pRow = $peLookup[$stk][$t] ?? null;

                        if (!$cRow && !$pRow) {
                            $stkCells[$t] = ['time' => $t, 'type' => 'neutral', 'value' => '-'];
                            continue;
                        }

                        $cVal = $cRow ? (float)$cRow->vega : 0;
                        $pVal = $pRow ? (float)$pRow->vega : 0;
                        $dCV = round($cVal - $stkOpenCVega, 3);
                        $dPV = round($pVal - $stkOpenPVega, 3);
                        $combLtp = ($cRow ? (float)$cRow->ltp : 0) + ($pRow ? (float)$pRow->ltp : 0);
                        $combLtpChange = round($combLtp - $stkOpenCombinedLtp, 2);

                        if ($matrixMetric === 'vega') {
                            if ($dCV > 0 && $dPV > 0) {
                                $cType = 'bull';
                                $lbl = "ΔCE:+$dCV | ΔPE:+$dPV";
                            } elseif ($dCV < 0 && $dPV < 0) {
                                $cType = 'bear';
                                $lbl = "ΔCE:$dCV | ΔPE:$dPV";
                            } elseif ($dCV > 0 && $dPV <= 0) {
                                $cType = 'skew_bull';
                                $lbl = "CE Exp (+$dCV)";
                            } else {
                                $cType = 'skew_bear';
                                $lbl = "PE Exp (+$dPV)";
                            }
                        } elseif ($matrixMetric === 'premium') {
                            $cType = ($combLtpChange >= 0) ? 'bull' : 'bear';
                            $lbl = ($combLtpChange >= 0 ? '+' : '') . $combLtpChange;
                        } else { // signal
                            if ($dCV < 0 && $dPV < 0) {
                                $cType = 'bear';
                                $lbl = 'DECAY (SELL)';
                            } elseif ($dCV > 0 && $dPV > 0) {
                                $cType = 'bull';
                                $lbl = 'EXP (BUY)';
                            } elseif ($dCV > 0) {
                                $cType = 'skew_bull';
                                $lbl = 'BULL SKEW';
                            } else {
                                $cType = 'skew_bear';
                                $lbl = 'BEAR SKEW';
                            }
                        }

                        $stkCells[$t] = [
                            'time' => $t,
                            'type' => $cType,
                            'value' => $lbl,
                            'ce_ltp' => $cRow ? $cRow->ltp : 0,
                            'pe_ltp' => $pRow ? $pRow->ltp : 0,
                            'comb_ltp' => $combLtp,
                            'ce_vega' => $cVal,
                            'pe_vega' => $pVal,
                            'd_ce' => $dCV,
                            'd_pe' => $dPV,
                        ];
                    }

                    $matrixRows[] = [
                        'id' => "stk_$stk",
                        'strike' => $stk,
                        'title' => "₹" . number_format($stk) . ($isAtm ? " (ATM)" : ""),
                        'subtitle' => "CE & PE Pair",
                        'is_atm' => $isAtm,
                        'is_master' => false,
                        'cells' => $stkCells,
                    ];
                }

                // Summary for latest timestamp
                $latestTime = end($matrixTimestamps);
                $upCount = 0;
                $downCount = 0;
                $totalCount = 0;
                foreach ($matrixRows as $mRow) {
                    if (isset($mRow['cells'][$latestTime])) {
                        $cell = $mRow['cells'][$latestTime];
                        $totalCount++;
                        if (in_array($cell['type'], ['bull', 'skew_bull'])) $upCount++;
                        if (in_array($cell['type'], ['bear', 'skew_bear'])) $downCount++;
                    }
                }
                $matrixSummary['active_time'] = $latestTime;
                $matrixSummary['active_up_pct'] = $totalCount > 0 ? round(($upCount / $totalCount) * 100) : 0;
                $matrixSummary['active_down_pct'] = $totalCount > 0 ? round(($downCount / $totalCount) * 100) : 0;
            }
        }

        return view('vega-analysis', compact(
            'symbol',
            'expiries',
            'selectedExpiry',
            'selectedDate',
            'allStrikes',
            'targetStrikes',
            'atmStrike',
            'centerStrike',
            'strikeRange',
            'mode',
            'matrixMetric',
            'labels',
            'callVega',
            'putVega',
            'callVegaChange',
            'putVegaChange',
            'synthFuture',
            'spotPrices',
            'currentRegime',
            'regimeStartTime',
            'stats',
            'table',
            'matrixTimestamps',
            'matrixRows',
            'matrixSummary'
        ));
    }
}
