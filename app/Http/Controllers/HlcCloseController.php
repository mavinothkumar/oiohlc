<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HlcCloseController extends Controller
{
    public function index(Request $request)
    {
        // Previous NSE work day
        $workDays = DB::table('nse_working_days')
                      ->where('previous', 1)
                      ->orWhere('current', 1)
                      ->get();
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
                        ->where('trading_symbol', 'NIFTY')
                        ->where('instrument_type', 'OPT')
                        ->where('is_current', 1)
                        ->select('expiry_date')
                        ->first();
        $expiryDate = $expiryData->expiry_date ?? null;

        $spotData            = DB::table('option_chains')
                                 ->where('trading_symbol', 'NIFTY')
                                 ->where('option_type', 'CE')
                                 ->whereDate('captured_at', $prevWorkDate)
                                 ->orderByDesc('captured_at')
                                 ->select('underlying_spot_price')
                                 ->first();
        $underlyingSpotPrice = $spotData->underlying_spot_price ?? null;

        $strikeRange = $request->get('strike_range', 300);
        $minStrike   = $underlyingSpotPrice - $strikeRange;
        $maxStrike   = $underlyingSpotPrice + $strikeRange;

        // Get all CE/PE in range for previous day and expiry

        $ceList = DB::table('daily_ohlc_quotes')
                    ->where('symbol_name', 'NIFTY')
                    ->where('expiry_date', $expiryDate)
                    ->whereBetween('strike', [$minStrike, $maxStrike])
                    ->where('option_type', 'CE')
                    ->where('quote_date', $prevWorkDate)
                    ->select(DB::raw('CAST(strike AS DECIMAL(10,2)) as strike'), 'option_type', 'close')
                    ->orderBy('strike')
                    ->get();

        $peList = DB::table('daily_ohlc_quotes')
                    ->where('symbol_name', 'NIFTY')
                    ->where('expiry_date', $expiryDate)
                    ->whereBetween('strike', [$minStrike, $maxStrike])
                    ->where('option_type', 'PE')
                    ->where('quote_date', $prevWorkDate)
                    ->select(DB::raw('CAST(strike AS DECIMAL(10,2)) as strike'), 'option_type', 'close')
                    ->orderBy('strike')
                    ->get();

        $pairs = [];

        // Forward pairing: For each CE, find nearest PE close value
        foreach ($ceList as $ce) {
            $nearestPe = null;
            $minDiff   = INF;
            foreach ($peList as $pe) {
                $diff = abs($ce->close - $pe->close);
                if ($diff < $minDiff) {
                    $minDiff   = $diff;
                    $nearestPe = $pe;
                    $minDiff   = $diff;
                }
            }
            if ($nearestPe) {
                $pairs[] = [
                    'ce_strike'      => $ce->strike,
                    'ce_close'       => $ce->close,
                    'pe_close'       => $nearestPe->close,
                    'pe_strike'      => $nearestPe->strike,
                    'diff'           => abs($ce->close - $nearestPe->close),
                    'min_resistance' => $ce->strike + $ce->close,
                    'min_support'    => $nearestPe->strike - $nearestPe->close,
                    'sum_ce_pe'      => $ce->close + $nearestPe->close,
                    'max_resistance' => $ce->strike + $ce->close + $nearestPe->close,
                    'max_support'    => $nearestPe->strike - ($ce->close + $nearestPe->close),
                ];
            }
        }

        // Reverse pairing: For each PE, find nearest CE close value
        $reversePairs = [];
        foreach ($peList as $pe) {
            $nearestCe = null;
            $minDiff   = INF;
            foreach ($ceList as $ce) {
                $diff = abs($pe->close - $ce->close);
                if ($diff < $minDiff) {
                    $minDiff   = $diff;
                    $nearestCe = $ce;
                    $minDiff   = $diff;
                }
            }
            if ($nearestCe) {
                $reversePairs[] = [
                    'pe_strike'      => $pe->strike,
                    'pe_close'       => $pe->close,
                    'ce_close'       => $nearestCe->close,
                    'ce_strike'      => $nearestCe->strike,
                    'diff'           => abs($pe->close - $nearestCe->close),
                    'min_resistance' => $nearestCe->strike + $nearestCe->close,
                    'min_support'    => $pe->strike - $pe->close,
                    'sum_ce_pe'      => $nearestCe->close + $pe->close,
                    'max_resistance' => $nearestCe->strike + $nearestCe->close + $pe->close,
                    'max_support'    => $pe->strike - ($nearestCe->close + $pe->close),
                ];
            }
        }

        return view('hlc_close', [
            'pairs'        => $pairs,
            'reversePairs' => $reversePairs,
            'expiryDate'   => $expiryDate,
            'prevWorkDate' => $prevWorkDate,
            'currentWorkDate' => $currentWorkDate,
        ]);
    }
}
