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
        $strikesCount = $request->input('strikes', 10);
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

        // Get market open and mid point configurations
        $trend = DB::table('daily_trend')
                   ->where('trading_date', $selectedDate)
                   ->where('symbol_name', 'NIFTY')
                   ->select('mid_point', 'current_day_index_open', 'atm_index_open')
                   ->first();

        // 3. Time Filter Rules
        $startTime = $request->input('start_time', '09:15');
        $endTime = $request->input('end_time', '15:25');

        $startDateTime = "{$selectedDate} {$startTime}:00";
        $endDateTime = "{$selectedDate} {$endTime}:00";

        $table_name = getTableName('option_chains');

        // [Requirement 3] Fetch the latest live spot price record to determine current Live ATM strike
        $liveSpotRow = DB::table($table_name)->orderByDesc('id')->select('underlying_spot_price')->first();
        $liveSpotPrice = $liveSpotRow ? $liveSpotRow->underlying_spot_price : ($trend ? $trend->atm_index_open : 0);
        $currentNearestStrike = round($liveSpotPrice / 50) * 50;

        // Base anchor using open price setup [Requirement 1]
        $openSpotPrice = $trend ? $trend->atm_index_open : 0;
        $openNearestStrike = round($openSpotPrice / 50) * 50;

        // ── Build strike list around open anchor ───────────────────
        $strikeList = [];
        for ($i = -$strikesCount; $i <= $strikesCount; $i++) {
            $strikeList[] = $openNearestStrike + ($i * 50);
        }

        // 5. Query primary Option Chain Data (Including requirement 4: diff_ltp)
        $rawChainData = DB::table($table_name)
                          ->where('expiry', $selectedExpiry)
                          ->whereIn('strike_price', $strikeList)
                          ->whereBetween('captured_at', [$startDateTime, $endDateTime])
                          ->select(
                              'strike_price',
                              'option_type',
                              'oi',
                              'diff_oi',
                              'diff_ltp', // Captured target metric field
                              'build_up',
                              DB::raw("DATE_FORMAT(captured_at, '%H:%i') as time_label")
                          )
                          ->orderBy('strike_price', 'asc')
                          ->orderBy('captured_at', 'asc')
                          ->get();

        // 6. Restructure Data for Matrix Map Engine
        $strikes = $rawChainData->pluck('strike_price')->unique()->values()->toArray();
        $timeSeries = $rawChainData->pluck('time_label')->unique()->values()->toArray();

        $processedData = [];
        foreach ($rawChainData as $row) {
            $processedData[$row->strike_price][$row->option_type][$row->time_label] = [
                'oi' => $row->oi,
                'diff_oi' => $row->diff_oi,
                'diff_ltp' => $row->diff_ltp,
                'build_up' => $row->build_up
            ];
        }

        // [Requirement 2] Extract highest open interest concentrations at the latest captured timestamp
        $highestCeStrike = null;
        $highestPeStrike = null;
        $maxCeOi = -1;
        $maxPeOi = -1;
        $latestTimeLabel = count($timeSeries) > 0 ? $timeSeries[count($timeSeries) - 1] : null;

        if ($latestTimeLabel) {
            foreach ($rawChainData as $dataItem) {
                if ($dataItem->time_label === $latestTimeLabel) {
                    if ($dataItem->option_type === 'CE' && $dataItem->oi > $maxCeOi) {
                        $maxCeOi = $dataItem->oi;
                        $highestCeStrike = $dataItem->strike_price;
                    }
                    if ($dataItem->option_type === 'PE' && $dataItem->oi > $maxPeOi) {
                        $maxPeOi = $dataItem->oi;
                        $highestPeStrike = $dataItem->strike_price;
                    }
                }
            }
        }

        return view('option-chain.dashboard', [
            'selectedDate' => $selectedDate,
            'selectedExpiry' => $selectedExpiry,
            'startTime' => $startTime,
            'endTime' => $endTime,
            'spotPrice' => $liveSpotPrice,
            'strikes' => $strikes,
            'timeSeries' => $timeSeries,
            'matrix' => $processedData,
            'trend' => $trend,
            // Context Variables for Indicators
            'openStrike' => $openNearestStrike,
            'currentNearestStrike' => $currentNearestStrike,
            'highestCeStrike' => $highestCeStrike,
            'highestPeStrike' => $highestPeStrike
        ]);
    }
}
