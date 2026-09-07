<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class OptionChainViewController extends Controller
{
    /**
     * Resolve the correct table based on mode and date.
     * Live mode  → use getTableName() (auto-switches to _history after market)
     * History mode → always use option_chains_history
     */
    private function resolveTable(string $mode): string
    {
        if ($mode === 'history') {
            return 'option_chains_history';
        }
        return getTableName('option_chains');
    }

    /**
     * Main page — pass filter defaults to the view.
     */
    public function index(Request $request)
    {
        // Resolve current working date
        $workingDay = DB::table('nse_working_days')->where('current', 1)->first()
            ?? DB::table('nse_working_days')->where('previous', 1)->orderByDesc('working_date')->first();

        $today = $workingDay ? $workingDay->working_date : today()->toDateString();

        // Default expiry — nearest current expiry for NIFTY
        $defaultExpiry = DB::table('nse_expiries')
            ->where('trading_symbol', 'NIFTY')
            ->where('instrument_type', 'OPT')
            ->where('is_current', 1)
            ->value('expiry_date') ?? $today;

        // All available expiries (live table since we are loading the page)
        $table = getTableName('option_chains');
        $expiries = DB::table($table)
            ->select('expiry')
            ->distinct()
            ->orderBy('expiry')
            ->pluck('expiry');

        $selectedDate   = $request->input('date', $today);
        $selectedExpiry = $request->input('expiry', $defaultExpiry);
        $mode           = $request->input('mode', 'live');
        $startTime      = $request->input('start_time', '09:16');
        $endTime        = $request->input('end_time', '15:40');

        return view('option-chain-view', compact(
            'today',
            'selectedDate',
            'selectedExpiry',
            'expiries',
            'mode',
            'startTime',
            'endTime'
        ));
    }

    /**
     * AJAX: Return option chain data as JSON.
     */
    public function getData(Request $request)
    {
        $mode            = $request->input('mode', 'live');
        $expiry          = $request->input('expiry');
        $date            = $request->input('date', today()->toDateString());
        $startTime       = $request->input('start_time', '09:16');
        $endTime         = $request->input('end_time', '15:40');
        $underlying      = $request->input('underlying', 'NSE_INDEX|Nifty 50');
        $strikesEachSide = max(1, min(50, (int) $request->input('strikes', 10)));

        if (!$expiry) {
            return response()->json(['error' => 'Expiry is required'], 422);
        }

        $table = $this->resolveTable($mode);

        // ── Fetch the latest snapshot within the time window ────────────────
        $latestCapturedAt = DB::table($table)
            ->where('underlying_key', $underlying)
            ->where('expiry', $expiry)
            ->whereDate('captured_at', $date)
            ->whereTime('captured_at', '>=', $startTime . ':00')
            ->whereTime('captured_at', '<=', $endTime . ':59')
            ->max('captured_at');

        if (!$latestCapturedAt) {
            return response()->json([
                'rows'        => [],
                'spot'        => null,
                'spot_chg'    => null,
                'total_pcr'   => null,
                'snapshot_at' => null,
                'highlights'  => [],
            ]);
        }

        // ── Fetch all rows at the latest snapshot ───────────────────────────
        $rows = DB::table($table)
            ->where('underlying_key', $underlying)
            ->where('expiry', $expiry)
            ->where('captured_at', $latestCapturedAt)
            ->orderBy('strike_price')
            ->get([
                'strike_price', 'option_type', 'ltp', 'diff_ltp',
                'volume', 'diff_volume', 'oi', 'diff_oi',
                'close_price', 'bid_price', 'bid_qty', 'ask_price', 'ask_qty',
                'prev_oi', 'vega', 'theta', 'gamma', 'delta', 'iv',
                'underlying_spot_price', 'pcr', 'build_up',
            ]);

        if ($rows->isEmpty()) {
            return response()->json(['rows' => [], 'spot' => null, 'total_pcr' => null, 'highlights' => []]);
        }

        // ── Spot price ──────────────────────────────────────────────────────
        $spotRow   = $rows->first();
        $spot      = (float) ($spotRow->underlying_spot_price ?? 0);
        $atmStrike = (int) (round($spot / 50) * 50);

        // Compute PCR from full snapshot (all strikes)
        $ceOi     = $rows->where('option_type', 'CE')->sum('oi');
        $peOi     = $rows->where('option_type', 'PE')->sum('oi');
        $totalPcr = $ceOi > 0 ? round($peOi / $ceOi, 3) : null;

        // ── Sum diff_oi and diff_volume across the selected time interval ─────
        $intervalSums = DB::table($table)
            ->where('underlying_key', $underlying)
            ->where('expiry', $expiry)
            ->whereDate('captured_at', $date)
            ->whereTime('captured_at', '>=', $startTime . ':00')
            ->whereTime('captured_at', '<=', $endTime . ':59')
            ->groupBy('strike_price', 'option_type')
            ->select(
                'strike_price',
                'option_type',
                DB::raw('SUM(COALESCE(diff_oi, 0)) as total_diff_oi'),
                DB::raw('SUM(COALESCE(diff_volume, 0)) as total_diff_volume')
            )
            ->get()
            ->keyBy(function ($item) {
                return ((int)$item->strike_price) . '_' . $item->option_type;
            });

        $ceDiffOi = 0;
        $peDiffOi = 0;
        foreach ($intervalSums as $item) {
            if ($item->option_type === 'CE') {
                $ceDiffOi += (int) $item->total_diff_oi;
            } elseif ($item->option_type === 'PE') {
                $peDiffOi += (int) $item->total_diff_oi;
            }
        }
        $changePcr = $ceDiffOi != 0 ? round($peDiffOi / $ceDiffOi, 3) : null;

        // ── Pivot rows into strike-keyed array ───────────────────────────────
        $strikeMap = [];
        foreach ($rows as $r) {
            $strike  = (int) $r->strike_price;
            $type    = $r->option_type;
            $ltpVal  = (float) ($r->ltp ?? 0);
            $prevLtp = $ltpVal - (float) ($r->diff_ltp ?? 0);
            $ltpPct  = $prevLtp != 0 ? round(((float) $r->diff_ltp / $prevLtp) * 100, 2) : 0;

            $intrinsic = $type === 'CE'
                ? max(0, $spot - $strike)
                : max(0, $strike - $spot);
            $timeValue = max(0, $ltpVal - $intrinsic);

            $sumKey     = $strike . '_' . $type;
            $diffOiSum  = isset($intervalSums[$sumKey]) ? (int) $intervalSums[$sumKey]->total_diff_oi : (int) ($r->diff_oi ?? 0);
            $diffVolSum = isset($intervalSums[$sumKey]) ? (int) $intervalSums[$sumKey]->total_diff_volume : (int) ($r->diff_volume ?? 0);

            $strikeMap[$strike][$type] = [
                'ltp'         => $ltpVal,
                'diff_ltp'    => (float) ($r->diff_ltp ?? 0),
                'ltp_pct'     => $ltpPct,
                'volume'      => (int) ($r->volume ?? 0),
                'diff_volume' => $diffVolSum,
                'oi'          => (int) ($r->oi ?? 0),
                'diff_oi'     => $diffOiSum,
                'prev_oi'     => (int) ($r->prev_oi ?? 0),
                'iv'          => (float) ($r->iv ?? 0),
                'delta'       => (float) ($r->delta ?? 0),
                'vega'        => (float) ($r->vega ?? 0),
                'theta'       => (float) ($r->theta ?? 0),
                'gamma'       => (float) ($r->gamma ?? 0),
                'pcr'         => (float) ($r->pcr ?? 0),
                'build_up'    => $r->build_up,
                'intrinsic'   => round($intrinsic, 2),
                'time_value'  => round($timeValue, 2),
                'bid_price'   => (float) ($r->bid_price ?? 0),
                'ask_price'   => (float) ($r->ask_price ?? 0),
                'bid_qty'     => (int) ($r->bid_qty ?? 0),
                'ask_qty'     => (int) ($r->ask_qty ?? 0),
            ];
        }

        ksort($strikeMap);

        // ── Slice to ±N strikes around ATM ───────────────────────────────────
        $allStrikes = array_keys($strikeMap);

        // Find ATM position (exact match, or nearest below)
        $atmIndex = null;
        foreach ($allStrikes as $i => $s) {
            if ($s >= $atmStrike) { $atmIndex = $i; break; }
        }
        if ($atmIndex === null) $atmIndex = count($allStrikes) - 1;

        $from = max(0, $atmIndex - $strikesEachSide);
        $to   = min(count($allStrikes) - 1, $atmIndex + $strikesEachSide);

        $slicedStrikes = array_slice($allStrikes, $from, $to - $from + 1);
        $strikeMap = array_intersect_key($strikeMap, array_flip($slicedStrikes));

        // ── Compute highlights on visible strikes only ───────────────────────
        $highlights = $this->computeHighlights($strikeMap);

        // ── Build final rows array ───────────────────────────────────────────
        $finalRows = [];
        foreach ($strikeMap as $strike => $sides) {
            $finalRows[] = [
                'strike' => $strike,
                'is_atm' => ($strike === $atmStrike),
                'CE'     => $sides['CE'] ?? null,
                'PE'     => $sides['PE'] ?? null,
            ];
        }

        return response()->json([
            'rows'             => $finalRows,
            'spot'             => $spot,
            'atm_strike'       => $atmStrike,
            'strikes_each_side'=> $strikesEachSide,
            'total_pcr'        => $totalPcr,
            'change_pcr'       => $changePcr,
            'snapshot_at'      => $latestCapturedAt,
            'highlights'       => $highlights,
        ]);
    }

    /**
     * AJAX: Return distinct expiry dates for a given mode.
     */

    public function getExpiries(Request $request)
    {
        $mode       = $request->input('mode', 'live');
        $date       = $request->input('date', today()->toDateString());
        $underlying = $request->input('underlying', 'NSE_INDEX|Nifty 50');

        $table = $this->resolveTable($mode);

        $expiries = DB::table($table)
            ->where('underlying_key', $underlying)
            ->whereDate('captured_at', $date)
            ->select('expiry')
            ->distinct()
            ->orderBy('expiry')
            ->pluck('expiry');

        return response()->json(['expiries' => $expiries]);
    }

    /**
     * Compute which strikes have the top 1 highest positive
     * and top 1 most negative OI change & volume for each side.
     *
     * Returns: [
     *   'CE' => ['diff_oi' => ['max_strike'=>N,'min_strike'=>N], 'volume'=>...],
     *   'PE' => ['diff_oi' => [...], 'volume' => [...]],
     * ]
     */
    private function computeHighlights(array $strikeMap): array
    {
        $result = [];

        foreach (['CE', 'PE'] as $type) {
            foreach (['oi', 'diff_oi', 'volume', 'diff_volume'] as $field) {
                $maxVal    = null;
                $minVal    = null;
                $maxStrike = null;
                $minStrike = null;

                foreach ($strikeMap as $strike => $sides) {
                    if (!isset($sides[$type])) {
                        continue;
                    }
                    $val = $sides[$type][$field];

                    if ($maxVal === null || $val > $maxVal) {
                        $maxVal    = $val;
                        $maxStrike = $strike;
                    }
                    if ($minVal === null || $val < $minVal) {
                        $minVal    = $val;
                        $minStrike = $strike;
                    }
                }

                $result[$type][$field] = [
                    'max_strike' => $maxStrike,
                    'max_val'    => $maxVal,
                    'min_strike' => $minStrike,
                    'min_val'    => $minVal,
                ];
            }
        }

        return $result;
    }
}
