<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StrikeDetailController extends Controller
{
    public function index()
    {
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
        $date = $request->input('date', now()->toDateString());
        $expiry = $request->input('expiry', $this->getCurrentExpiry());
        $atmStrike = $request->input('strike', $this->getATMStrike($this->getCurrentSpot()));

        // Get 5 strikes: ATM - 100, ATM - 50, ATM, ATM + 50, ATM + 100
        $strikes = [
            $atmStrike - 100,
            $atmStrike - 50,
            $atmStrike,
            $atmStrike + 50,
            $atmStrike + 100
        ];

        // Get current spot price
        $currentSpotPrice = DB::table('option_chains')
                              ->whereDate('captured_at', $date)
                              ->where('expiry', $expiry)
                              ->orderBy('captured_at', 'desc')
                              ->value('underlying_spot_price');

        $allStrikesData = [];

        foreach ($strikes as $strike) {
            $strikeData = $this->getStrikeTimeSeries($date, $expiry, $strike);
            $processedData = $this->processStrikeData($strikeData, $currentSpotPrice);
            $allStrikesData[$strike] = $processedData;
        }

        // Group by time across all strikes
        $groupedByTime = $this->groupDataByTime($allStrikesData, $currentSpotPrice);

        return response()->json([
            'data' => $groupedByTime,
            'strikes' => $strikes,
            'atm_strike' => $atmStrike,
            'expiry' => $expiry,
            'date' => $date,
            'current_spot' => $currentSpotPrice
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
        return DB::table('option_chains')
                 ->where('expiry', $expiry)
                 ->whereDate('captured_at', now()->toDateString())
                 ->orderBy('captured_at', 'desc')
                 ->value('underlying_spot_price') ?? 23400;
    }

    private function getATMStrike($spot)
    {
        if (!$spot) return 23400;
        return round($spot / 50) * 50;
    }

    private function getStrikeTimeSeries($date, $expiry, $strike)
    {
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

        $ceRecords = $records->where('option_type', 'CE')->keyBy('time');
        $peRecords = $records->where('option_type', 'PE')->keyBy('time');

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

    private function processStrikeData($strikeData, $currentSpotPrice)
    {
        $processed = [];
        $startCE = null;
        $startPE = null;

        $firstRecord = array_values($strikeData)[0] ?? null;
        if ($firstRecord) {
            $startCE = $firstRecord['ce']->oi ?? 0;
            $startPE = $firstRecord['pe']->oi ?? 0;
        }

        foreach ($strikeData as $time => $data) {
            $ce = $data['ce'];
            $pe = $data['pe'];

            $ceCurrentPercent = $ce ? $this->calculatePercentChange($ce->oi, $ce->oi - $ce->diff_oi) : 0;
            $peCurrentPercent = $pe ? $this->calculatePercentChange($pe->oi, $pe->oi - $pe->diff_oi) : 0;
            $ceCumulativePercent = $startCE > 0 ? (($ce->oi - $startCE) / $startCE) * 100 : 0;
            $peCumulativePercent = $startPE > 0 ? (($pe->oi - $startPE) / $startPE) * 100 : 0;

            $processed[$time] = [
                'strike' => $data['strike_price'],
                'ce_oi' => $ce ? $ce->oi : 0,
                'pe_oi' => $pe ? $pe->oi : 0,
                'ce_current_diff_oi' => $ce ? $ce->diff_oi : 0,
                'pe_current_diff_oi' => $pe ? $pe->diff_oi : 0,
                'ce_cumulative_diff_oi' => $ce ? ($ce->oi - $startCE) : 0,
                'pe_cumulative_diff_oi' => $pe ? ($pe->oi - $startPE) : 0,
                'ce_current_percent' => round($ceCurrentPercent, 2),
                'pe_current_percent' => round($peCurrentPercent, 2),
                'ce_cumulative_percent' => round($ceCumulativePercent, 2),
                'pe_cumulative_percent' => round($peCumulativePercent, 2),
                'ce_volume' => $ce ? $ce->volume : 0,
                'pe_volume' => $pe ? $pe->volume : 0,
            ];
        }

        return $processed;
    }

    private function groupDataByTime($allStrikesData, $currentSpotPrice)
    {
        // Collect all unique times across all strikes
        $allTimes = [];
        foreach ($allStrikesData as $strikeData) {
            $allTimes = array_merge($allTimes, array_keys($strikeData));
        }
        $allTimes = array_unique($allTimes);
        rsort($allTimes);

        $grouped = [];

        foreach ($allTimes as $time) {
            $timeData = [];
            $allCEPercentChanges = [];
            $allPEPercentChanges = [];

            foreach ($allStrikesData as $strike => $strikeData) {
                if (isset($strikeData[$time])) {
                    $row = $strikeData[$time];
                    $timeData[$strike] = $row;
                    $allCEPercentChanges[] = $row['ce_current_percent'];
                    $allPEPercentChanges[] = $row['pe_current_percent'];
                }
            }

            // Sort to find top 5
            rsort($allCEPercentChanges);
            $top5CEPositive = array_slice(array_filter($allCEPercentChanges, function($v) { return $v > 0; }), 0, 5);
            sort($allCEPercentChanges);
            $top5CENegative = array_slice(array_filter($allCEPercentChanges, function($v) { return $v < 0; }), 0, 5);

            rsort($allPEPercentChanges);
            $top5PEPositive = array_slice(array_filter($allPEPercentChanges, function($v) { return $v > 0; }), 0, 5);
            sort($allPEPercentChanges);
            $top5PENegative = array_slice(array_filter($allPEPercentChanges, function($v) { return $v < 0; }), 0, 5);

            // Add flags to each row
            foreach ($timeData as $strike => &$row) {
                $row['is_top5_ce_positive'] = in_array($row['ce_current_percent'], $top5CEPositive);
                $row['is_top5_ce_negative'] = in_array($row['ce_current_percent'], $top5CENegative);
                $row['is_top5_pe_positive'] = in_array($row['pe_current_percent'], $top5PEPositive);
                $row['is_top5_pe_negative'] = in_array($row['pe_current_percent'], $top5PENegative);
            }

            // Calculate consolidated action based on all 5 strikes
            $consolidatedAction = $this->calculateConsolidatedAction($timeData, $currentSpotPrice);

            $grouped[$time] = [
                'strikes' => $timeData,
                'consolidated_action' => $consolidatedAction,
                'total_ce_oi' => array_sum(array_column($timeData, 'ce_oi')),
                'total_pe_oi' => array_sum(array_column($timeData, 'pe_oi')),
            ];
        }

        return $grouped;
    }

    private function calculateConsolidatedAction($timeData, $currentSpotPrice)
    {
        $buyScore = 0;
        $sellScore = 0;

        foreach ($timeData as $strike => $row) {
            $distance = abs($currentSpotPrice - $strike);
            $weight = $distance <= 50 ? 3 : ($distance <= 100 ? 2 : 1);

            $netScore = $row['pe_cumulative_percent'] - $row['ce_cumulative_percent'];

            if ($netScore > 10) {
                $buyScore += $weight * 2;
            } elseif ($netScore > 5) {
                $buyScore += $weight;
            } elseif ($netScore < -10) {
                $sellScore += $weight * 2;
            } elseif ($netScore < -5) {
                $sellScore += $weight;
            }
        }

        if ($buyScore > $sellScore * 1.5) {
            return 'STRONG BUY';
        } elseif ($buyScore > $sellScore) {
            return 'BUY';
        } elseif ($sellScore > $buyScore * 1.5) {
            return 'STRONG SELL';
        } elseif ($sellScore > $buyScore) {
            return 'SELL';
        } else {
            return 'WAIT';
        }
    }

    private function calculatePercentChange($current, $previous)
    {
        if ($previous == 0) return 0;
        return round((($current - $previous) / $previous) * 100, 2);
    }
}
