<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MultiChartStrategyController extends Controller
{
    /**
     * Main View: Multi-Chart Strategy Premium Analytics (2×3 or 3×3 grid).
     */
    public function index(Request $request): View
    {
        $symbol = strtoupper($request->input('symbol', 'NIFTY'));
        $table  = $this->resolveOptionChainsTable();

        // ── 1. Available Dates ────────────────────────────────────────────────
        $availableDates = [];
        if (SchemaHasTable2('nse_working_days')) {
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

        $defaultDate  = $availableDates[0];
        $selectedDate = $request->input('date', $defaultDate);

        // ── 2. Available Expiries ─────────────────────────────────────────────
        $availableExpiries = [];
        if (SchemaHasTable2('nse_expiries')) {
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

        $defaultExpiry  = !empty($availableExpiries) ? $availableExpiries[0] : today()->toDateString();
        $selectedExpiry = $request->input('expiry', $defaultExpiry);

        // ── 3. Backtest Strategies ────────────────────────────────────────────
        $backtestStrategies = DB::table('backtest_strategies')
            ->where('is_active', true)
            ->orWhereNotNull('name')
            ->orderBy('name')
            ->get();

        // ── 4. Time range ─────────────────────────────────────────────────────
        $startTime = $request->input('start_time', '09:15');
        $endTime   = $request->input('end_time', '15:30');

        // ── 5. Layout (2×3 or 3×3) ───────────────────────────────────────────
        $layout = in_array($request->input('layout', '2x3'), ['2x3', '3x3']) ? $request->input('layout', '2x3') : '2x3';
        $chartCount = $layout === '3x3' ? 9 : 6;

        // ── 6. All Strikes for ATM dropdowns ─────────────────────────────────
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

        // ── 7. Open Spot Price & Calculated ATM ─────────────────────────────
        $step = $symbol === 'BANKNIFTY' ? 100.0 : 50.0;
        $startDt = Carbon::parse("{$selectedDate} {$startTime}:00");
        $endDt   = Carbon::parse("{$selectedDate} {$endTime}:00");

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

        $openSpot      = (float)($openRow->underlying_spot_price ?? 22550.0);
        $calculatedAtm = round($openSpot / $step) * $step;

        // ── 8. Global ATM & Center Index ──────────────────────────────────────
        // 3x3 (9 charts) -> center is Chart 5 (index 4)
        // 2x3 (6 charts) -> center is Chart 3 (index 2)
        $centerIndex = $chartCount === 9 ? 4 : 2;

        $rawGlobalAtm = $request->input('global_atm');
        if ($rawGlobalAtm !== null && $rawGlobalAtm !== '' && is_numeric($rawGlobalAtm) && (float)$rawGlobalAtm > 0) {
            $globalAtm = (float)$rawGlobalAtm;
        } else {
            // Default center is the market open calculated ATM
            $globalAtm = $calculatedAtm;
        }
        $globalAtmActive = true;

        // Ensure globalAtm is in allStrikes
        if (!in_array($globalAtm, $allStrikes)) {
            $allStrikes[] = $globalAtm;
        }

        // ── 9. Per-chart config: Center chart = Global ATM, others shift by step
        $chartStrategyIds = $request->input('strategy_id', []);
        $chartCustomAtms  = $request->input('custom_atm', []);

        $firstStrategyId = $backtestStrategies->first()?->id ?? 1;
        $chartConfigs = [];
        for ($i = 0; $i < $chartCount; $i++) {
            $stratId = isset($chartStrategyIds[$i]) && $chartStrategyIds[$i] ? (int)$chartStrategyIds[$i] : $firstStrategyId;

            // Offset relative to center chart: -200, -150, -100, -50, 0 (Center), +50, +100, +150, +200
            $offset = ($i - $centerIndex) * $step;
            $centeredStrike = (float)($globalAtm + $offset);

            // If user explicitly provided custom_atm[$i], use it; otherwise use the calculated centered strike
            if (isset($chartCustomAtms[$i]) && is_numeric($chartCustomAtms[$i]) && $chartCustomAtms[$i] !== '') {
                $customAtm = (float)$chartCustomAtms[$i];
            } else {
                $customAtm = $centeredStrike;
            }

            if (!in_array($customAtm, $allStrikes)) {
                $allStrikes[] = $customAtm;
            }

            $chartConfigs[] = [
                'strategy_id' => $stratId,
                'custom_atm'  => $customAtm,
                'offset'      => $offset,
                'is_center'   => ($i === $centerIndex),
            ];
        }

        sort($allStrikes);
        $allStrikes = array_values(array_unique($allStrikes));

        return view('strategy-multi-chart.index', compact(
            'symbol',
            'availableDates',
            'selectedDate',
            'availableExpiries',
            'selectedExpiry',
            'backtestStrategies',
            'startTime',
            'endTime',
            'layout',
            'chartCount',
            'allStrikes',
            'chartConfigs',
            'globalAtm',
            'globalAtmActive',
            'centerIndex',
            'step',
            'calculatedAtm'
        ));
    }

    /**
     * API: Return analytics data for a single chart slot.
     * Called via AJAX with per-chart params.
     */
    public function getChartData(Request $request): JsonResponse
    {
        $table = $this->resolveOptionChainsTable();

        $customAtm = $request->input('custom_atm');
        $customAtm = ($customAtm !== null && $customAtm !== '' && is_numeric($customAtm)) ? (float)$customAtm : null;

        $analytics = $this->computeAnalytics(
            strategyId:    (int)$request->input('strategy_id', 1),
            symbol:        strtoupper($request->input('symbol', 'NIFTY')),
            selectedDate:  $request->input('date', today()->toDateString()),
            selectedExpiry:$request->input('expiry', today()->toDateString()),
            startTime:     $request->input('start_time', '09:15'),
            endTime:       $request->input('end_time', '15:30'),
            table:         $table,
            customAtm:     $customAtm
        );

        return response()->json($analytics);
    }

    /**
     * Core analytics computation engine (strategy-only, no manual mode).
     */
    protected function computeAnalytics(
        int     $strategyId,
        string  $symbol,
        string  $selectedDate,
        string  $selectedExpiry,
        string  $startTime,
        string  $endTime,
        string  $table,
        ?float  $customAtm = null
    ): array {
        $step = $symbol === 'BANKNIFTY' ? 100.0 : 50.0;

        $startDt = Carbon::parse("{$selectedDate} {$startTime}:00");
        $endDt   = Carbon::parse("{$selectedDate} {$endTime}:00");

        // ── ATM spot resolution ───────────────────────────────────────────────
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

        $openSpot      = (float)($openRow->underlying_spot_price ?? 22550.0);
        $calculatedAtm = round($openSpot / $step) * $step;
        $atmStrike     = ($customAtm !== null && $customAtm > 0) ? $customAtm : $calculatedAtm;

        // ── Strategy legs ─────────────────────────────────────────────────────
        $resolvedLegs = [];
        $strategyName = 'ATM Straddle';

        $stratRecord = DB::table('backtest_strategies')->find($strategyId);
        if ($stratRecord) {
            $strategyName = $stratRecord->name;
            $def          = json_decode($stratRecord->definition, true);
            $rawLegs      = $def['legs'] ?? [];

            foreach ($rawLegs as $idx => $leg) {
                $lots   = max(1, (int)($leg['lots'] ?? 1));
                $side   = strtoupper($leg['side'] ?? 'SELL');
                $opt    = strtoupper($leg['option_type'] ?? 'CE');
                $mon    = strtoupper($leg['moneyness'] ?? 'ATM');
                $offset = (float)($leg['strike_offset'] ?? 0);

                $moneynessAdjustment = match ($mon) {
                    'ITM'   => -$step - $offset,
                    'OTM'   => $step + $offset,
                    default => 0.0,
                };
                $strike = (float)($atmStrike + $moneynessAdjustment);

                $resolvedLegs[] = [
                    'leg_id'      => $idx + 1,
                    'strike'      => $strike,
                    'option_type' => $opt,
                    'side'        => $side,
                    'lots'        => $lots,
                    'moneyness'   => $mon,
                    'offset'      => (int)$offset,
                    'label'       => "{$side} {$lots}x " . number_format($strike, 0, '', '') . " {$opt}",
                ];
            }
        }

        // Fallback default: ATM Straddle
        if (empty($resolvedLegs)) {
            $strategyName = 'ATM Straddle (Default)';
            $resolvedLegs = [
                ['leg_id' => 1, 'strike' => (float)$atmStrike, 'option_type' => 'CE', 'side' => 'SELL', 'lots' => 1, 'moneyness' => 'ATM', 'offset' => 0, 'label' => "SELL 1x {$atmStrike} CE"],
                ['leg_id' => 2, 'strike' => (float)$atmStrike, 'option_type' => 'PE', 'side' => 'SELL', 'lots' => 1, 'moneyness' => 'ATM', 'offset' => 0, 'label' => "SELL 1x {$atmStrike} PE"],
            ];
        }

        // ── Query option chains ───────────────────────────────────────────────
        $strikesFloats    = array_unique(array_map(fn($s) => (float)$s, array_column($resolvedLegs, 'strike')));
        $strikesFormatted = array_map(fn($s) => number_format((float)$s, 2, '.', ''), $strikesFloats);
        $allStrikeVariants = array_values(array_unique(array_merge($strikesFloats, $strikesFormatted)));

        $isMysql    = DB::connection()->getDriverName() === 'mysql';
        $fromClause = ($isMysql && $table === 'option_chains_history')
            ? DB::raw("`{$table}` USE INDEX (option_chains_captured_at_index)")
            : $table;

        $query = DB::table($fromClause)
            ->select(['captured_at', 'strike_price', 'option_type', 'ltp', 'volume', 'diff_oi',
                      'delta', 'gamma', 'theta', 'vega', 'iv', 'underlying_spot_price'])
            ->where('trading_symbol', $symbol)
            ->whereIn('strike_price', $allStrikeVariants)
            ->where('captured_at', '>=', $startDt->toDateTimeString())
            ->where('captured_at', '<=', $endDt->toDateTimeString())
            ->orderBy('captured_at');

        if (!empty($selectedExpiry)) {
            $query->where('expiry', $selectedExpiry);
        }

        $rawRows = $query->get();

        // ── Group by time ─────────────────────────────────────────────────────
        $groupedByTime = [];
        foreach ($rawRows as $r) {
            $tKey      = $r->captured_at;
            $strikeKey = (string)(float)$r->strike_price;
            $optType   = strtoupper(trim($r->option_type));
            $legKey    = "{$strikeKey}_{$optType}";
            if (!isset($groupedByTime[$tKey])) {
                $groupedByTime[$tKey] = ['captured_at' => $tKey, 'spot' => (float)($r->underlying_spot_price ?? $openSpot), 'contracts' => []];
            }
            $groupedByTime[$tKey]['contracts'][$legKey] = $r;
        }
        ksort($groupedByTime);

        // ── Build time-series ─────────────────────────────────────────────────
        $labels              = [];
        $combinedLtp         = [];
        $combinedVwap        = [];
        $combinedOiVwap      = [];
        $netOiChangeSeries   = [];
        $combinedDeltaSeries = [];
        $combinedGammaSeries = [];
        $combinedThetaSeries = [];
        $combinedVegaSeries  = [];
        $combinedIvSeries    = [];

        $cumulativePV      = 0.0;
        $cumulativeVol     = 0.0;
        $cumulativeOIPV    = 0.0;
        $cumulativeOIWeight = 0.0;
        $runningNetOI      = 0.0;

        $baseTimestamp  = null;
        $basePremium    = 0.0;
        $baseIv         = 0.0;
        $baseDelta      = 0.0;
        $baseGamma      = 0.0;
        $totalLotsAcrossLegs = array_sum(array_column($resolvedLegs, 'lots'));

        foreach ($groupedByTime as $tKey => $slot) {
            $timeLabel = Carbon::parse($tKey)->format('H:i');
            $labels[]  = $timeLabel;

            $slotLtp    = 0.0; $slotVol = 0.0; $slotDiffOi = 0.0;
            $slotDelta  = 0.0; $slotGamma = 0.0; $slotTheta = 0.0;
            $slotVega   = 0.0; $slotIvSum = 0.0;

            foreach ($resolvedLegs as $leg) {
                $strikeKey = (string)(float)$leg['strike'];
                $optType   = strtoupper(trim($leg['option_type']));
                $key       = "{$strikeKey}_{$optType}";
                $contract  = $slot['contracts'][$key] ?? null;

                $ltp    = (float)($contract->ltp ?? 0.0);
                $vol    = (int)($contract->volume ?? 0);
                $diffOi = (float)($contract->diff_oi ?? 0.0);
                $delta  = (float)($contract->delta ?? 0.0);
                $gamma  = (float)($contract->gamma ?? 0.0);
                $theta  = (float)($contract->theta ?? 0.0);
                $vega   = (float)($contract->vega ?? 0.0);
                $iv     = (float)($contract->iv ?? 0.0);
                $sign   = $leg['side'] === 'BUY' ? 1.0 : -1.0;
                $lots   = $leg['lots'];

                $slotLtp    += ($lots * $ltp);
                $slotVol    += ($lots * $vol);
                $slotDiffOi += ($lots * $diffOi);
                $slotDelta  += ($lots * $sign * $delta);
                $slotGamma  += ($lots * $sign * $gamma);
                $slotTheta  += ($lots * $sign * $theta);
                $slotVega   += ($lots * $sign * $vega);
                $slotIvSum  += ($lots * $iv);
            }

            $avgSlotIv = $totalLotsAcrossLegs > 0 ? ($slotIvSum / $totalLotsAcrossLegs) : 0.0;

            $cumulativePV  += ($slotLtp * max(1, $slotVol));
            $cumulativeVol += max(1, $slotVol);
            $vwapVal        = round($cumulativePV / $cumulativeVol, 2);

            $weight              = max(1.0, abs($slotDiffOi));
            $cumulativeOIPV     += ($slotLtp * $weight);
            $cumulativeOIWeight += $weight;
            $oiVwapVal           = round($cumulativeOIPV / $cumulativeOIWeight, 2);

            $runningNetOI += $slotDiffOi;

            $combinedLtp[]         = round($slotLtp, 2);
            $combinedVwap[]        = $vwapVal;
            $combinedOiVwap[]      = $oiVwapVal;
            $netOiChangeSeries[]   = round($runningNetOI, 0);
            $combinedDeltaSeries[] = round($slotDelta, 3);
            $combinedGammaSeries[] = round($slotGamma, 5);
            $combinedThetaSeries[] = round($slotTheta, 2);
            $combinedVegaSeries[]  = round($slotVega, 2);
            $combinedIvSeries[]    = round($avgSlotIv, 2);

            if ($baseTimestamp === null) {
                $baseTimestamp = Carbon::parse($tKey);
                $basePremium   = $slotLtp;
                $baseDelta     = $slotDelta;
                $baseGamma     = $slotGamma;
                $baseIv        = $avgSlotIv;
            }
        }

        $latestPremium = end($combinedLtp) ?: 0.0;
        $latestVwap    = end($combinedVwap) ?: 0.0;
        $latestDelta   = end($combinedDeltaSeries) ?: 0.0;
        $latestTheta   = end($combinedThetaSeries) ?: 0.0;
        $netDecayPts   = round($basePremium - $latestPremium, 2);
        $decayPct      = $basePremium > 0.01 ? round(($netDecayPts / $basePremium) * 100, 1) : 0.0;

        return [
            'strategy_name' => $strategyName,
            'resolved_legs' => $resolvedLegs,
            'atm_strike'    => $atmStrike,
            'calculated_atm'=> $calculatedAtm,
            'is_custom_atm' => ($customAtm !== null && $customAtm > 0),
            'open_spot'     => $openSpot,
            'summary' => [
                'base_premium'    => round($basePremium, 2),
                'latest_premium'  => round($latestPremium, 2),
                'net_decay_points'=> $netDecayPts,
                'decay_pct'       => $decayPct,
                'latest_vwap'     => $latestVwap,
                'latest_delta'    => $latestDelta,
                'latest_theta'    => round($latestTheta, 2),
                'start_time'      => $labels[0] ?? $startTime,
                'latest_time'     => end($labels) ?: $endTime,
            ],
            'chart_data' => [
                'labels'       => $labels,
                'combined_ltp' => $combinedLtp,
                'vwap'         => $combinedVwap,
                'oi_vwap'      => $combinedOiVwap,
                'net_oi'       => $netOiChangeSeries,
                'delta'        => $combinedDeltaSeries,
                'gamma'        => $combinedGammaSeries,
                'theta'        => $combinedThetaSeries,
                'vega'         => $combinedVegaSeries,
                'iv'           => $combinedIvSeries,
            ],
        ];
    }

    protected function resolveOptionChainsTable(): string
    {
        if (SchemaHasTable2('nse_working_days') && function_exists('getTableName')) {
            $tbl = getTableName('option_chains');
            if (SchemaHasTable2($tbl) && DB::table($tbl)->exists()) {
                return $tbl;
            }
        }
        if (SchemaHasTable2('option_chains_history') && DB::table('option_chains_history')->exists()) {
            return 'option_chains_history';
        }
        if (SchemaHasTable2('option_chains_3m') && DB::table('option_chains_3m')->exists()) {
            return 'option_chains_3m';
        }
        return 'option_chains';
    }
}

function SchemaHasTable2(string $table): bool
{
    return \Illuminate\Support\Facades\Schema::hasTable($table);
}
