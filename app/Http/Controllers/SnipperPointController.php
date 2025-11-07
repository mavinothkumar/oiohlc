<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SnipperPointController extends Controller
{

    public function index(Request $request)
    {
        $index       = $request->get('index', 'NIFTY');
        $strikeRange = intval($request->get('strike_range', $index == 'NIFTY' ? 400 : 1000));
        $delta       = intval($request->get('delta', 5));
        //$strikeSteps = $index == 'NIFTY' ? [50, 100] : [100, 200];
        $strikeSteps = $index == 'NIFTY' ? [100] : [100];

        // Previous working day
        $prevDay = DB::table('nse_working_days')->where('previous', 1)->value('working_date');

        // Current expiry
        $expiry = DB::table('expiries')
                    ->where('instrument_type', 'OPT')
                    ->where('is_current', 1)
                    ->where('trading_symbol', $index)
                    ->value('expiry_date');

        // Spot price from option_chains (latest data)
        $spotPrice = DB::table('option_chains')
                       ->where('trading_symbol', $index)
                       ->where('expiry', $expiry)
                       ->orderByDesc('captured_at')
                       ->value('underlying_spot_price');

        // Calculate OTM strikes for each step
        $strikes = [];
        foreach ($strikeSteps as $step) {
            // Find rounded ATM strike (nearest multiple)
            $atm            = round($spotPrice / $step) * $step;
            $ce_otm         = $atm + $step; // CE OTM: Next higher strike
            $pe_otm         = $atm - $step; // PE OTM: Next lower strike
            $strikes[$step] = [
                'ce_otm' => $ce_otm,
                'pe_otm' => $pe_otm,
            ];
        }


        // Fetch OHLC data for previous day and selected strikes in range
        $strikeMin = $spotPrice - $strikeRange;
        $strikeMax = $spotPrice + $strikeRange;

        return $ohlc = DB::table('daily_ohlc_quotes')
                         ->where('symbol_name', $index)
                         ->where('expiry', $expiry)
                         ->where('quote_date', $prevDay)
                         ->whereBetween('strike', [$strikeMin, $strikeMax])
                         ->get();

        // Fetch LTP values from option_chains table
        $ltpsRaw = DB::table('option_chains')
                     ->where('trading_symbol', $index)
                     ->where('expiry', $expiry)
                     ->whereBetween('strike_price', [$strikeMin, $strikeMax])
                     ->whereIn('option_type', ['CE', 'PE'])
                     ->get();
        // Group by strike_price + option_type for quick lookup
        $ltps = [];
        foreach ($ltpsRaw as $row) {
            $ltps[$row->strike_price.$row->option_type] = $row->ltp;
        }

        return view('snipper-point', compact('index', 'strikeRange', 'delta', 'strikeSteps', 'strikes', 'spotPrice', 'ohlc', 'ltps', 'prevDay'));
    }
}
