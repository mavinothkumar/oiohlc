<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CombinedPremiumAnalysisController extends Controller
{
    public function index(Request $request)
    {
        // ----- 1. Default expiry -----
        $defaultExpiry = DB::table('nse_expiries')
                           ->where('trading_symbol', 'NIFTY')
                           ->where('instrument_type', 'OPT')
                           ->where('is_current', 1)
                           ->value('expiry_date') ?? today()->toDateString();

        $selectedExpiry = $request->input('expiry', $defaultExpiry);
        $selectedDate   = $request->input('date', today()->toDateString());
        $putStrikes     = $request->input('put_strikes', []);
        $callStrikes    = $request->input('call_strikes', []);
        $enterPrice     = $request->input('enter_price');
        $chartView      = $request->input('chart_view', 'combined'); // Default to combined

        // ----- 2. All strikes for dropdowns -----
        $allStrikes = DB::table('option_chains')
                        ->where('trading_symbol', 'NIFTY')
                        ->where('expiry', $selectedExpiry)
                        ->distinct()
                        ->orderBy('strike_price')
                        ->pluck('strike_price');

        // ----- 3. Fetch and aggregate data -----
        $data = collect();
        $labels = [];
        $totalPutLtp = [];
        $totalCallLtp = [];
        $combinedLtp = [];
        $putVega = [];
        $callVega = [];
        $netVega = [];
        $putTheta = [];
        $callTheta = [];
        $netTheta = [];
        $putGamma = [];
        $callGamma = [];
        $netGamma = [];
        $putDelta = [];
        $callDelta = [];
        $netDelta = [];
        $putIv = [];
        $callIv = [];
        $putPop = [];
        $callPop = [];
        $vwap = [];
        $oiVwap = [];
        $netOIChange = [];
        $putBuildUp = [];
        $callBuildUp = [];

        if (!empty($putStrikes) && !empty($callStrikes)) {
            // Fetch all PE data
            $peData = DB::table('option_chains')
                        ->whereIn('strike_price', $putStrikes)
                        ->where('option_type', 'PE')
                        ->where('expiry', $selectedExpiry)
                        ->whereDate('captured_at', $selectedDate)
                        ->orderBy('captured_at')
                        ->get();

            // Fetch all CE data
            $ceData = DB::table('option_chains')
                        ->whereIn('strike_price', $callStrikes)
                        ->where('option_type', 'CE')
                        ->where('expiry', $selectedExpiry)
                        ->whereDate('captured_at', $selectedDate)
                        ->orderBy('captured_at')
                        ->get();

            // Group and aggregate by timestamp
            $groupedData = [];

            // Aggregate PE data
            foreach ($peData as $row) {
                $key = $row->captured_at;
                if (!isset($groupedData[$key])) {
                    $groupedData[$key] = [
                        'captured_at' => $row->captured_at,
                        'total_put_ltp' => 0, 'total_put_volume' => 0, 'total_put_oi' => 0,
                        'total_put_prev_oi' => 0, 'total_put_diff_oi' => 0, 'total_put_vega' => 0,
                        'total_put_theta' => 0, 'total_put_gamma' => 0, 'total_put_delta' => 0,
                        'total_put_iv' => 0, 'total_put_pop' => 0, 'put_count' => 0,
                        'total_call_ltp' => 0, 'total_call_volume' => 0, 'total_call_oi' => 0,
                        'total_call_prev_oi' => 0, 'total_call_diff_oi' => 0, 'total_call_vega' => 0,
                        'total_call_theta' => 0, 'total_call_gamma' => 0, 'total_call_delta' => 0,
                        'total_call_iv' => 0, 'total_call_pop' => 0, 'call_count' => 0,
                        'put_build_up' => [], 'call_build_up' => []
                    ];
                }

                $groupedData[$key]['total_put_ltp'] += $row->ltp;
                $groupedData[$key]['total_put_volume'] += $row->volume;
                $groupedData[$key]['total_put_oi'] += $row->oi;
                $groupedData[$key]['total_put_prev_oi'] += $row->prev_oi;
                $groupedData[$key]['total_put_diff_oi'] += $row->diff_oi;
                $groupedData[$key]['total_put_vega'] += $row->vega;
                $groupedData[$key]['total_put_theta'] += $row->theta;
                $groupedData[$key]['total_put_gamma'] += $row->gamma;
                $groupedData[$key]['total_put_delta'] += $row->delta;
                $groupedData[$key]['total_put_iv'] += $row->iv;
                $groupedData[$key]['total_put_pop'] += $row->pop;
                $groupedData[$key]['put_count']++;
                if ($row->build_up) {
                    $groupedData[$key]['put_build_up'][] = $row->build_up;
                }
            }

            // Aggregate CE data
            foreach ($ceData as $row) {
                $key = $row->captured_at;
                if (!isset($groupedData[$key])) {
                    $groupedData[$key] = [
                        'captured_at' => $row->captured_at,
                        'total_put_ltp' => 0, 'total_put_volume' => 0, 'total_put_oi' => 0,
                        'total_put_prev_oi' => 0, 'total_put_diff_oi' => 0, 'total_put_vega' => 0,
                        'total_put_theta' => 0, 'total_put_gamma' => 0, 'total_put_delta' => 0,
                        'total_put_iv' => 0, 'total_put_pop' => 0, 'put_count' => 0,
                        'total_call_ltp' => 0, 'total_call_volume' => 0, 'total_call_oi' => 0,
                        'total_call_prev_oi' => 0, 'total_call_diff_oi' => 0, 'total_call_vega' => 0,
                        'total_call_theta' => 0, 'total_call_gamma' => 0, 'total_call_delta' => 0,
                        'total_call_iv' => 0, 'total_call_pop' => 0, 'call_count' => 0,
                        'put_build_up' => [], 'call_build_up' => []
                    ];
                }

                $groupedData[$key]['total_call_ltp'] += $row->ltp;
                $groupedData[$key]['total_call_volume'] += $row->volume;
                $groupedData[$key]['total_call_oi'] += $row->oi;
                $groupedData[$key]['total_call_prev_oi'] += $row->prev_oi;
                $groupedData[$key]['total_call_diff_oi'] += $row->diff_oi;
                $groupedData[$key]['total_call_vega'] += $row->vega;
                $groupedData[$key]['total_call_theta'] += $row->theta;
                $groupedData[$key]['total_call_gamma'] += $row->gamma;
                $groupedData[$key]['total_call_delta'] += $row->delta;
                $groupedData[$key]['total_call_iv'] += $row->iv;
                $groupedData[$key]['total_call_pop'] += $row->pop;
                $groupedData[$key]['call_count']++;
                if ($row->build_up) {
                    $groupedData[$key]['call_build_up'][] = $row->build_up;
                }
            }

            // Sort by timestamp
            ksort($groupedData);
            $data = collect(array_values($groupedData));

            // Generate labels and aggregated data
            $labels = $data->pluck('captured_at')->map(fn($d) => Carbon::parse($d)->format('H:i'));

            // Calculate averages and totals
            $totalPutLtp = $data->pluck('total_put_ltp');
            $totalCallLtp = $data->pluck('total_call_ltp');
            $combinedLtp = $data->map(fn($r) => round($r['total_put_ltp'] + $r['total_call_ltp'], 2));

            // Greeks (averaged per strike)
            $putCount = $data->pluck('put_count')->map(fn($c) => max($c, 1));
            $callCount = $data->pluck('call_count')->map(fn($c) => max($c, 1));

            $putVega = $data->pluck('total_put_vega')->map(fn($v, $i) => round($v / $putCount[$i], 4));
            $callVega = $data->pluck('total_call_vega')->map(fn($v, $i) => round($v / $callCount[$i], 4));
            $netVega = $putVega->zip($callVega)->map(fn($pair) => round(-($pair[0] + $pair[1]), 4));

            $putTheta = $data->pluck('total_put_theta')->map(fn($v, $i) => round($v / $putCount[$i], 4));
            $callTheta = $data->pluck('total_call_theta')->map(fn($v, $i) => round($v / $callCount[$i], 4));
            $netTheta = $putTheta->zip($callTheta)->map(fn($pair) => round(-($pair[0] + $pair[1]), 4));

            $putGamma = $data->pluck('total_put_gamma')->map(fn($v, $i) => round($v / $putCount[$i], 4));
            $callGamma = $data->pluck('total_call_gamma')->map(fn($v, $i) => round($v / $callCount[$i], 4));
            $netGamma = $putGamma->zip($callGamma)->map(fn($pair) => round(-($pair[0] + $pair[1]), 4));

            $putDelta = $data->pluck('total_put_delta')->map(fn($v, $i) => round($v / $putCount[$i], 4));
            $callDelta = $data->pluck('total_call_delta')->map(fn($v, $i) => round($v / $callCount[$i], 4));
            $netDelta = $putDelta->zip($callDelta)->map(fn($pair) => round(-($pair[0] + $pair[1]), 4));

            $putIv = $data->pluck('total_put_iv')->map(fn($v, $i) => round($v / $putCount[$i], 2));
            $callIv = $data->pluck('total_call_iv')->map(fn($v, $i) => round($v / $callCount[$i], 2));
            $putPop = $data->pluck('total_put_pop')->map(fn($v, $i) => round($v / $putCount[$i], 2));
            $callPop = $data->pluck('total_call_pop')->map(fn($v, $i) => round($v / $callCount[$i], 2));

            // Build-up strings (combine all)
            $putBuildUp = $data->map(fn($r) => implode(', ', array_unique($r['put_build_up'])));
            $callBuildUp = $data->map(fn($r) => implode(', ', array_unique($r['call_build_up'])));

            // ----- VWAP (volume weighted) -----
            $cumulativePV = 0;
            $cumulativeVol = 0;
            foreach ($data as $row) {
                $combinedPrice = $row['total_put_ltp'] + $row['total_call_ltp'];
                $combinedVol = $row['total_put_volume'] + $row['total_call_volume'];
                $cumulativePV += $combinedPrice * $combinedVol;
                $cumulativeVol += $combinedVol;
                $vwap[] = $cumulativeVol > 0 ? round($cumulativePV / $cumulativeVol, 2) : (count($vwap) ? end($vwap) : 0);
            }

            // ----- OI-VWAP (weighted by new positions) -----
            $cumulativeOIPV = 0;
            $cumulativeOIWeight = 0;
            foreach ($data as $row) {
                $combinedPrice = $row['total_put_ltp'] + $row['total_call_ltp'];
                $weight = max($row['total_put_diff_oi'], 0) + max($row['total_call_diff_oi'], 0);
                if ($weight > 0) {
                    $cumulativeOIPV += $combinedPrice * $weight;
                    $cumulativeOIWeight += $weight;
                    $oiVwap[] = round($cumulativeOIPV / $cumulativeOIWeight, 2);
                } else {
                    $oiVwap[] = count($oiVwap) ? end($oiVwap) : round($combinedPrice, 2);
                }
            }

            // ----- Cumulative Net OI Change -----
            $runningOI = 0;
            foreach ($data as $row) {
                $runningOI += ($row['total_put_diff_oi'] + $row['total_call_diff_oi']);
                $netOIChange[] = $runningOI;
            }
        }

        return view('combine-premium-analysis', compact(
            'selectedExpiry', 'selectedDate', 'putStrikes', 'callStrikes', 'enterPrice', 'chartView',
            'allStrikes',
            'labels',
            'totalPutLtp', 'totalCallLtp', 'combinedLtp',
            'putVega', 'callVega', 'netVega',
            'putTheta', 'callTheta', 'netTheta',
            'putGamma', 'callGamma', 'netGamma',
            'putDelta', 'callDelta', 'netDelta',
            'putIv', 'callIv', 'putPop', 'callPop',
            'vwap', 'oiVwap', 'netOIChange',
            'putBuildUp', 'callBuildUp',
            'data'
        ));
    }
}
