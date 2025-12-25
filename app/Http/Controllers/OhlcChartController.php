<?php

// app/Http/Controllers/OhlcController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OhlcChartController extends Controller
{
    public function index()
    {
        // initial page – you can preload symbols or dates if needed
        return view('options-chart');
    }

    public function expiries(Request $request)
    {
        $request->validate([
            'underlying_symbol' => 'required|string',
            'date'              => 'required|date',
        ]);

        $symbol = $request->underlying_symbol;
        $date   = $request->date;

        // List expiries that have data on/after this date
        $expiries = DB::table('expired_ohlc')
                      ->where('underlying_symbol', $symbol)
                      ->whereNotNull('expiry')
                      ->whereDate('timestamp', $date)
                      ->distinct()
                      ->orderBy('expiry')
                      ->pluck('expiry');

        return response()->json([
            'expiries' => $expiries,
        ]);
    }

    public function ohlc(Request $request)
    {
        $request->validate([
            'underlying_symbol' => 'required|string',
            'expiry'            => 'required|date',
            'date'              => 'required|date',
            'ce_instrument_key' => 'required|string',
            'pe_instrument_key' => 'required|string',
        ]);

        $symbol = $request->underlying_symbol;
        $expiry = $request->expiry;
        $date   = $request->date;
        $ceKey  = $request->ce_instrument_key;
        $peKey  = $request->pe_instrument_key;

        $base = DB::table('expired_ohlc')
                  ->where('underlying_symbol', $symbol)
                  ->whereDate('expiry', $expiry)
                  ->where('interval', '5minute')
                  ->whereDate('timestamp', $date)
                  ->where([
                      ['strike', $ceKey],
                      ['strike', $peKey],
                  ])
                  ->orderBy('timestamp', 'asc');

        $ce = (clone $base)
            // ->where('instrument_key', $ceKey)
            ->where('instrument_type', 'CE')
            ->get(['open', 'high', 'low', 'close', 'timestamp']);

        $pe = (clone $base)
            //->where('instrument_key', $peKey)
            ->where('instrument_type', 'PE')
            ->get(['open', 'high', 'low', 'close', 'timestamp']);

        $map = fn($row) => [
            'time'  => strtotime($row->timestamp),  // strictly increasing
            'open'  => (float) $row->open,
            'high'  => (float) $row->high,
            'low'   => (float) $row->low,
            'close' => (float) $row->close,
        ];

        return response()->json([
            'ce' => $ce->map($map)->values(),
            'pe' => $pe->map($map)->values(),
        ]);
    }
}
