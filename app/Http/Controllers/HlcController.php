<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HlcController extends Controller
{
    public function index(Request $request)
    {
        // Get previous NSE working day
        $workDays = DB::table('nse_working_days')
                      ->where('previous', 1)
                      ->orWhere('current', 1)
                      ->get();
        $symbol   = $request->input('symbol', 'NIFTY');
        foreach ($workDays as $days) {
            if (1 === $days->previous) {
                $prevWorkDate = $days->working_date ?? now()->subDay()->toDateString();
            }
            if (1 === $days->current) {
                $currentWorkDate = $days->working_date ?? now()->subDay()->toDateString();
            }
        }


        // Get current NIFTY expiry
        $expiryData = DB::table('expiries')
                        ->where('trading_symbol', $symbol)
                        ->where('instrument_type', 'OPT')
                        ->where('is_current', 1)
                        ->select('expiry_date')
                        ->first();
        $expiryDate = $expiryData->expiry_date ?? null;

        // Get underlying spot price on previous day (CE entry)
        $spotData = DB::table('daily_ohlc_quotes')
                      ->where('symbol_name', $symbol)
                      ->where('option_type', 'INDEX')
                      ->select('close')
                      ->first();
        if ( ! empty($spotData->close)) {
            $underlyingSpotPrice = $spotData->close ?? null;
        } else {
            $spotData            = DB::table('option_chains')
                                     ->where('trading_symbol', $symbol)
                                     ->where('option_type', 'CE')
                                     ->orderBy('captured_at')
                                     ->select('underlying_spot_price')
                                     ->first();
            $underlyingSpotPrice = $spotData->underlying_spot_price ?? null;
        }

        $strikeRange = $request->get('strike_range', 300);
         $minStrike   = $underlyingSpotPrice - $strikeRange;
        $maxStrike   = $underlyingSpotPrice + $strikeRange;

        // Get all strikes in range for prev expiry date and captured_at
        $rowsRaw = DB::table('daily_ohlc_quotes')
                     ->where('symbol_name', $symbol)
                     ->where('expiry_date', $expiryDate)
                     ->whereBetween('strike', [$minStrike, $maxStrike])
                     ->whereIn('option_type', ['CE', 'PE'])
                     ->where('quote_date', $prevWorkDate)
                     ->select(DB::raw('CAST(strike AS DECIMAL(10,2)) as strike'), 'option_type', 'close AS ltp')
                     ->orderBy('strike')
                     ->get();

        // Combine CE & PE by strike
        $grouped = [];
        foreach ($rowsRaw as $row) {
            $strike                              = (string) $row->strike;
            $grouped[$strike][$row->option_type] = $row->ltp;
        }

        $rows      = [];
        $atmStrike = null;
        $minDiff   = INF;
        foreach ($grouped as $strike => $set) {
            if (isset($set['CE']) && isset($set['PE'])) {
                $ceLtp = $set['CE'];
                $peLtp = $set['PE'];
                $diff  = abs($ceLtp - $peLtp);
                if ($diff < $minDiff) {
                    $minDiff   = $diff;
                    $atmStrike = $strike;
                }
                $rows[] = [
                    'strike'         => $strike,
                    'ce_ltp'         => $ceLtp,
                    'pe_ltp'         => $peLtp,
                    'diff'           => $diff,
                    'min_resistance' => $strike + $ceLtp,
                    'min_support'    => $strike - $peLtp,
                    'sum_ce_pe'      => $ceLtp + $peLtp,
                    'max_resistance' => $strike + $ceLtp + $peLtp,
                    'max_support'    => $strike - ($ceLtp + $peLtp),
                ];
            }
        }

        // Also get daily OHLC for prevWorkDate
        $ohlcQuote = DB::table('daily_ohlc_quotes')
                       ->where('symbol_name', $symbol)
                       ->where('quote_date', $prevWorkDate)
                       ->orderByDesc('quote_date')
                       ->first();

        return view('hlc', [
            'rows'                => $rows,
            'atmStrike'           => $atmStrike,
            'underlyingSpotPrice' => $underlyingSpotPrice,
            'expiryDate'          => $expiryDate,
            'ohlcQuote'           => $ohlcQuote,
            'prevWorkDate'        => $prevWorkDate,
            'currentWorkDate'     => $currentWorkDate,
            'strikeRange'         => $strikeRange,
        ]);
    }
}
