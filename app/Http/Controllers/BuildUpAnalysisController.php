<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BuildUpAnalysisController extends Controller
{
    public function index(Request $request)
    {
        $date    = $request->input('date', Carbon::today()->toDateString());
        $strikes = (int) $request->input('strikes', 2);

        // 1. Get expiry for the given date
        $expiry = DB::table('nse_expiries')
                    ->where('trading_symbol', 'NIFTY')
                    ->where('instrument_type', 'OPT')
                    ->where('is_current', 1)
                    ->first();

        if (! $expiry) {
            return view('build-up-analysis', [
                'emptyState' => [
                    'icon'    => '🕐',
                    'title'   => 'Market Not Opened Yet',
                    'message' => 'No active expiry found for NIFTY. The market may not have opened yet or expiry data is not populated.',
                    'hint'    => 'Expiry data is usually available from 09:15 AM on trading days.',
                ],
                // pass defaults so blade doesn't break
                'date'    => $date,
                'strikes' => $strikes,
            ]);
        }

        $expiryDate = $expiry->expiry_date;
        //$expiryDate = '2026-03-24';
        // 2. Get the latest underlying_spot_price from option_chains
        $latest = DB::table('option_chains')
                    ->where('trading_symbol', 'NIFTY')
                    ->where('expiry', $expiryDate)
                    ->orderByDesc('captured_at')
                    ->first(['underlying_spot_price']);

        if (! $latest) {
            return view('build-up-analysis', [
                'emptyState' => [
                    'icon'    => '📭',
                    'title'   => 'No Option Chain Data',
                    'message' => 'Option chain data for NIFTY has not been populated yet for ' . $expiryDate . '.',
                    'hint'    => 'Data starts flowing in after market opens at 09:15 AM IST.',
                ],
                'date'    => $date,
                'strikes' => $strikes,
            ]);
        }

        // 3. Round spot to nearest 50 and get surrounding strikes
        $spotPrice   = $latest->underlying_spot_price;
        $nearestStrike = round($spotPrice / 50) * 50;

        $strikeList = [];
        for ($i = -$strikes; $i <= $strikes; $i++) {
            $strikeList[] = $nearestStrike + ($i * 50);
        }

        // 4. Fetch option_chains data for selected strikes from 09:15 to 15:30
        $startTime = $date . ' 09:15:00';
        $endTime   = $date . ' 15:30:00';

        $rows = DB::table('option_chains')
                  ->where('trading_symbol', 'NIFTY')
                  ->where('expiry', $expiryDate)
                  ->whereIn('strike_price', $strikeList)
                  ->whereBetween('captured_at', [$startTime, $endTime])
                  ->orderBy('captured_at')
                  ->get(['strike_price', 'option_type', 'diff_oi', 'diff_volume', 'diff_ltp', 'build_up', 'captured_at']);

        // 5. Classify build_up if null using diff_oi and diff_ltp
        //    Long Build:   diff_oi > 0 && diff_ltp > 0
        //    Short Build:  diff_oi > 0 && diff_ltp < 0
        //    Long Unwind:  diff_oi < 0 && diff_ltp < 0
        //    Short Cover:  diff_oi < 0 && diff_ltp > 0
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

// ── Market Bias from CE + PE separately ───────────────────────────
// CE: Long Build=+2, Short Cover=+1, Short Build=-2, Long Unwind=-1
// PE: Short Build=+2, Long Unwind=+1, Long Build=-2, Short Cover=-1
        $bullishOI =
            ($buildUpTotals['CE']['Long Build']['oi']  * 2) +
            ($buildUpTotals['CE']['Short Cover']['oi'] * 1) +
            ($buildUpTotals['PE']['Short Build']['oi'] * 2) +
            ($buildUpTotals['PE']['Long Unwind']['oi'] * 1);

        $bearishOI =
            ($buildUpTotals['CE']['Short Build']['oi']  * 2) +
            ($buildUpTotals['CE']['Long Unwind']['oi']  * 1) +
            ($buildUpTotals['PE']['Long Build']['oi']   * 2) +
            ($buildUpTotals['PE']['Short Cover']['oi']  * 1);

        $totalWeightedOI = $bullishOI + $bearishOI;

        $biasScore = $totalWeightedOI > 0
            ? round((($bullishOI - $bearishOI) / $totalWeightedOI) * 100)
            : 0;

        $bias = match(true) {
            $biasScore >  20 => 'Bullish',
            $biasScore < -20 => 'Bearish',
            default          => 'Sideways',
        };

        $biasStrength = match(true) {
            abs($biasScore) >= 60 => 'Strong',
            abs($biasScore) >= 35 => 'Moderate',
            default               => 'Weak',
        };

// ── Chart data — CE and PE as grouped bars ─────────────────────────
        $chartLabels = ['Long Build', 'Short Build', 'Short Cover', 'Long Unwind'];

        $chartCE_OI  = array_column($buildUpTotals['CE'], 'oi');
        $chartPE_OI  = array_column($buildUpTotals['PE'], 'oi');
        $chartCE_Vol = array_column($buildUpTotals['CE'], 'volume');
        $chartPE_Vol = array_column($buildUpTotals['PE'], 'volume');

        return view('build-up-analysis', compact(
            'date', 'strikes', 'expiry', 'expiryDate',
            'spotPrice', 'nearestStrike', 'strikeList',
            'buildUpTotals', 'chartLabels',
            'chartCE_OI', 'chartPE_OI', 'chartCE_Vol', 'chartPE_Vol',
            'bias', 'biasScore', 'biasStrength', 'bullishOI', 'bearishOI'
        ));

    }

    private function classifyBuildUp(int|float $diffOi, int|float $diffLtp): ?string
    {
        if ($diffOi == 0 || $diffLtp == 0) return null; // skip neutral rows
        if ($diffOi > 0 && $diffLtp > 0) return 'Long Build';
        if ($diffOi > 0 && $diffLtp < 0) return 'Short Build';
        if ($diffOi < 0 && $diffLtp < 0) return 'Long Unwind';
        if ($diffOi < 0 && $diffLtp > 0) return 'Short Cover';
        return null;
    }
}
