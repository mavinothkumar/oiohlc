<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StrikeDetailController extends Controller {
    public function index() {
        $currentDate   = request()->get( 'date', now()->toDateString() );
        $currentExpiry = $this->getCurrentExpiry();
        $currentSpot   = $this->getCurrentSpot();
        $atmStrike     = request()->get( 'strike', $this->getATMStrike( $currentSpot ) );
        $workingDates  = $this->getWorkingDates();

        return view( 'strike-detail', compact(
            'currentDate',
            'currentExpiry',
            'currentSpot',
            'atmStrike',
            'workingDates'
        ) );
    }

    public function getStrikeData( Request $request ) {
        $date      = $request->input( 'date', now()->toDateString() );
        $expiry    = $request->input( 'expiry', $this->getCurrentExpiry() );
        $atmStrike = $request->input( 'strike', $this->getATMStrike( $this->getCurrentSpot() ) );

        // Get 5 strikes: ATM - 100, ATM - 50, ATM, ATM + 50, ATM + 100
        $strikes = [
            $atmStrike - 100,
            $atmStrike - 50,
            $atmStrike,
            $atmStrike + 50,
            $atmStrike + 100,
        ];

        // Get current spot price
        $currentSpotPrice = DB::table( 'option_chains' )
                              ->whereDate( 'captured_at', $date )
                              ->where( 'expiry', $expiry )
                              ->orderBy( 'captured_at', 'desc' )
                              ->value( 'underlying_spot_price' );

        $allStrikesData = [];

        foreach ( $strikes as $strike ) {
            $strikeData                = $this->getStrikeTimeSeries( $date, $expiry, $strike );
            $processedData             = $this->processStrikeData( $strikeData, $currentSpotPrice );
            $allStrikesData[ $strike ] = $processedData;
        }

        // Group by time across all strikes
        $groupedByTime = $this->groupDataByTime( $allStrikesData, $currentSpotPrice );

        return response()->json( [
            'data'         => $groupedByTime,
            'strikes'      => $strikes,
            'atm_strike'   => $atmStrike,
            'expiry'       => $expiry,
            'date'         => $date,
            'current_spot' => $currentSpotPrice,
        ] );
    }

    private function getCurrentExpiry() {
        return DB::table( 'nse_expiries' )
                 ->where( 'is_current', 1 )
                 ->where( 'instrument_type', 'OPT' )
                 ->where( 'trading_symbol', 'NIFTY' )
                 ->value( 'expiry_date' );
    }

    private function getWorkingDates() {
        return DB::table( 'nse_working_days' )
                 ->where( 'working_date', '>=', now()->subDays( 30 ) )
                 ->where( 'working_date', '<=', now() )
                 ->orderBy( 'working_date', 'desc' )
                 ->pluck( 'working_date' )
                 ->toArray();
    }

    private function getCurrentSpot() {
        $expiry = $this->getCurrentExpiry();

        return DB::table( 'option_chains' )
                 ->where( 'expiry', $expiry )
                 ->whereDate( 'captured_at', now()->toDateString() )
                 ->orderBy( 'captured_at', 'desc' )
                 ->value( 'underlying_spot_price' ) ?? 23400;
    }

    private function getATMStrike( $spot ) {
        if ( ! $spot ) {
            return 23400;
        }

        return round( $spot / 50 ) * 50;
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
                         'strike_price',
                         'build_up',
                         'underlying_spot_price'
                     )
                     ->whereDate('captured_at', $date)
                     ->where('expiry', $expiry)
                     ->where('strike_price', $strike)
                     ->orderBy('captured_at', 'asc')
                     ->get();

        // Separate CE and PE
        $ceRecords = $records->where('option_type', 'CE');
        $peRecords = $records->where('option_type', 'PE');

        // Group by time
        $ceGrouped = $ceRecords->groupBy('time')->map(function ($group) {
            return $group->last();
        });

        $peGrouped = $peRecords->groupBy('time')->map(function ($group) {
            return $group->last();
        });

        // Merge
        $allTimes = array_unique(array_merge($ceGrouped->keys()->toArray(), $peGrouped->keys()->toArray()));
        sort($allTimes);

        $result = [];
        foreach ($allTimes as $time) {
            $result[$time] = [
                'ce' => $ceGrouped->get($time),
                'pe' => $peGrouped->get($time),
                'time' => $time,
                'strike_price' => $strike
            ];
        }

        return $result;
    }

    private function processStrikeData($strikeData, $currentSpotPrice)
    {
        $processed = [];

        // Track running sum of diff_oi
        $runningSumCE = 0;
        $runningSumPE = 0;

        // Track previous running sum for percentage calculation
        $prevRunningSumCE = 0;
        $prevRunningSumPE = 0;

        // Sort times in ascending order to process chronologically
        $times = array_keys($strikeData);
        sort($times);

        foreach ($times as $time) {
            // Skip 09:15
            if ($time <= '09:15') continue;

            $data = $strikeData[$time];
            $ce = $data['ce'];
            $pe = $data['pe'];

            if (!$ce || !$pe) continue;

            // Store previous running sum before updating
            $prevRunningSumCE = $runningSumCE;
            $prevRunningSumPE = $runningSumPE;

            // Add current diff_oi to running sum
            $runningSumCE += $ce->diff_oi ?? 0;
            $runningSumPE += $pe->diff_oi ?? 0;

            $ceBuildUp = $ce->build_up ?? null;
            $peBuildUp = $pe->build_up ?? null;

            $ceCurrentDiff = $ce->diff_oi ?? 0;
            $peCurrentDiff = $pe->diff_oi ?? 0;

            $ceCurrentPercent = $this->calculatePercentChange($ce->oi, $ce->oi - $ceCurrentDiff);
            $peCurrentPercent = $this->calculatePercentChange($pe->oi, $pe->oi - $peCurrentDiff);

            // ===== YOUR LOGIC: Percentage change between consecutive running sums =====
            if ($time === '09:20') {
                // For 09:20, cumulative percent is 0 (baseline)
                $ceCumulativePercent = 0;
                $peCumulativePercent = 0;
            } else {
                // Calculate percentage change from previous running sum
                $ceCumulativePercent = $prevRunningSumCE > 0 ?
                    (($runningSumCE - $prevRunningSumCE) / $prevRunningSumCE) * 100 : 0;
                $peCumulativePercent = $prevRunningSumPE > 0 ?
                    (($runningSumPE - $prevRunningSumPE) / $prevRunningSumPE) * 100 : 0;
            }

            $processed[$time] = [
                'strike' => $data['strike_price'],
                'ce_oi' => $ce->oi,
                'pe_oi' => $pe->oi,
                'ce_current_diff_oi' => $ceCurrentDiff,
                'pe_current_diff_oi' => $peCurrentDiff,
                'ce_cumulative_diff_oi' => $runningSumCE,
                'pe_cumulative_diff_oi' => $runningSumPE,
                'ce_current_percent' => round($ceCurrentPercent, 2),
                'pe_current_percent' => round($peCurrentPercent, 2),
                'ce_cumulative_percent' => round($ceCumulativePercent, 2),
                'pe_cumulative_percent' => round($peCumulativePercent, 2),
                'ce_volume' => $ce->volume ?? 0,
                'pe_volume' => $pe->volume ?? 0,
                'ce_build_up' => $ceBuildUp,
                'pe_build_up' => $peBuildUp,
            ];
        }

        return $processed;
    }

    private function groupDataByTime($allStrikesData, $currentSpotPrice)
    {
        $allTimes = [];
        foreach ($allStrikesData as $strikeData) {
            $allTimes = array_merge($allTimes, array_keys($strikeData));
        }
        $allTimes = array_unique($allTimes);
        rsort($allTimes);

        $grouped = [];
        $previousPrice = null;

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

            // Get current price for this time
            $currentPrice = $currentSpotPrice;
            if (isset($timeData[$strike]) && isset($timeData[$strike]['price'])) {
                $currentPrice = $timeData[$strike]['price'];
            }

            // Calculate consolidated action
            $consolidatedAction = $this->calculateConsolidatedActionWithBuildUp($timeData, $currentPrice);

            $grouped[$time] = [
                'strikes' => $timeData,
                'consolidated_action' => $consolidatedAction,
                'total_ce_oi' => array_sum(array_column($timeData, 'ce_oi')),
                'total_pe_oi' => array_sum(array_column($timeData, 'pe_oi')),
                'price' => $currentPrice,
            ];

            $previousPrice = $currentPrice;
        }

        return $grouped;
    }

    private function calculateConsolidatedActionWithBuildUp($timeData, $currentSpotPrice)
    {
        $buyScore = 0;
        $sellScore = 0;
        $buyBuildUpCount = 0;
        $sellBuildUpCount = 0;

        foreach ($timeData as $strike => $row) {
            $distance = abs($currentSpotPrice - $strike);
            $weight = $distance <= 50 ? 3 : ($distance <= 100 ? 2 : 1);

            $ceBuildUp = $row['ce_build_up'];
            $peBuildUp = $row['pe_build_up'];
            $isITMForCall = $currentSpotPrice > $strike;
            $isITMForPut = $currentSpotPrice < $strike;

            // ===== BULLISH SIGNALS =====
            if ($ceBuildUp === 'Long Build' && $isITMForCall) {
                $buyScore += $weight * 3;
                $buyBuildUpCount++;
            }
            if ($peBuildUp === 'Short Build' && $isITMForCall) {
                $buyScore += $weight * 2;
                $buyBuildUpCount++;
            }
            if ($ceBuildUp === 'Short Cover' && $isITMForCall) {
                $buyScore += $weight;
                $buyBuildUpCount++;
            }

            // ===== BEARISH SIGNALS =====
            if ($peBuildUp === 'Long Build' && $isITMForPut) {
                $sellScore += $weight * 3;
                $sellBuildUpCount++;
            }
            if ($ceBuildUp === 'Short Build' && $isITMForPut) {
                $sellScore += $weight * 2;
                $sellBuildUpCount++;
            }
            if ($peBuildUp === 'Short Cover' && $isITMForPut) {
                $sellScore += $weight;
                $sellBuildUpCount++;
            }
        }

        // Determine final action
        if ($buyScore > $sellScore * 1.5 && $buyBuildUpCount >= 2) {
            return 'STRONG BUY';
        } elseif ($buyScore > $sellScore) {
            return 'BUY';
        } elseif ($sellScore > $buyScore * 1.5 && $sellBuildUpCount >= 2) {
            return 'STRONG SELL';
        } elseif ($sellScore > $buyScore) {
            return 'SELL';
        } else {
            return 'WAIT';
        }
    }

    private function calculatePercentChange( $current, $previous ) {
        if ( $previous == 0 ) {
            return 0;
        }

        return round( ( ( $current - $previous ) / $previous ) * 100, 2 );
    }
}
