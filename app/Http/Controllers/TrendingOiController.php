<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TrendingOiController extends Controller
{
    /**
     * Resolve the database table name based on mode.
     */
    private function resolveTable(string $mode): string
    {
        if ($mode === 'history') {
            return 'option_chains_history';
        }
        return getTableName('option_chains');
    }

    /**
     * Main Trending OI view.
     */
    public function index(Request $request)
    {
        $workingDay = DB::table('nse_working_days')->where('current', 1)->first()
            ?? DB::table('nse_working_days')->where('previous', 1)->orderByDesc('working_date')->first();

        $today = $workingDay ? $workingDay->working_date : today()->toDateString();

        $defaultExpiry = DB::table('nse_expiries')
            ->where('trading_symbol', 'NIFTY')
            ->where('instrument_type', 'OPT')
            ->where('is_current', 1)
            ->value('expiry_date') ?? $today;

        $table = getTableName('option_chains');
        $expiries = DB::table($table)
            ->where('underlying_key', 'NSE_INDEX|Nifty 50')
            ->select('expiry')
            ->distinct()
            ->orderBy('expiry')
            ->pluck('expiry');

        if ($expiries->isEmpty()) {
            $expiries = DB::table('nse_expiries')
                ->where('trading_symbol', 'NIFTY')
                ->where('instrument_type', 'OPT')
                ->orderBy('expiry_date')
                ->pluck('expiry_date');
        }

        $selectedDate   = $request->input('date', $today);
        $selectedExpiry = $request->input('expiry', $defaultExpiry);
        $mode           = $request->input('mode', 'live');
        $interval       = $request->input('interval', '5');

        return view('trending-oi', compact(
            'today',
            'selectedDate',
            'selectedExpiry',
            'expiries',
            'mode',
            'interval'
        ));
    }

    /**
     * AJAX: Get time series data and summary for Trending OI.
     */
    public function getData(Request $request)
    {
        $mode       = $request->input('mode', 'live');
        $expiry     = $request->input('expiry');
        $date       = $request->input('date', today()->toDateString());
        $underlying = $request->input('underlying', 'NSE_INDEX|Nifty 50');
        $interval   = (int) $request->input('interval', 5); // 1, 3, 5, 15
        $customStrikes = $request->input('strikes'); // optional comma separated or array

        if (!$expiry) {
            return response()->json(['error' => 'Expiry is required'], 422);
        }

        $table = $this->resolveTable($mode);

        // Fetch distinct timestamps available for this date & expiry
        $timestamps = DB::table($table)
            ->where('underlying_key', $underlying)
            ->where('expiry', $expiry)
            ->whereDate('captured_at', $date)
            ->select('captured_at')
            ->distinct()
            ->orderBy('captured_at', 'asc')
            ->pluck('captured_at')
            ->toArray();

        if (empty($timestamps)) {
            return response()->json([
                'rows'              => [],
                'chart'             => ['labels' => [], 'call_oi_chg' => [], 'put_oi_chg' => [], 'sentiment' => [], 'spot' => []],
                'selected_strikes'  => [],
                'all_strikes'       => [],
                'underlying_data'   => null,
                'atm_strike'        => null,
            ]);
        }

        // Get latest available snapshot for current spot price & available strikes
        $latestTimestamp = end($timestamps);
        $latestRows = DB::table($table)
            ->where('underlying_key', $underlying)
            ->where('expiry', $expiry)
            ->where('captured_at', $latestTimestamp)
            ->get(['strike_price', 'underlying_spot_price', 'option_type', 'ltp', 'oi']);

        $spot = (float) ($latestRows->first()->underlying_spot_price ?? 0);
        $atmStrike = (int) (round($spot / 50) * 50);

        // All available strikes for this expiry
        $allStrikes = DB::table($table)
            ->where('underlying_key', $underlying)
            ->where('expiry', $expiry)
            ->whereDate('captured_at', $date)
            ->select('strike_price')
            ->distinct()
            ->orderBy('strike_price')
            ->pluck('strike_price')
            ->map(fn($s) => (int) $s)
            ->toArray();

        // Determine selected strikes (15 strikes around ATM by default)
        if (!empty($customStrikes)) {
            if (is_string($customStrikes)) {
                $selectedStrikes = array_map('intval', explode(',', $customStrikes));
            } else {
                $selectedStrikes = array_map('intval', (array) $customStrikes);
            }
            sort($selectedStrikes);
        } else {
            // Find ATM index in allStrikes
            $atmIdx = array_search($atmStrike, $allStrikes);
            if ($atmIdx === false) {
                // Nearest strike
                $closestIdx = 0;
                $minDiff = PHP_INT_MAX;
                foreach ($allStrikes as $idx => $s) {
                    $diff = abs($s - $atmStrike);
                    if ($diff < $minDiff) {
                        $minDiff = $diff;
                        $closestIdx = $idx;
                    }
                }
                $atmIdx = $closestIdx;
            }

            $startIdx = max(0, $atmIdx - 7);
            $selectedStrikes = array_slice($allStrikes, $startIdx, 15);
        }

        // Filter timestamps according to selected interval (if interval > 1, sample appropriately)
        $sampledTimestamps = [];
        $lastMinute = -1;
        foreach ($timestamps as $ts) {
            $carbon = Carbon::parse($ts);
            $minute = (int) $carbon->format('i');
            if ($interval <= 1 || ($minute % $interval === 0 && $minute !== $lastMinute) || $ts === end($timestamps) || $ts === reset($timestamps)) {
                $sampledTimestamps[] = $ts;
                $lastMinute = $minute;
            }
        }
        if (empty($sampledTimestamps)) {
            $sampledTimestamps = $timestamps;
        }

        // Fetch all rows for selected strikes at the sampled timestamps
        $dataRows = DB::table($table)
            ->where('underlying_key', $underlying)
            ->where('expiry', $expiry)
            ->whereDate('captured_at', $date)
            ->whereIn('captured_at', $sampledTimestamps)
            ->whereIn('strike_price', $selectedStrikes)
            ->get([
                'captured_at',
                'strike_price',
                'option_type',
                'oi',
                'diff_oi',
                'ltp',
                'diff_ltp',
                'underlying_spot_price',
                'close_price'
            ]);

        // Group rows by captured_at
        $grouped = [];
        foreach ($dataRows as $r) {
            $ts = $r->captured_at;
            if (!isset($grouped[$ts])) {
                $grouped[$ts] = [
                    'captured_at' => $ts,
                    'spot'        => (float) $r->underlying_spot_price,
                    'CE'          => ['oi' => 0, 'ltp' => 0],
                    'PE'          => ['oi' => 0, 'ltp' => 0],
                ];
            }
            $type = $r->option_type;
            if (isset($grouped[$ts][$type])) {
                $grouped[$ts][$type]['oi']  += (int) ($r->oi ?? 0);
                $grouped[$ts][$type]['ltp'] += (float) ($r->ltp ?? 0);
            }
        }

        if (empty($grouped)) {
            return response()->json([
                'rows'             => [],
                'chart'            => ['labels' => [], 'call_oi_chg' => [], 'put_oi_chg' => [], 'sentiment' => [], 'spot' => []],
                'selected_strikes' => $selectedStrikes,
                'all_strikes'      => $allStrikes,
                'underlying_data'  => null,
                'atm_strike'       => $atmStrike,
            ]);
        }

        // Sort grouped data chronologically (ascending) for base and cumulative calculations
        ksort($grouped);

        // Determine base snapshot (09:15:00 or first available)
        $firstEntry = reset($grouped);
        $baseCeOi   = $firstEntry['CE']['oi'];
        $basePeOi   = $firstEntry['PE']['oi'];
        $baseCeLtp  = $firstEntry['CE']['ltp'];
        $basePeLtp  = $firstEntry['PE']['ltp'];
        $baseSpot   = $firstEntry['spot'];

        // Track running Day High & Low for Spot and for Diff in OI
        $runningDayHighSpot = $baseSpot;
        $runningDayLowSpot  = $baseSpot;
        $runningMaxDiffOi   = null;
        $runningMinDiffOi   = null;

        $previousDiffOi = null;
        $calculatedRows = [];

        foreach ($grouped as $ts => $g) {
            $spotVal = $g['spot'];
            $curCeOi = $g['CE']['oi'];
            $curPeOi = $g['PE']['oi'];
            $curCeLtp = round($g['CE']['ltp'], 2);
            $curPeLtp = round($g['PE']['ltp'], 2);

            // 1. Chng in Call & Put OI
            $chngCallOi = $curCeOi - $baseCeOi;
            $chngPutOi  = $curPeOi - $basePeOi;

            // 2. Diff in OI (Put Chng - Call Chng)
            $diffOi = $chngPutOi - $chngCallOi;

            // 3. Direction of Chng & Chng in Direction
            $chngInDirection = ($previousDiffOi !== null) ? ($diffOi - $previousDiffOi) : 0;
            $directionPct = 0;
            if ($previousDiffOi !== null && $previousDiffOi != 0) {
                $directionPct = round(($chngInDirection / abs($previousDiffOi)) * 100, 2);
            }

            // 4. Strength: Difference / (abs(Call Chng) + abs(Put Chng)) * 100
            $totalChngOi = abs($chngCallOi) + abs($chngPutOi);
            $strengthPct = $totalChngOi > 0 ? round(($diffOi / $totalChngOi) * 100) : 0;

            // 5. LTP metrics
            $callLtpChng = round($curCeLtp - $baseCeLtp, 2);
            $putLtpChng  = round($curPeLtp - $basePeLtp, 2);
            $cePeLtpChng = round($callLtpChng + $putLtpChng, 2);

            // 6. Net PCR
            $netPcr = $chngCallOi != 0 ? round($chngPutOi / $chngCallOi, 2) : ($curCeOi > 0 ? round($curPeOi / $curCeOi, 2) : 0);

            // 7. Day H/L Break for Spot
            $dayHlBreak = '-';
            if ($spotVal > $runningDayHighSpot) {
                $runningDayHighSpot = $spotVal;
                $dayHlBreak = 'D.H.B. (' . number_format($spotVal, 2) . ')';
            } elseif ($spotVal < $runningDayLowSpot) {
                $runningDayLowSpot = $spotVal;
                $dayHlBreak = 'D.L.B. (' . number_format($spotVal, 2) . ')';
            }

            // 8. Day H/L Diff in OI
            $dayHlDiffOi = '-';
            if ($runningMaxDiffOi === null || $diffOi > $runningMaxDiffOi) {
                $runningMaxDiffOi = $diffOi;
                if ($previousDiffOi !== null) {
                    $dayHlDiffOi = 'D.H.B';
                }
            }
            if ($runningMinDiffOi === null || $diffOi < $runningMinDiffOi) {
                $runningMinDiffOi = $diffOi;
                if ($previousDiffOi !== null) {
                    $dayHlDiffOi = 'D.L.B';
                }
            }

            // 9. Sentiment
            $sentiment = $diffOi >= 0 ? 'Bullish' : 'Bearish';

            $cTime = Carbon::parse($ts);

            $calculatedRows[] = [
                'date'              => $cTime->format('d-m-Y'),
                'time'              => $cTime->format('H:i:s'),
                'time_short'        => $cTime->format('H:i'),
                'timestamp'         => $ts,
                'spot'              => $spotVal,
                'day_hl_break'      => $dayHlBreak,
                'chng_call_oi'      => $chngCallOi,
                'chng_put_oi'       => $chngPutOi,
                'diff_oi'           => $diffOi,
                'strength'          => $strengthPct,
                'direction_pct'     => $directionPct,
                'chng_in_direction' => $chngInDirection,
                'total_call_ltp'    => $curCeLtp,
                'call_ltp_chng'     => $callLtpChng,
                'ce_pe_ltp_chng'    => $cePeLtpChng,
                'put_ltp_chng'      => $putLtpChng,
                'total_put_ltp'     => $curPeLtp,
                'net_pcr'           => $netPcr,
                'day_hl_diff_oi'    => $dayHlDiffOi,
                'sentiment'         => $sentiment,
            ];

            $previousDiffOi = $diffOi;
        }

        // Prepare chart payload (in chronological ascending order)
        $chartLabels    = [];
        $chartCallOi    = [];
        $chartPutOi     = [];
        $chartSentiment = [];
        $chartSpot      = [];

        foreach ($calculatedRows as $row) {
            $chartLabels[]    = $row['time_short'];
            $chartCallOi[]    = $row['chng_call_oi'];
            $chartPutOi[]     = $row['chng_put_oi'];
            $chartSentiment[] = $row['diff_oi'];
            $chartSpot[]      = $row['spot'];
        }

        // Prepare table rows (in descending order: latest time at the top)
        $tableRows = array_reverse($calculatedRows);

        // Calculate spot change relative to first bar
        $latestSpot = end($calculatedRows)['spot'] ?? 0;
        $spotChange = $latestSpot - $baseSpot;
        $spotChangePct = $baseSpot > 0 ? round(($spotChange / $baseSpot) * 100, 2) : 0;

        $underlyingData = [
            'name'       => 'NIFTY 50',
            'spot'       => $latestSpot,
            'change'     => round($spotChange, 2),
            'change_pct' => $spotChangePct,
            'time'       => Carbon::parse($latestTimestamp)->format('d M Y, H:i:s') . ' IST',
        ];

        return response()->json([
            'rows'              => $tableRows,
            'chart'             => [
                'labels'     => $chartLabels,
                'call_oi'    => $chartCallOi,
                'put_oi'     => $chartPutOi,
                'sentiment'  => $chartSentiment,
                'spot'       => $chartSpot,
            ],
            'selected_strikes'  => $selectedStrikes,
            'all_strikes'       => $allStrikes,
            'underlying_data'   => $underlyingData,
            'atm_strike'        => $atmStrike,
        ]);
    }

    /**
     * AJAX: Get distinct expiries.
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

        if ($expiries->isEmpty()) {
            $expiries = DB::table('nse_expiries')
                ->where('trading_symbol', 'NIFTY')
                ->where('instrument_type', 'OPT')
                ->orderBy('expiry_date')
                ->pluck('expiry_date');
        }

        return response()->json(['expiries' => $expiries]);
    }

    /**
     * AJAX: Get all available strikes for strike selection modal.
     */
    public function getStrikes(Request $request)
    {
        $mode       = $request->input('mode', 'live');
        $expiry     = $request->input('expiry');
        $date       = $request->input('date', today()->toDateString());
        $underlying = $request->input('underlying', 'NSE_INDEX|Nifty 50');

        $table = $this->resolveTable($mode);

        $strikes = DB::table($table)
            ->where('underlying_key', $underlying)
            ->where('expiry', $expiry)
            ->whereDate('captured_at', $date)
            ->select('strike_price')
            ->distinct()
            ->orderBy('strike_price')
            ->pluck('strike_price')
            ->map(fn($s) => (int) $s);

        return response()->json(['strikes' => $strikes]);
    }
}
