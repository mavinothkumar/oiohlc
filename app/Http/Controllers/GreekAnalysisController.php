<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\OptionChain;

class GreekAnalysisController extends Controller
{
    public function index(Request $request)
    {
        // ----- 1. Default values -----
        $defaultExpiry = DB::table('nse_expiries')->where('trading_symbol', 'NIFTY')
                                  ->where('instrument_type', 'OPT')
                                  ->where('is_current', 1)
                                  ->value('expiry_date');

        $selectedExpiry = $request->input('expiry', $defaultExpiry);
        $selectedDate   = $request->input('date', today()->toDateString());
        $putStrike      = $request->input('put_strike');
        $callStrike     = $request->input('call_strike');
        $enterPrice     = $request->input('enter_price');   // combined credit received

        // ----- 2. Dropdown options -----
        $expiries = DB::table('nse_expiries')->where('trading_symbol', 'NIFTY')
                             ->where('instrument_type', 'OPT')
                             ->orderBy('expiry_date')
                             ->pluck('expiry_date', 'expiry_date');

        $strikes = OptionChain::where('trading_symbol', 'NIFTY')
                              ->where('expiry', $selectedExpiry)
                              ->distinct()
                              ->orderBy('strike_price')
                              ->pluck('strike_price');

        // ----- 3. Query data only if both strikes are chosen -----
        $data = collect();
        $labels = $combinedLtp = $netVega = $netTheta = $netGamma = $netDelta = [];
        $putIv = $callIv = $putPop = $callPop = [];

        if ($putStrike && $callStrike) {
            $rows = DB::table('option_chains as put')
                      ->join('option_chains as call', function ($join) {
                          $join->on('put.captured_at', '=', 'call.captured_at')
                               ->on('put.expiry', '=', 'call.expiry')
                               ->on('put.trading_symbol', '=', 'call.trading_symbol');
                      })
                      ->where('put.strike_price', $putStrike)
                      ->where('put.option_type', 'PE')
                      ->where('call.strike_price', $callStrike)
                      ->where('call.option_type', 'CE')
                      ->where('put.expiry', $selectedExpiry)
                      ->whereDate('put.captured_at', $selectedDate)
                      ->orderBy('put.captured_at')
                      ->select(
                          'put.captured_at',
                          'put.ltp as put_ltp',
                          'call.ltp as call_ltp',
                          'put.vega as put_vega',
                          'call.vega as call_vega',
                          'put.theta as put_theta',
                          'call.theta as call_theta',
                          'put.gamma as put_gamma',
                          'call.gamma as call_gamma',
                          'put.delta as put_delta',
                          'call.delta as call_delta',
                          'put.iv as put_iv',
                          'call.iv as call_iv',
                          'put.pop as put_pop',
                          'call.pop as call_pop'
                      )
                      ->get();

            $data = $rows;
            $labels = $rows->pluck('captured_at')->map(fn($d) => \Carbon\Carbon::parse($d)->format('H:i'));
            $combinedLtp = $rows->map(fn($r) => round($r->put_ltp + $r->call_ltp, 2));
            $netVega   = $rows->map(fn($r) => round(-($r->put_vega + $r->call_vega), 4));
            $netTheta  = $rows->map(fn($r) => round(-($r->put_theta + $r->call_theta), 4));
            $netGamma  = $rows->map(fn($r) => round(-($r->put_gamma + $r->call_gamma), 4));
            $netDelta  = $rows->map(fn($r) => round(-($r->put_delta + $r->call_delta), 4));
            $putIv     = $rows->pluck('put_iv');
            $callIv    = $rows->pluck('call_iv');
            $putPop    = $rows->pluck('put_pop');
            $callPop   = $rows->pluck('call_pop');
        }

        return view('greek-analysis', compact(
            'expiries', 'strikes',
            'selectedExpiry', 'selectedDate', 'putStrike', 'callStrike', 'enterPrice',
            'labels', 'combinedLtp', 'netVega', 'netTheta', 'netGamma', 'netDelta',
            'putIv', 'callIv', 'putPop', 'callPop', 'data'
        ));
    }
}
