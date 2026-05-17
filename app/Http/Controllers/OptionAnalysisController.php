<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OptionAnalysisController extends Controller
{
    public function index()
    {
        // Get default dates
        $currentDate = now()->toDateString();
        $currentExpiry = $this->getCurrentExpiry();
        $workingDates = $this->getWorkingDates();

        return view('option-data.index', compact('currentDate', 'currentExpiry', 'workingDates'));
    }

    public function getOptionData(Request $request)
    {
        // Validate and get filters
        $date = $request->input('date', now()->toDateString());
        $expiry = $request->input('expiry', $this->getCurrentExpiry());
        $fromTime = $request->input('from_time', '09:15:00');
        $toTime = $request->input('to_time', '15:30:00');

        // Convert to timestamps for query
        $fromDateTime = Carbon::parse($date . ' ' . $fromTime);
        $toDateTime = Carbon::parse($date . ' ' . $toTime);

        // Get current spot price (from the start of the selected range)
        $startPrice = $this->getPriceAtTime($date, $expiry, $fromDateTime);
        $endPrice = $this->getPriceAtTime($date, $expiry, $toDateTime);

        // Get all strikes between 5% above and below the current spot
        $activeStrikes = $this->getActiveStrikes($date, $expiry, $fromDateTime, $toDateTime);

        // Get OI and Volume data grouped by strike
        $optionData = $this->getOptionDataGrouped($expiry, $fromDateTime, $toDateTime);

        // Calculate summary statistics with all 4 parameters
        $summary = $this->calculateSummary($optionData, $activeStrikes, $startPrice, $endPrice);

        return response()->json([
            'summary' => $summary,
            'chart_data' => $this->prepareChartData($optionData),
            'raw_data' => $optionData
        ]);
    }

    private function getPriceAtTime($date, $expiry, $timestamp)
    {
        // Get the closest record to the requested timestamp
        $record = DB::table('option_chains')
                    ->whereDate('captured_at', $date)
                    ->where('expiry', $expiry)
                    ->where('captured_at', '<=', $timestamp)
                    ->orderBy('captured_at', 'desc')
                    ->first();

        // If no record found before the timestamp, get the first record after
        if (!$record) {
            $record = DB::table('option_chains')
                        ->whereDate('captured_at', $date)
                        ->where('expiry', $expiry)
                        ->where('captured_at', '>=', $timestamp)
                        ->orderBy('captured_at', 'asc')
                        ->first();
        }

        return $record ? $record->underlying_spot_price : null;
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

    private function getActiveStrikes($date, $expiry, $fromDateTime, $toDateTime)
    {
        // Get current spot price from the start of the selected range
        $spot = $this->getPriceAtTime($date, $expiry, $fromDateTime);

        if (!$spot) return [];

        $range = $spot * 0.05; // 5% range
        $lower = $spot - $range;
        $upper = $spot + $range;

        // Get all strikes in the active zone
        return DB::table('option_chains')
                 ->select('strike_price')
                 ->where('expiry', $expiry)
                 ->whereBetween('strike_price', [$lower, $upper])
                 ->distinct()
                 ->orderBy('strike_price', 'asc')
                 ->pluck('strike_price')
                 ->toArray();
    }

    private function getOptionDataGrouped($expiry, $fromDateTime, $toDateTime)
    {
        // Get the start and end snapshots for OI difference calculation
        $startTime = $fromDateTime->copy();
        $endTime = $toDateTime->copy();

        // Get records at start time
        $startRecords = DB::table('option_chains')
                          ->where('expiry', $expiry)
                          ->whereBetween('captured_at', [$startTime, $startTime->copy()->addMinutes(5)])
                          ->get()
                          ->keyBy(function ($item) {
                              return $item->strike_price . '_' . $item->option_type;
                          });

        // Get records at end time
        $endRecords = DB::table('option_chains')
                        ->where('expiry', $expiry)
                        ->whereBetween('captured_at', [$endTime->copy()->subMinutes(5), $endTime])
                        ->get()
                        ->keyBy(function ($item) {
                            return $item->strike_price . '_' . $item->option_type;
                        });

        // Calculate differences and prepare grouped data
        $grouped = [];

        foreach ($endRecords as $key => $record) {
            $startRecord = $startRecords->get($key);

            // Calculate OI change percentage
            $oiChangePercent = 0;
            if ($startRecord && $startRecord->oi > 0) {
                $oiChangePercent = (($record->oi - $startRecord->oi) / $startRecord->oi) * 100;
            }

            $grouped[$record->strike_price][$record->option_type] = [
                'oi' => $record->oi,
                'oi_change' => $startRecord ? ($record->oi - $startRecord->oi) : 0,
                'oi_change_percent' => round($oiChangePercent, 2),
                'volume' => $record->volume,
                'volume_change' => $startRecord ? ($record->volume - $startRecord->volume) : 0,
                'ltp' => $record->ltp,
                'build_up' => $record->build_up,
                'pcr' => $record->pcr,
                'captured_at' => $record->captured_at,
            ];
        }

        return $grouped;
    }

    private function calculateSummary($optionData, $activeStrikes, $startPrice, $endPrice)
    {
        $summary = [
            'sentiment' => 'NEUTRAL',
            'support_strike' => null,
            'resistance_strike' => null,
            'max_put_oi_change_strike' => null,
            'max_put_oi_change_value' => 0,
            'max_call_oi_change_strike' => null,
            'max_call_oi_change_value' => 0,
            'pcr_trend' => 'NEUTRAL',
            'volume_spike_strike' => null,
            'signal' => 'WAIT',
            'confidence_score' => 0,
            'price_direction' => 'NEUTRAL',
            'price_change' => 0,
            'details' => []
        ];

        if (empty($optionData) || !$startPrice || !$endPrice) {
            return $summary;
        }

        // Calculate price direction
        $priceChange = $endPrice - $startPrice;
        $priceChangePercent = ($startPrice > 0) ? ($priceChange / $startPrice) * 100 : 0;
        $priceDirection = $priceChange > 0 ? 'UP' : ($priceChange < 0 ? 'DOWN' : 'NEUTRAL');

        $summary['price_direction'] = $priceDirection;
        $summary['price_change'] = round($priceChange, 2);
        $summary['price_change_percent'] = round($priceChangePercent, 2);

        // Find max OI changes
        $maxPutOIChange = 0;
        $maxCallOIChange = 0;
        $pcrValues = [];
        $signalScore = 0;

        foreach ($optionData as $strike => $data) {
            if (!in_array($strike, $activeStrikes)) continue;

            // Put side analysis
            if (isset($data['PE'])) {
                $putOIChange = abs($data['PE']['oi_change']);
                $putOIChangePercent = $data['PE']['oi_change_percent'] ?? 0;

                if ($putOIChange > $maxPutOIChange) {
                    $maxPutOIChange = $putOIChange;
                    $summary['max_put_oi_change_strike'] = $strike;
                    $summary['max_put_oi_change_value'] = $putOIChange;

                    // Find support (highest Put build-up)
                    if ($putOIChange > 500000) { // 5L threshold
                        $summary['support_strike'] = $strike;
                    }
                }
                if (isset($data['PE']['pcr'])) {
                    $pcrValues[] = $data['PE']['pcr'];
                }
            }

            // Call side analysis
            if (isset($data['CE'])) {
                $callOIChange = abs($data['CE']['oi_change']);
                $callOIChangePercent = $data['CE']['oi_change_percent'] ?? 0;

                if ($callOIChange > $maxCallOIChange) {
                    $maxCallOIChange = $callOIChange;
                    $summary['max_call_oi_change_strike'] = $strike;
                    $summary['max_call_oi_change_value'] = $callOIChange;

                    // Find resistance (highest Call build-up)
                    if ($callOIChange > 500000) { // 5L threshold
                        $summary['resistance_strike'] = $strike;
                    }
                }
            }
        }

        // 🔥 **CORRECTED LOGIC WITH PRICE ACTION**
        $oiThreshold = 500000; // 5 Lakh

        if ($priceDirection == 'UP') {
            // Market is rising
            if ($summary['max_put_oi_change_strike'] && $summary['max_put_oi_change_value'] > $oiThreshold) {
                // Put writers are confident → Bullish
                $summary['sentiment'] = 'BULLISH';
                $summary['signal'] = 'BUY';
                $summary['confidence_score'] = 80;
                $signalScore += 30;
            }
            if ($summary['max_call_oi_change_strike'] && $summary['max_call_oi_change_value'] > $oiThreshold) {
                // Call writers are trapped → Bullish Breakout
                $summary['sentiment'] = 'BULLISH BREAKOUT';
                $summary['signal'] = 'STRONG BUY';
                $summary['confidence_score'] = 90;
                $signalScore += 40;
            }
            // If both Put and Call are building, market is consolidating
            if ($signalScore < 30) {
                $summary['sentiment'] = 'BULLISH CONSOLIDATION';
                $summary['signal'] = 'BUY ON DIPS';
                $summary['confidence_score'] = 60;
            }
        }
        elseif ($priceDirection == 'DOWN') {
            // Market is falling
            if ($summary['max_call_oi_change_strike'] && $summary['max_call_oi_change_value'] > $oiThreshold) {
                // Call writers are confident → Bearish
                $summary['sentiment'] = 'BEARISH';
                $summary['signal'] = 'SELL';
                $summary['confidence_score'] = 80;
                $signalScore += 30;
            }
            if ($summary['max_put_oi_change_strike'] && $summary['max_put_oi_change_value'] > $oiThreshold) {
                // Put writers are trapped → Bearish Breakdown
                $summary['sentiment'] = 'BEARISH BREAKDOWN';
                $summary['signal'] = 'STRONG SELL';
                $summary['confidence_score'] = 90;
                $signalScore += 40;
            }
            if ($signalScore < 30) {
                $summary['sentiment'] = 'BEARISH CONSOLIDATION';
                $summary['signal'] = 'SELL ON RALLIES';
                $summary['confidence_score'] = 60;
            }
        }
        else {
            // Market is flat
            if ($summary['max_put_oi_change_value'] > $oiThreshold) {
                $summary['sentiment'] = 'BULLISH SUPPORT';
                $summary['signal'] = 'BUY ON DIPS';
                $summary['confidence_score'] = 70;
            } elseif ($summary['max_call_oi_change_value'] > $oiThreshold) {
                $summary['sentiment'] = 'BEARISH RESISTANCE';
                $summary['signal'] = 'SELL ON RALLIES';
                $summary['confidence_score'] = 70;
            } else {
                $summary['sentiment'] = 'NEUTRAL';
                $summary['signal'] = 'WAIT';
                $summary['confidence_score'] = 30;
            }
        }

        // Determine PCR trend
        if (count($pcrValues) >= 2) {
            $first = $pcrValues[0];
            $last = $pcrValues[count($pcrValues) - 1];
            $summary['pcr_trend'] = $last > $first ? 'UP' : ($last < $first ? 'DOWN' : 'NEUTRAL');
        }

        // Add details
        $summary['details'] = [
            'total_active_strikes' => count($activeStrikes),
            'pcr_range' => count($pcrValues) > 0 ? [min($pcrValues), max($pcrValues)] : null,
            'start_price' => round($startPrice, 2),
            'end_price' => round($endPrice, 2),
            'price_change_percent' => round($priceChangePercent, 2)
        ];

        return $summary;
    }

    private function prepareChartData($optionData)
    {
        $chartData = [];

        foreach ($optionData as $strike => $data) {
            $row = [
                'strike' => $strike,
                'put_oi' => $data['PE']['oi'] ?? 0,
                'call_oi' => $data['CE']['oi'] ?? 0,
                'put_oi_change' => $data['PE']['oi_change'] ?? 0,
                'call_oi_change' => $data['CE']['oi_change'] ?? 0,
                'put_build_up' => $data['PE']['build_up'] ?? null,
                'call_build_up' => $data['CE']['build_up'] ?? null,
            ];
            $chartData[] = $row;
        }

        return $chartData;
    }
}
