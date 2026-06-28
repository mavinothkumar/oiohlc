<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OptionChainBuildupController extends Controller
{
    public function index(Request $request)
    {
        // 1. Determine Selected or Default Working Date
        $selectedDate = $request->input('date');
        $strikes = $request->input('strikes', 10);
        if (!$selectedDate) {
             $workingDay = DB::table('nse_working_days')->where('current', '1')->first();
            $selectedDate = $workingDay ? $workingDay->working_date : Carbon::today()->toDateString();
        }

        // 2. Determine Selected or Default Current Expiry
        $selectedExpiry = $request->input('expiry');
        if (!$selectedExpiry) {
            $currentExpiry = DB::table('nse_expiries')
                               ->where('is_current', 1)
                               ->first();
            $selectedExpiry = $currentExpiry ? $currentExpiry->expiry_date : null;
        }

        // 3. Time Filter Rules
        $startTime = $request->input('start_time', '09:15');
        $endTime = $request->input('end_time', '15:25');

        $startDateTime = "{$selectedDate} {$startTime}:00";
        $endDateTime = "{$selectedDate} {$endTime}:00";

        $table_name = getTableName('option_chains');

        // 4. Fetch Underlying Spot to anchor our strike prices center
        $spotData = DB::table($table_name)->orderByDesc('id')->limit(1)->first();

        $spotPrice = $spotData ? $spotData->underlying_spot_price : 0;

        $nearestStrike = round($spotPrice / 50) * 50;

        // ── 3. Build strike list ───────────────────────────────────────
        $strikeList = [];
        for ($i = -$strikes; $i <= $strikes; $i++) {
            $strikeList[] = $nearestStrike + ($i * 50);
        }

        // 5. Query primary Option Chain Data filtered by range
        $rawChainData = DB::table($table_name)
                          ->where('expiry', $selectedExpiry)
                          ->whereIn('strike_price', $strikeList)
                          ->whereBetween('captured_at', [$startDateTime, $endDateTime])
                          ->select(
                              'strike_price',
                              'option_type',
                              'oi',
                              'diff_oi',
                              'build_up',
                              DB::raw("DATE_FORMAT(captured_at, '%H:%i') as time_label")
                          )
                          ->orderBy('strike_price', 'asc')
                          ->orderBy('captured_at', 'asc')
                          ->get();

        // 6. Restructure Data for Blade Templates & Charts
        $strikes = $rawChainData->pluck('strike_price')->unique()->values()->toArray();
        $timeSeries = $rawChainData->pluck('time_label')->unique()->values()->toArray();

        // Organize data into map for easy reference in view logic
        $processedData = [];
        foreach ($rawChainData as $row) {
            $processedData[$row->strike_price][$row->option_type][$row->time_label] = [
                'oi' => $row->oi,
                'diff_oi' => $row->diff_oi,
                'build_up' => $row->build_up
            ];
        }

        return view('option-chain.dashboard', [
            'selectedDate' => $selectedDate,
            'selectedExpiry' => $selectedExpiry,
            'startTime' => $startTime,
            'endTime' => $endTime,
            'spotPrice' => $spotPrice,
            'strikes' => $strikes,
            'timeSeries' => $timeSeries,
            'matrix' => $processedData
        ]);
    }
}
