<?php
// app/Http/Controllers/TradingSimulatorController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TradingSimulatorController extends Controller
{
    // Nifty lot size
    const LOT_SIZE = 75;

    public function index()
    {
        // Available trading dates from expired_ohlc

        $dates = DB::table('nse_working_days')
                   ->selectRaw('DATE(working_date) as trade_date')
                   ->distinct()
                   ->orderByDesc('trade_date')
                   ->pluck('trade_date');

        // Latest available date to default the calendar to
        $latestDate = $dates->first();

        return view('test.trading-simulator', compact('dates', 'latestDate'));
    }

    /**
     * Get expiry for a given date (nearest expiry >= date from expired_expiries)
     */
    public function getExpiry(Request $request)
    {
        $date = $request->query('date');

        $expiry = DB::table('expired_expiries')
                    ->where('underlying_symbol', 'NIFTY')
                    ->where('instrument_type', 'OPT')
                    ->where('expiry_date', '>=', $date)
                    ->orderBy('expiry_date')
                    ->value('expiry_date');


        return response()->json(['expiry' => $expiry]);
    }

    /**
     * Get available strikes for a given date + expiry
     */
    public function getStrikes(Request $request)
    {
        $date   = $request->query('date');
        $expiry = $request->query('expiry');

        $strikes = DB::table('expired_ohlc')
                     ->where('underlying_symbol', 'NIFTY')
                     ->where('expiry', $expiry)
                     ->whereDate('timestamp', $date)
                     ->select('strike', 'instrument_type')
                     ->distinct()
                     ->orderBy('strike')
                     ->get()
                     ->groupBy('strike')
                     ->map(fn($group) => $group->pluck('instrument_type'))
                     ->toArray();

        return response()->json(['strikes' => $strikes]);
    }

    /**
     * Get OHLC open price for a specific strike/type/expiry at a given datetime
     */
    public function getPrice(Request $request)
    {
        $expiry    = $request->query('expiry');
        $strike    = $request->query('strike');
        $type      = $request->query('type');      // CE or PE
        $timestamp = $request->query('timestamp'); // Y-m-d H:i:s

        $row = DB::table('expired_ohlc')
                 ->where('underlying_symbol', 'NIFTY')
                 ->where('expiry', $expiry)
                 ->where('strike', $strike)
                 ->where('instrument_type', $type)
                 ->where('timestamp', $timestamp)
                 ->select('open', 'high', 'low', 'close', 'volume', 'open_interest')
                 ->first();

        return response()->json($row ?? ['open' => null]);
    }
}
