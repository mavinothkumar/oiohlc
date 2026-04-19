<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OptionsChartController extends Controller
{
    /**
     * Main page — load NIFTY OPT expiry list.
     */
    public function index()
    {
        $expiries = DB::table('expired_expiries')
                      ->where('instrument_type', 'OPT')
                      ->where('underlying_symbol', 'NIFTY')
                      ->orderBy('expiry_date', 'desc')
                      ->pluck('expiry_date');

        return view('options.chart', compact('expiries'));
    }

    /**
     * AJAX: Resolve from/to dates and compute strike range.
     * GET /options-chart/expiry-range?expiry_date=YYYY-MM-DD
     */
    public function getExpiryRange(Request $request)
    {
        $request->validate(['expiry_date' => 'required|date']);

        $selectedExpiry = $request->input('expiry_date');

        // Previous expiry = from_date
        $prevExpiry = DB::table('expired_expiries')
                        ->where('instrument_type', 'OPT')
                        ->where('underlying_symbol', 'NIFTY')
                        ->where('expiry_date', '<', $selectedExpiry)
                        ->orderBy('expiry_date', 'desc')
                        ->value('expiry_date');

        if (! $prevExpiry) {
            return response()->json([
                'success' => false,
                'message' => 'No previous expiry found for the selected expiry date.',
            ], 422);
        }

        // Get NIFTY INDEX daily min/max over [prev_expiry, selected_expiry]
        $indexRange = DB::table('expired_ohlc')
                        ->where('underlying_symbol', 'NIFTY')
                        ->where('instrument_type', 'INDEX')
                        ->where('interval', 'day')
                        ->whereDate('timestamp', '>=', $prevExpiry)
                        ->whereDate('timestamp', '<=', $selectedExpiry)
                        ->selectRaw('MIN(low) as min_low, MAX(high) as max_high')
                        ->first();

        if (! $indexRange || is_null($indexRange->min_low)) {
            return response()->json([
                'success' => false,
                'message' => 'No NIFTY INDEX day data found for the computed date range.',
            ], 422);
        }

        $minLow  = (float) $indexRange->min_low;
        $maxHigh = (float) $indexRange->max_high;

        // NIFTY strike interval = 50. Apply -5 and +5 strikes
        $si           = 50;
        $lowerStrike  = (floor($minLow  / $si) - 5) * $si;
        $upperStrike  = (ceil($maxHigh  / $si) + 5) * $si;

        $strikes = [];
        for ($s = $lowerStrike; $s <= $upperStrike; $s += $si) {
            $strikes[] = (int) $s;
        }

        return response()->json([
            'success'      => true,
            'from_date'    => $prevExpiry,
            'to_date'      => $selectedExpiry,
            'min_low'      => $minLow,
            'max_high'     => $maxHigh,
            'lower_strike' => (int) $lowerStrike,
            'upper_strike' => (int) $upperStrike,
            'strikes'      => $strikes,
        ]);
    }

    /**
     * AJAX: Fetch CE & PE OHLC candle data for all strikes.
     * GET /options-chart/chart-data
     *     ?expiry_date=YYYY-MM-DD&from_date=YYYY-MM-DD&to_date=YYYY-MM-DD
     *     &strikes[]=22000&strikes[]=22050&interval=5minute
     */
    public function getChartData(Request $request)
    {
        $request->validate([
            'expiry_date' => 'required|date',
            'from_date'   => 'required|date',
            'to_date'     => 'required|date',
            'strikes'     => 'required|array|min:1',
            'strikes.*'   => 'integer',
            'interval'    => 'nullable|string',
        ]);

        $expiryDate = $request->input('expiry_date');
        $fromDate   = $request->input('from_date');
        $toDate     = $request->input('to_date');
        $strikes    = $request->input('strikes');
        $interval   = $request->input('interval', 'day');

        // Fetch CE + PE rows — use actual DB column names (no underscores in schema)
        $rows = DB::table('expired_ohlc')
                  ->where('underlying_symbol', 'NIFTY')
                  ->where('expiry', $expiryDate)
                  ->whereIn('instrument_type', ['CE', 'PE'])
                  ->whereIn('strike', $strikes)
                  ->where('interval', $interval)
                  ->whereDate('timestamp', '>=', $fromDate)
                  ->whereDate('timestamp', '<=', $toDate)
                  ->select(
                      'strike',
                      'instrument_type',
                      'open',
                      'high',
                      'low',
                      'close',
                      'volume',
                      'open_interest',
                      'build_up',
                      'diff_oi',
                      'diff_volume',
                      'diff_ltp',
                      'timestamp'
                  )
                  ->orderBy('strike')
                  ->orderBy('instrument_type')
                  ->orderBy('timestamp')
                  ->get();

        // Group: strike → CE/PE → candles[]
        $chartData = [];
        foreach ($strikes as $s) {
            $chartData[$s] = ['strike' => (int) $s, 'CE' => [], 'PE' => []];
        }

        foreach ($rows as $row) {
            $strike = (int) $row->strike;
            $type   = $row->instrument_type;

            if (! isset($chartData[$strike])) continue;
            if (! in_array($type, ['CE', 'PE'])) continue;

            $chartData[$strike][$type][] = [
                'x'        => $row->timestamp,
                'open'     => (float) $row->open,
                'high'     => (float) $row->high,
                'low'      => (float) $row->low,
                'close'    => (float) $row->close,
                'volume'   => (int)   ($row->volume        ?? 0),
                'oi'       => (int)   ($row->open_interest ?? 0),
                'build_up' => $row->build_up ?? 'Neutral',
                'diff_oi'  => (int)   ($row->diff_oi       ?? 0),
                'diff_vol' => (int)   ($row->diff_volume   ?? 0),
                'diff_ltp' => (float) ($row->diff_ltp      ?? 0),
            ];
        }

        // Remove strikes with zero CE and zero PE data
        $result = array_values(array_filter(
            $chartData,
            fn($item) => count($item['CE']) > 0 || count($item['PE']) > 0
        ));

        return response()->json([
            'success'    => true,
            'expiry'     => $expiryDate,
            'from_date'  => $fromDate,
            'to_date'    => $toDate,
            'interval'   => $interval,
            'chart_data' => $result,
        ]);
    }
}
