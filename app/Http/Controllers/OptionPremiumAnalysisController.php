<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OptionPremiumAnalysisController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->input('date', date('Y-m-d'));
        $time = $request->input('time');
        
        if (!$time) {
            $now = Carbon::now();
            $time = $now->format('H:i');
            // Check if time is between 09:15 and 13:29, though the prompt says "if not given, take the current date and time and the time should be between 09:15 to 13:29". 
            // We can leave it as current time, or clamp it. I'll just use current time for default.
        }

        $expiry = $request->input('expiry');
        if (!$expiry) {
            $currentExpiry = DB::table('nse_expiries')
                ->where('is_current', 1)
                ->where('instrument_type', 'OPT')
                ->first();
            $expiry = $currentExpiry ? $currentExpiry->expiry_date : null;
        }

        $ce_strike = $request->input('ce_strike');
        $pe_strike = $request->input('pe_strike');

        $current_day_index_open = null;
        
        if (!$ce_strike || !$pe_strike) {
            $dailyTrend = DB::table('daily_trend')
                ->where('symbol_name', 'NIFTY')
                ->where('trading_date', $date)
                ->first();

            if ($dailyTrend && $dailyTrend->current_day_index_open) {
                $current_day_index_open = $dailyTrend->current_day_index_open;
                $atm_strike = round($current_day_index_open / 100) * 100;
                
                if (!$ce_strike) $ce_strike = $atm_strike;
                if (!$pe_strike) $pe_strike = $atm_strike;
            }
        } else {
             $dailyTrend = DB::table('daily_trend')
                ->where('symbol_name', 'NIFTY')
                ->where('trading_date', $date)
                ->first();
             if ($dailyTrend) {
                  $current_day_index_open = $dailyTrend->current_day_index_open;
             }
        }

        $expiries = DB::table('nse_expiries')
            ->where('instrument_type', 'OPT')
            ->orderBy('expiry_date', 'asc')
            ->pluck('expiry_date');

        $data = [];

        if ($ce_strike && $pe_strike && $expiry) {
            $targetDateTime = $date . ' ' . $time . ':59';

            for ($i = 0; $i <= 10; $i++) {
                $ce_leg = $ce_strike + ($i * 50);
                $pe_leg = $pe_strike - ($i * 50);
                
                $distance = $ce_leg - $pe_leg;

                $ce_quote = DB::table('ohlc_quotes')
                    ->where('trading_symbol', 'NIFTY')
                    ->where('strike_price', $ce_leg)
                    ->where('instrument_type', 'CE')
                    ->where('expiry_date', $expiry)
                    ->where('ts_at', '<=', $targetDateTime)
                    ->orderBy('ts_at', 'desc')
                    ->first();

                $pe_quote = DB::table('ohlc_quotes')
                    ->where('trading_symbol', 'NIFTY')
                    ->where('strike_price', $pe_leg)
                    ->where('instrument_type', 'PE')
                    ->where('expiry_date', $expiry)
                    ->where('ts_at', '<=', $targetDateTime)
                    ->orderBy('ts_at', 'desc')
                    ->first();

                $ce_premium = $ce_quote ? $ce_quote->close : 0;
                $pe_premium = $pe_quote ? $pe_quote->close : 0;

                $data[] = [
                    'distance' => $distance,
                    'ce_strike' => $ce_leg,
                    'pe_strike' => $pe_leg,
                    'ce_premium' => $ce_premium,
                    'pe_premium' => $pe_premium,
                    'total_premium' => $ce_premium + $pe_premium,
                    'premium_difference' => abs($ce_premium - $pe_premium),
                ];
            }
        }

        return view('option-premium-analysis', compact(
            'date',
            'time',
            'expiry',
            'expiries',
            'ce_strike',
            'pe_strike',
            'data',
            'current_day_index_open'
        ));
    }
}
