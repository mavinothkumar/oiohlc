<?php

namespace App\Http\Controllers;

use App\Models\BiasSnapshot;
use App\Services\SnapshotPredictionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BuildUpAnalysisController extends Controller
{
    public function index(Request $request)
    {
        $date    = $request->input('date', Carbon::today()->toDateString());
        $strikes = (int) $request->input('strikes', 2);

        $emptyDefaults = [
            'date'          => $date,
            'strikes'       => $strikes,
            'expiryDate'    => null,
            'expiry'        => null,
            'spotPrice'     => 0,
            'nearestStrike' => 0,
            'strikeList'    => [],
            'buildUpTotals' => [
                'CE' => [
                    'Long Build'  => ['oi' => 0, 'volume' => 0],
                    'Short Build' => ['oi' => 0, 'volume' => 0],
                    'Short Cover' => ['oi' => 0, 'volume' => 0],
                    'Long Unwind' => ['oi' => 0, 'volume' => 0],
                ],
                'PE' => [
                    'Long Build'  => ['oi' => 0, 'volume' => 0],
                    'Short Build' => ['oi' => 0, 'volume' => 0],
                    'Short Cover' => ['oi' => 0, 'volume' => 0],
                    'Long Unwind' => ['oi' => 0, 'volume' => 0],
                ],
            ],
            'chartLabels'   => [],
            'chartCE_OI'    => [],
            'chartPE_OI'    => [],
            'chartCE_Vol'   => [],
            'chartPE_Vol'   => [],
            'bias'          => null,
            'biasScore'     => 0,
            'biasStrength'  => null,
            'bullishOI'     => 0,
            'bearishOI'     => 0,
            'prediction'    => [
                'signal'     => 'WATCH',
                'confidence' => 0,
                'label'      => '⏳ Watching',
                'reason'     => 'No snapshots available yet.',
            ],
            'strategies'    => [],
        ];

        // ── 1. Get current active expiry ──────────────────────────────
        $expiry = DB::table('nse_expiries')
                    ->where('trading_symbol', 'NIFTY')
                    ->where('instrument_type', 'OPT')
                    ->where('is_current', 1)
                    ->first();

        if (! $expiry) {
            return view('build-up-analysis', array_merge($emptyDefaults, [
                'emptyState' => [
                    'icon'    => '🕐',
                    'title'   => 'Market Not Opened Yet',
                    'message' => 'No active expiry found for NIFTY. The market may not have opened yet or expiry data is not populated.',
                    'hint'    => 'Expiry data is usually available from 09:15 AM on trading days.',
                ],
            ]));
        }

        $expiryDate = $expiry->expiry_date;

        // ── 2. Get latest spot price ──────────────────────────────────
        $latest = DB::table('option_chains')
                    ->where('trading_symbol', 'NIFTY')
                    ->where('expiry', $expiryDate)
                    ->orderByDesc('captured_at')
                    ->first(['underlying_spot_price']);

        if (! $latest) {
            return view('build-up-analysis', array_merge($emptyDefaults, [
                'expiryDate' => $expiryDate,
                'expiry'     => $expiry,
                'emptyState' => [
                    'icon'    => '📭',
                    'title'   => 'No Option Chain Data',
                    'message' => 'Option chain data for NIFTY has not been populated yet for ' . $expiryDate . '.',
                    'hint'    => 'Data starts flowing in after market opens at 09:15 AM IST.',
                ],
            ]));
        }

        // ── 3. Compute ATM strike & strike list ───────────────────────
        $spotPrice     = $latest->underlying_spot_price;
        $nearestStrike = round($spotPrice / 50) * 50;

        $strikeList = [];
        for ($i = -$strikes; $i <= $strikes; $i++) {
            $strikeList[] = $nearestStrike + ($i * 50);
        }

        // ── 4. Fetch option_chains rows for selected strikes ──────────
        $startTime = $date . ' 09:15:00';
        $endTime   = $date . ' 15:30:00';

        $rows = DB::table('option_chains')
                  ->where('trading_symbol', 'NIFTY')
                  ->where('expiry', $expiryDate)
                  ->whereIn('strike_price', $strikeList)
                  ->whereBetween('captured_at', [$startTime, $endTime])
                  ->orderBy('captured_at')
                  ->get(['strike_price', 'option_type', 'diff_oi', 'diff_volume', 'diff_ltp', 'build_up', 'captured_at']);

        // ── 5. Aggregate build-up totals ──────────────────────────────
        $buildUpTotals = [
            'CE' => [
                'Long Build'  => ['oi' => 0, 'volume' => 0],
                'Short Build' => ['oi' => 0, 'volume' => 0],
                'Short Cover' => ['oi' => 0, 'volume' => 0],
                'Long Unwind' => ['oi' => 0, 'volume' => 0],
            ],
            'PE' => [
                'Long Build'  => ['oi' => 0, 'volume' => 0],
                'Short Build' => ['oi' => 0, 'volume' => 0],
                'Short Cover' => ['oi' => 0, 'volume' => 0],
                'Long Unwind' => ['oi' => 0, 'volume' => 0],
            ],
        ];

        foreach ($rows as $row) {
            $diffOi  = $row->diff_oi     ?? 0;
            $diffLtp = $row->diff_ltp    ?? 0;
            $diffVol = $row->diff_volume ?? 0;
            $type    = $row->option_type; // 'CE' or 'PE'

            $buildUp = $row->build_up ?? $this->classifyBuildUp($diffOi, $diffLtp);

            if ($buildUp && isset($buildUpTotals[$type][$buildUp])) {
                $buildUpTotals[$type][$buildUp]['oi']     += abs($diffOi);
                $buildUpTotals[$type][$buildUp]['volume'] += abs($diffVol);
            }
        }

        // ── 6. Compute bias score from live build-up totals ───────────
        //      CE: Long Build=+2, Short Cover=+1, Short Build=-2, Long Unwind=-1
        //      PE: Short Build=+2, Long Unwind=+1, Long Build=-2, Short Cover=-1
        $bullishOI =
            ($buildUpTotals['CE']['Long Build']['oi']  * 2) +
            ($buildUpTotals['CE']['Short Cover']['oi'] * 1) +
            ($buildUpTotals['PE']['Short Build']['oi'] * 2) +
            ($buildUpTotals['PE']['Long Unwind']['oi'] * 1);

        $bearishOI =
            ($buildUpTotals['CE']['Short Build']['oi'] * 2) +
            ($buildUpTotals['CE']['Long Unwind']['oi'] * 1) +
            ($buildUpTotals['PE']['Long Build']['oi']  * 2) +
            ($buildUpTotals['PE']['Short Cover']['oi'] * 1);

        $totalWeightedOI = $bullishOI + $bearishOI;

        $biasScore = $totalWeightedOI > 0
            ? round((($bullishOI - $bearishOI) / $totalWeightedOI) * 100)
            : 0;

        $bias = match (true) {
            $biasScore > 20  => 'Bullish',
            $biasScore < -20 => 'Bearish',
            default          => 'Sideways',
        };

        $biasStrength = match (true) {
            abs($biasScore) >= 60 => 'Strong',
            abs($biasScore) >= 35 => 'Moderate',
            default               => 'Weak',
        };

        // ── 7. Chart data ─────────────────────────────────────────────
        $chartLabels = ['Long Build', 'Short Build', 'Short Cover', 'Long Unwind'];
        $chartCE_OI  = array_column($buildUpTotals['CE'], 'oi');
        $chartPE_OI  = array_column($buildUpTotals['PE'], 'oi');
        $chartCE_Vol = array_column($buildUpTotals['CE'], 'volume');
        $chartPE_Vol = array_column($buildUpTotals['PE'], 'volume');

        // ── 8. Load BiasSnapshot history for prediction ───────────────
        //      No bias recomputation — read what the command already saved
        $history = BiasSnapshot::where('trading_symbol', 'NIFTY')
                               ->whereDate('date', $date)
                               ->orderBy('captured_at')
                               ->get();

        $prediction = [
            'signal'     => 'WATCH',
            'confidence' => 0,
            'label'      => '⏳ Watching',
            'reason'     => 'No snapshots saved yet. Snapshots are captured every 5 minutes during market hours.',
        ];
        $strategies = [];

        if ($history->isNotEmpty()) {
            $current           = $history->last();
            $predictionService = new SnapshotPredictionService();
            $strategies        = $predictionService->predict($current, $history);
            $prediction        = $predictionService->aggregate($strategies);
        }

        // ── 9. Return view ────────────────────────────────────────────
        return view('build-up-analysis', compact(
            'date', 'strikes', 'expiry', 'expiryDate',
            'spotPrice', 'nearestStrike', 'strikeList',
            'buildUpTotals',
            'chartLabels', 'chartCE_OI', 'chartPE_OI', 'chartCE_Vol', 'chartPE_Vol',
            'bias', 'biasScore', 'biasStrength',
            'bullishOI', 'bearishOI',
            'prediction', 'strategies'
        ));
    }

    // ── Helper: classify build-up from diff_oi & diff_ltp ─────────────
    private function classifyBuildUp(int|float $diffOi, int|float $diffLtp): ?string
    {
        if ($diffOi == 0 || $diffLtp == 0) return null;
        if ($diffOi > 0 && $diffLtp > 0)   return 'Long Build';
        if ($diffOi > 0 && $diffLtp < 0)   return 'Short Build';
        if ($diffOi < 0 && $diffLtp < 0)   return 'Long Unwind';
        if ($diffOi < 0 && $diffLtp > 0)   return 'Short Cover';
        return null;
    }
}
