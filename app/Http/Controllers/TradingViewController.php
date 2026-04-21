<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TradingViewController extends Controller
{
    /**
     * Main page – auto-loads everything NIFTY needs on page load:
     *  - Current expiry from nse_expiries (is_current = 1)
     *  - MidPoint from daily_trend for previous working day
     *  - 7 strikes: ATM ±3 in 50s from current day index open
     *  - Current working date for the trade_date field
     */
    public function index()
    {
        $symbol = 'NIFTY';

        // ── 1. Current expiry ────────────────────────────────────────────────
        $expiry = DB::table('nse_expiries')
                    ->where('trading_symbol', $symbol)
                    ->where('is_current', 1)
                    ->orderBy('expiry_date')
                    ->value('expiry_date');

        // ── 2. Working dates ─────────────────────────────────────────────────
        $currentWorkingDate = DB::table('nse_working_days')
                                ->where('current', 1)
                                ->value('working_date');

        $previousWorkingDate = DB::table('nse_working_days')
                                 ->where('previous', 1)
                                 ->value('working_date');

        // ── 3. MidPoint from previous day's daily_trend ──────────────────────
        $midPoint = null;
        $currentDayIndexOpen = null;

        if ($previousWorkingDate) {
            $trend = DB::table('daily_trend')
                       ->where('quote_date', $previousWorkingDate)
                       ->where('symbol_name', $symbol)
                       ->select('mid_point', 'current_day_index_open')
                       ->first();

            $underlying_spot_price = DB::table('option_chains')->orderByDesc('captured_at')->first();

            if ($trend) {
                $midPoint            = $trend->mid_point;
                $currentDayIndexOpen = $underlying_spot_price->underlying_spot_price;
            }
        }

        // ── 4. Generate ATM ±3 strikes in 50s ───────────────────────────────
        $strikes = [];
        if ($currentDayIndexOpen) {
            // Round to nearest 50 for ATM
            $atm = (int) (round($currentDayIndexOpen / 50) * 50);
            for ($i = -4; $i <= 5; $i++) {
                $strikes[] = $atm + ($i * 50);
            }
        }

        // Trade date is the current working day
        $tradeDate = $currentWorkingDate ?? now()->toDateString();

        return view('trading.chart', compact(
            'symbol',
            'expiry',
            'tradeDate',
            'midPoint',
            'strikes',
        ));
    }

    /**
     * AJAX – expiry dates for a symbol from nse_expiries (is_current = 1).
     * Returns full row objects; JS reads .expiry_date from each.
     */
    public function getExpiries(Request $request)
    {
        $request->validate(['symbol' => 'required|string']);

        $expiries = DB::table('nse_expiries')
                      ->where('symbol', $request->input('symbol'))
                      ->where('is_current', 1)
                      ->get();

        return response()->json(['expiries' => $expiries]);
    }

    /**
     * AJAX – candle data for selected strikes on a given trade date.
     */
    public function fetchChartData(Request $request)
    {
        $request->validate([
            'strikes'           => 'required|array|min:1|max:10',
            'strikes.*'         => 'numeric',
            'underlying_symbol' => 'required|string',
            'expiry_date'       => 'required|date',
            'trade_date'        => 'required|date',
            'midpoint'          => 'nullable|numeric',
        ]);

        $strikes          = $request->input('strikes');
        $underlyingSymbol = $request->input('underlying_symbol');
        $expiryDate       = $request->input('expiry_date');
        $tradeDate        = $request->input('trade_date');
        $midpoint         = $request->input('midpoint');

        // Force trade session window: 09:15 to 15:35 IST
        $start = Carbon::parse($tradeDate . ' 09:15:00', 'Asia/Kolkata');
        $end   = Carbon::parse($tradeDate . ' 15:35:00', 'Asia/Kolkata');

        $snapshots = DB::table('ohlc_live_snapshots')
                       ->whereIn('strike', $strikes)
                       ->where('underlying_symbol', $underlyingSymbol)
                       ->where('expiry_date', $expiryDate)
                       ->whereIn('instrument_type', ['CE', 'PE'])
                       ->whereBetween('timestamp', [$start, $end])
                       ->select([
                           'id', 'strike', 'instrument_type',
                           'open', 'high', 'low', 'close',
                           'oi', 'volume', 'build_up',
                           'diff_oi', 'diff_volume', 'diff_ltp', 'timestamp',
                       ])
                       ->orderBy('strike')
                       ->orderBy('instrument_type')
                       ->orderBy('timestamp')
                       ->get();

        // Group: strike → CE/PE → candles
        $grouped = [];
        foreach ($snapshots as $row) {
            $strike = (string) $row->strike;
            $type   = $row->instrument_type;

            if (!isset($grouped[$strike])) {
                $grouped[$strike] = ['CE' => [], 'PE' => []];
            }

            $grouped[$strike][$type][] = [
                'id'          => $row->id,
                'time'        => Carbon::parse($row->timestamp)->timestamp,
                'open'        => (float) $row->open,
                'high'        => (float) $row->high,
                'low'         => (float) $row->low,
                'close'       => (float) $row->close,
                'oi'          => (int)   $row->oi,
                'volume'      => (int)   $row->volume,
                'build_up'    => $row->build_up,
                'diff_oi'     => (int)   $row->diff_oi,
                'diff_volume' => (int)   $row->diff_volume,
                'diff_ltp'    => (float) $row->diff_ltp,
                'timestamp'   => $row->timestamp,
            ];
        }

        // First 5-min candle high / low (index 0 = 09:15 candle)
        $firstCandle = [];
        foreach ($grouped as $strike => $types) {
            $firstCandle[$strike] = [];
            foreach (['CE', 'PE'] as $type) {
                if (!empty($types[$type])) {
                    $f = $types[$type][0];
                    $firstCandle[$strike][$type] = [
                        'high' => $f['high'],
                        'low'  => $f['low'],
                        'time' => $f['time'],
                    ];
                }
            }
        }

        // Top 5 candles by diff_oi and diff_volume
        $topMarkers = [];
        foreach ($grouped as $strike => $types) {
            $topMarkers[$strike] = [];
            foreach (['CE', 'PE'] as $type) {
                if (empty($types[$type])) {
                    $topMarkers[$strike][$type] = ['oi' => [], 'volume' => []];
                    continue;
                }

                $candles = $types[$type];

                $byOi = $candles;
                usort($byOi, fn($a, $b) => abs($b['diff_oi']) <=> abs($a['diff_oi']));

                $byVol = $candles;
                usort($byVol, fn($a, $b) => $b['diff_volume'] <=> $a['diff_volume']);

                $topMarkers[$strike][$type] = [
                    'oi'     => array_slice(array_column($byOi,  'time'), 0, 5),
                    'volume' => array_slice(array_column($byVol, 'time'), 0, 5),
                ];
            }
        }

        return response()->json([
            'success'     => true,
            'data'        => $grouped,
            'firstCandle' => $firstCandle,
            'topMarkers'  => $topMarkers,
            'midpoint'    => $midpoint !== null ? (float) $midpoint : null,
            'tradeDate'   => $tradeDate,
            'strikes'     => $strikes,
        ]);
    }
}
