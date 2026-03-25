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
            return back()->with('error', 'No current expiry found for NIFTY.');
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
            return back()->with('error', 'No option chain data found.');
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
            'Long Build'   => ['oi' => 0, 'volume' => 0],
            'Short Build'  => ['oi' => 0, 'volume' => 0],
            'Long Unwind'  => ['oi' => 0, 'volume' => 0],
            'Short Cover'  => ['oi' => 0, 'volume' => 0],
        ];

        foreach ($rows as $row) {
            $buildUp = $row->build_up ?? $this->classifyBuildUp($row->diff_oi, $row->diff_ltp);
            if ($buildUp && isset($buildUpTotals[$buildUp])) {
                $buildUpTotals[$buildUp]['oi']     += abs($row->diff_oi);
                $buildUpTotals[$buildUp]['volume'] += abs($row->diff_volume);
            }
        }

        $chartLabels = array_keys($buildUpTotals);
        $chartOI     = array_column($buildUpTotals, 'oi');
        $chartVolume = array_column($buildUpTotals, 'volume');

        // ── Market Bias Prediction ──────────────────────────────────────────
        $bullishOI = ($buildUpTotals['Long Build']['oi']  * 2)
                     + ($buildUpTotals['Short Cover']['oi'] * 1);

        $bearishOI = ($buildUpTotals['Short Build']['oi']  * 2)
                     + ($buildUpTotals['Long Unwind']['oi']  * 1);

        $totalWeightedOI = $bullishOI + $bearishOI;

// Score: +100 fully bullish, -100 fully bearish, 0 neutral
        $biasScore = $totalWeightedOI > 0
            ? round((($bullishOI - $bearishOI) / $totalWeightedOI) * 100)
            : 0;

// Sideways band: within ±20
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

        return view('build-up-analysis', compact(
            'date', 'strikes', 'expiry', 'expiryDate',
            'spotPrice', 'nearestStrike', 'strikeList',
            'buildUpTotals', 'chartLabels', 'chartOI', 'chartVolume',
            'bias', 'biasScore', 'biasStrength', 'bullishOI', 'bearishOI'
        ));
    }

    private function classifyBuildUp(int|float $diffOi, int|float $diffLtp): ?string
    {
        if ($diffOi > 0 && $diffLtp > 0) return 'Long Build';
        if ($diffOi > 0 && $diffLtp < 0) return 'Short Build';
        if ($diffOi < 0 && $diffLtp < 0) return 'Long Unwind';
        if ($diffOi < 0 && $diffLtp > 0) return 'Short Cover';
        return null;
    }
}
