<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OiVolumeChartController extends Controller
{
    const STEP_INTERVAL = '5minute';
    const STEP_MINUTES  = 5;
    const MARKET_START  = '09:15';
    const MARKET_END    = '15:30';
    const ATM_ROUND     = 50;

    // -----------------------------------------------------------------------
    // GET /oi-volume-chart
    // -----------------------------------------------------------------------
    public function index(Request $request)
    {
        $request->validate([
            'symbol'     => 'nullable|string',
            'quote_date' => 'nullable|date',
            'expiry'     => 'nullable|date',
            'strikes'    => 'nullable|integer|in:3,5,7,9',
        ]);

        $symbol     = $request->input('symbol', 'NIFTY');
        $quoteDate  = $request->input('quote_date');
        $expiry     = $request->input('expiry');
        $numStrikes = (int) $request->input('strikes', 3);
        $slots      = $this->buildSlots();

        $dates = DB::table('nse_working_days')
                   ->selectRaw('DATE(working_date) as trade_date')
                   ->distinct()
                   ->orderByDesc('trade_date')
                   ->pluck('trade_date');

        return view('oi-volume-chart', compact(
            'symbol', 'quoteDate', 'expiry', 'numStrikes', 'slots', 'dates'
        ));
    }

    // -----------------------------------------------------------------------
    // GET /api/oi-volume-expiries
    // -----------------------------------------------------------------------
    public function getExpiries(Request $request)
    {
        $request->validate([
            'symbol'      => 'required|string',
            'quote_date'  => 'required|date',
            'num_strikes' => 'nullable|integer|in:3,5,7,9',
        ]);

        $symbol     = $request->input('symbol');
        $quoteDate  = $request->input('quote_date');
        $numStrikes = (int) $request->input('num_strikes', 3);

        $expiry = DB::table('expired_expiries')
                    ->where('underlying_symbol', $symbol)
                    ->where('instrument_type', 'OPT')
                    ->where('expiry_date', '>=', $quoteDate)
                    ->orderBy('expiry_date')
                    ->value('expiry_date');

        if (! $expiry) {
            $expiry = DB::table('expired_expiries')
                        ->where('underlying_symbol', $symbol)
                        ->where('instrument_type', 'OPT')
                        ->where('expiry_date', '<', $quoteDate)
                        ->orderByDesc('expiry_date')
                        ->value('expiry_date');
        }

        $indexRow = DB::table('expired_ohlc')
                      ->where('underlying_symbol', $symbol)
                      ->where('instrument_type', 'INDEX')
                      ->where('interval', self::STEP_INTERVAL)
                      ->whereDate('timestamp', $quoteDate)
                      ->whereRaw("TIME(timestamp) = '09:15:00'")
                      ->first();

        $spotClose = $indexRow ? (float) $indexRow->close : null;
        $atm       = $spotClose
            ? (int) (round($spotClose / self::ATM_ROUND) * self::ATM_ROUND)
            : null;

        $strikes = [];
        if ($atm !== null) {
            $half = (int) (($numStrikes - 1) / 2);
            for ($i = -$half; $i <= $half; $i++) {
                $strikes[] = $atm + ($i * self::ATM_ROUND);
            }
        }

        return response()->json([
            'expiry'     => $expiry,
            'spot'       => $spotClose,
            'atm_strike' => $atm,
            'strikes'    => $strikes,
        ]);
    }

    // -----------------------------------------------------------------------
    // GET /api/oi-volume-slot
    // -----------------------------------------------------------------------
    public function getSlotData(Request $request)
    {
        $request->validate([
            'symbol'      => 'required|string',
            'quote_date'  => 'required|date',
            'expiry'      => 'required|date',
            'num_strikes' => 'nullable|integer|in:3,5,7,9',
            'slot_index'  => 'required|integer|min:0',
        ]);

        $symbol     = $request->input('symbol');
        $quoteDate  = $request->input('quote_date');
        $expiry     = $request->input('expiry');
        $numStrikes = (int) $request->input('num_strikes', 3);
        $slotIdx    = (int) $request->input('slot_index');
        $slots      = $this->buildSlots();

        if (! isset($slots[$slotIdx])) {
            return response()->json(['error' => 'Invalid slot index'], 422);
        }

        $currSlot = $slots[$slotIdx];

        // ATM from 09:15 INDEX candle
        $indexFirst = DB::table('expired_ohlc')
                        ->where('underlying_symbol', $symbol)
                        ->where('instrument_type', 'INDEX')
                        ->where('interval', self::STEP_INTERVAL)
                        ->whereDate('timestamp', $quoteDate)
                        ->whereRaw("TIME(timestamp) = '09:15:00'")
                        ->first();

        $spotClose = $indexFirst ? (float) $indexFirst->close : null;
        $atm       = $spotClose
            ? (int) (round($spotClose / self::ATM_ROUND) * self::ATM_ROUND)
            : null;

        if (! $atm) {
            return response()->json(['error' => 'Could not determine ATM strike'], 422);
        }

        $half    = (int) (($numStrikes - 1) / 2);
        $strikes = [];
        for ($i = -$half; $i <= $half; $i++) {
            $strikes[] = $atm + ($i * self::ATM_ROUND);
        }

        // INDEX candles up to current slot
        $indexCandles = DB::table('expired_ohlc')
                          ->where('underlying_symbol', $symbol)
                          ->where('instrument_type', 'INDEX')
                          ->where('interval', self::STEP_INTERVAL)
                          ->whereDate('timestamp', $quoteDate)
                          ->whereRaw("TIME(timestamp) <= ?", [$currSlot . ':00'])
                          ->orderBy('timestamp')
                          ->get(['open', 'high', 'low', 'close', 'timestamp'])
                          ->map(fn($r) => [
                              'time'  => $r->timestamp,
                              'open'  => (float) $r->open,
                              'high'  => (float) $r->high,
                              'low'   => (float) $r->low,
                              'close' => (float) $r->close,
                          ]);

        // CE + PE rows up to current slot — includes open_interest, volume, build_up
        $rows = DB::table('expired_ohlc')
                  ->where('underlying_symbol', $symbol)
                  ->where('expiry', $expiry)
                  ->where('interval', self::STEP_INTERVAL)
                  ->whereDate('timestamp', $quoteDate)
                  ->whereIn('instrument_type', ['CE', 'PE'])
                  ->whereIn('strike', $strikes)
                  ->whereRaw("TIME(timestamp) <= ?", [$currSlot . ':00'])
                  ->orderBy('timestamp')
                  ->get(['strike', 'instrument_type', 'open_interest', 'volume', 'build_up', 'timestamp']);

        /*
         * Build three output shapes from the same row set:
         *
         * 1. time_series  – OI & Volume per strike/type (for Row 2)
         *    { strike: { CE: [{time, open_interest, volume}], PE: [...] } }
         *
         * 2. build_up_data – OI value per strike/type/build_up_type per time (for Row 1)
         *    { strike: { CE: { 'Long Build': {time: oi_value}, ... }, PE: {...} } }
         *
         * 3. time_labels   – sorted unique HH:mm list (shared x-axis)
         */
        $timeSeries   = [];
        $buildUpRaw   = []; // [strike][type][time] = [build_up, open_interest]

        foreach ($rows as $row) {
            $s    = (int) $row->strike;
            $t    = $row->instrument_type;          // CE or PE
            $time = Carbon::parse($row->timestamp)->format('H:i');
            $oi   = (int) $row->open_interest;
            $bu   = $row->build_up ?? 'Neutral';    // build_up column value

            // OI + Volume series
            $timeSeries[$s][$t][] = [
                'time'          => $time,
                'open_interest' => $oi,
                'volume'        => (int) $row->volume,
            ];

            // Build-up: store OI value keyed by time, under the build_up type
            $buildUpRaw[$s][$t][$time] = [
                'build_up'      => $bu,
                'open_interest' => $oi,
            ];
        }

        // Collect sorted unique time labels
        $timeLabels = [];
        foreach ($timeSeries as $strikeSeries) {
            foreach ($strikeSeries as $typeSeries) {
                foreach ($typeSeries as $point) {
                    $timeLabels[] = $point['time'];
                }
            }
        }
        $timeLabels = array_values(array_unique($timeLabels));
        sort($timeLabels);

        /*
         * Shape build_up_data for the frontend:
         * For every time label, for each strike/type combination, place the OI
         * value under its build_up type key — null for all other types at that time.
         *
         * Result: { strike: { CE: { 'Long Build': [oi|null, …], 'Short Build': […], … }, PE: {…} } }
         */
        $buildUpTypes = ['Long Build', 'Short Build', 'Short Cover', 'Long Unwind'];
        $buildUpData  = [];

        foreach ($strikes as $strike) {
            foreach (['CE', 'PE'] as $type) {
                foreach ($buildUpTypes as $buType) {
                    $buildUpData[$strike][$type][$buType] = [];
                }

                foreach ($timeLabels as $tl) {
                    $entry   = $buildUpRaw[$strike][$type][$tl] ?? null;
                    $thisBu  = $entry['build_up']      ?? null;
                    $thisOi  = $entry['open_interest']  ?? null;

                    foreach ($buildUpTypes as $buType) {
                        // Only the matching build_up type gets the OI value;
                        // all other types get null so Chart.js shows a gap
                        $buildUpData[$strike][$type][$buType][] =
                            ($thisBu === $buType) ? $thisOi : null;
                    }
                }
            }
        }

        return response()->json([
            'slot_index'    => $slotIdx,
            'label'         => $currSlot,
            'total_slots'   => count($slots),
            'strikes'       => $strikes,
            'atm'           => $atm,
            'time_labels'   => $timeLabels,
            'time_series'   => $timeSeries,
            'build_up_data' => $buildUpData,
            'index_candles' => $indexCandles,
        ]);
    }

    // -----------------------------------------------------------------------
    private function buildSlots(): array
    {
        $slots = [];
        $start = strtotime(self::MARKET_START);
        $end   = strtotime(self::MARKET_END);
        $step  = self::STEP_MINUTES * 60;
        for ($t = $start; $t <= $end; $t += $step) {
            $slots[] = date('H:i', $t);
        }
        return $slots;
    }
}
