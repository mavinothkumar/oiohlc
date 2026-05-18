<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StrikeDetailController extends Controller
{
    public function index()
    {
        // Get default values
        $currentDate = now()->toDateString();
        $currentExpiry = $this->getCurrentExpiry();
        $currentSpot = $this->getCurrentSpot();
        $atmStrike = $this->getATMStrike($currentSpot);
        $workingDates = $this->getWorkingDates();

        return view('strike-detail', compact(
            'currentDate',
            'currentExpiry',
            'currentSpot',
            'atmStrike',
            'workingDates'
        ));
    }

    public function getStrikeData(Request $request)
    {
        // Validate and get filters
        $date = $request->input('date', now()->toDateString());
        $expiry = $request->input('expiry', $this->getCurrentExpiry());
        $strike = $request->input('strike', $this->getATMStrike($this->getCurrentSpot()));

        // Get all 5-minute interval data for this strike
        $strikeData = $this->getStrikeTimeSeries($date, $expiry, $strike);

        // Calculate cumulative and percentage metrics
        $processedData = $this->processStrikeData($strikeData);

        return response()->json([
            'data' => $processedData,
            'strike' => $strike,
            'expiry' => $expiry,
            'date' => $date
        ]);
    }

    private function getCurrentExpiry()
    {
        return DB::table('nse_expiries')
                 ->where('is_current', 1)
                 ->where('instrument_type', 'OPT')
                 ->where('trading_symbol', 'NIFTY')
                 ->value('expiry_date');
    }

    private function getWorkingDates()
    {
        return DB::table('nse_working_days')
                 ->where('working_date', '>=', now()->subDays(30))
                 ->where('working_date', '<=', now())
                 ->orderBy('working_date', 'desc')
                 ->pluck('working_date')
                 ->toArray();
    }

    private function getCurrentSpot()
    {
        $expiry = $this->getCurrentExpiry();

        $spot = DB::table('option_chains')
                  ->where('expiry', $expiry)
                  ->whereDate('captured_at', now()->toDateString())
                  ->orderBy('captured_at', 'desc')
                  ->value('underlying_spot_price');

        return $spot ?? 23400; // Fallback if no data
    }

    private function getATMStrike($spot)
    {
        if (!$spot) return 23400;

        // Round to nearest 50
        return round($spot / 50) * 50;
    }

    private function getStrikeTimeSeries($date, $expiry, $strike)
    {
        // Get all records ordered by time DESC (recent first)
        $records = DB::table('option_chains')
                     ->select(
                         DB::raw('DATE_FORMAT(captured_at, "%H:%i") as time'),
                         'captured_at',
                         'option_type',
                         'oi',
                         'diff_oi',
                         'volume',
                         'diff_volume',
                         'strike_price'
                     )
                     ->whereDate('captured_at', $date)
                     ->where('expiry', $expiry)
                     ->where('strike_price', $strike)
                     ->orderBy('captured_at', 'desc')
                     ->get();

        // Separate CE and PE
        $ceRecords = $records->where('option_type', 'CE')->keyBy('time');
        $peRecords = $records->where('option_type', 'PE')->keyBy('time');

        // Get all unique times (already sorted desc because of the query)
        $allTimes = array_unique(array_merge($ceRecords->keys()->toArray(), $peRecords->keys()->toArray()));
        rsort($allTimes);

        $result = [];
        foreach ($allTimes as $time) {
            $result[$time] = [
                'ce' => $ceRecords->get($time),
                'pe' => $peRecords->get($time),
                'time' => $time,
                'strike_price' => $strike
            ];
        }

        return $result;
    }

    private function processStrikeData($strikeData)
    {
        $processed = [];
        $cumulativeCE = 0;
        $cumulativePE = 0;
        $startCE = null;  // Store the first CE OI value (09:15)
        $startPE = null;  // Store the first PE OI value (09:15)

        // Get the first (oldest) record to use as baseline
        $firstRecord = array_values($strikeData)[0] ?? null;
        if ($firstRecord) {
            $startCE = $firstRecord['ce']->oi ?? 0;
            $startPE = $firstRecord['pe']->oi ?? 0;
        }

        foreach ($strikeData as $time => $data) {
            $ce = $data['ce'];
            $pe = $data['pe'];

            // Current interval change (5-minute)
            $ceCurrentDiff = $ce ? $ce->diff_oi : 0;
            $peCurrentDiff = $pe ? $pe->diff_oi : 0;

            // Cumulative change from 09:15
            $ceCumulativeDiff = $ce ? ($ce->oi - $startCE) : 0;
            $peCumulativeDiff = $pe ? ($pe->oi - $startPE) : 0;

            // Current interval percentage (based on previous OI)
            $ceCurrentPercent = $ce ? $this->calculatePercentChange($ce->oi, $ce->oi - $ce->diff_oi) : 0;
            $peCurrentPercent = $pe ? $this->calculatePercentChange($pe->oi, $pe->oi - $pe->diff_oi) : 0;

            // Cumulative percentage (based on 09:15 OI)
            $ceCumulativePercent = $startCE > 0 ? (($ce->oi - $startCE) / $startCE) * 100 : 0;
            $peCumulativePercent = $startPE > 0 ? (($pe->oi - $startPE) / $startPE) * 100 : 0;

            $processed[] = [
                'time' => $time,
                'ce_current_diff_oi' => $ceCurrentDiff,
                'ce_cumulative_diff_oi' => $ceCumulativeDiff,
                'ce_current_percent' => round($ceCurrentPercent, 2),
                'ce_cumulative_percent' => round($ceCumulativePercent, 2),
                'strike' => $data['strike_price'],
                'pe_current_diff_oi' => $peCurrentDiff,
                'pe_cumulative_diff_oi' => $peCumulativeDiff,
                'pe_current_percent' => round($peCurrentPercent, 2),
                'pe_cumulative_percent' => round($peCumulativePercent, 2),
                'ce_oi' => $ce ? $ce->oi : 0,
                'pe_oi' => $pe ? $pe->oi : 0,
                'ce_volume' => $ce ? $ce->volume : 0,
                'pe_volume' => $pe ? $pe->volume : 0,
            ];
        }

        return $processed;
    }

    private function calculatePercentChange($current, $previous)
    {
        if ($previous == 0) return 0;
        return round((($current - $previous) / $previous) * 100, 2);
    }
}
