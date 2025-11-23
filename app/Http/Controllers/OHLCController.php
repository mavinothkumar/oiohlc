<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OHLCController extends Controller
{
    public function index(Request $request)
    {
        // Default filter values
        $range      = $request->input('range', 300); // +/- range for strike around spot price
        $filterDate = $request->input('date', Carbon::today()->toDateString());

        // Step 1: Get current expiry date for NIFTY options
        $expiryData = DB::table('expiries')
                        ->where('trading_symbol', 'NIFTY')
                        ->where('instrument_type', 'OPT')
                        ->where('is_current', 1)
                        ->select('expiry_date')
                        ->first();

        if ( ! $expiryData) {
            return view('ohlc.index')->with('error', 'No current expiry found for NIFTY options.');
        }

        $expiryDate = $expiryData->expiry_date;

        // Step 2: Get underlying spot price from option_chains for NIFTY & that expiry date
        $spotData = DB::table('option_chains')
                      ->where('trading_symbol', 'NIFTY')
                      ->where('option_type', 'CE')
                      ->whereDate('captured_at', $filterDate)
                      ->orderByDesc('captured_at')
                      ->select('underlying_spot_price', 'captured_at')
                      ->first();

        if ( ! $spotData) {
            return view('ohlc.index')->with('error', 'No spot price data found for NIFTY options.');
        }

        $spotPrice          = $spotData->underlying_spot_price;
        $timestamp_captured = $spotData->captured_at;

        // Step 3: Calculate strike price range to filter by
        $minStrike = $spotPrice - $range;
        $maxStrike = $spotPrice + $range;

        // Step 4: Fetch option data from full_market_quotes for CE and PE within strike price range, on expiry date
        // Subquery: get the latest timestamp and highest id per strike+option_type
        $full_market_quotes_last_record = DB::table('full_market_quotes')->orderByDesc('timestamp')->first();
        $optionsData                    = DB::table('full_market_quotes')
                                            ->where('symbol_name', 'NIFTY')
                                            ->whereDate('expiry_date', $expiryDate)
                                            ->whereBetween('strike', [$minStrike, $maxStrike])
                                            ->whereIn('option_type', ['CE', 'PE'])
                                            ->where('timestamp', $full_market_quotes_last_record->timestamp)
                                            ->get();

// Join on timestamp, then for tied timestamps pick the highest id ONLY
//        $optionsData = DB::table('full_market_quotes as fmq')
//                         ->joinSub($subQuery, 'sq', function($join) {
//                             $join->on('fmq.strike', '=', 'sq.strike')
//                                  ->on('fmq.option_type', '=', 'sq.option_type')
//                                  ->on('fmq.timestamp', '=', 'sq.latest_timestamp');
//                         })
//            // Below: Only keep the highest id where timestamp ties
//                        ->whereRaw('fmq.id = (SELECT MAX(id) FROM full_market_quotes WHERE strike = fmq.strike AND option_type = fmq.option_type AND timestamp = fmq.timestamp)')
//                        ->whereDate('fmq.expiry_date', $expiryDate)
//                         ->select(
//                             'fmq.strike',
//                             'fmq.option_type',
//                             'fmq.open',
//                             'fmq.high',
//                             'fmq.low',
//                             'fmq.close',
//                             'fmq.last_price'
//                         )
//                         ->orderBy('fmq.strike', 'asc')
//                         ->get();


        return view('ohlc.index', compact('optionsData', 'spotPrice', 'minStrike', 'maxStrike', 'expiryDate', 'range', 'filterDate'));
    }
}
