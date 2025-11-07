<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SnipperPointController extends Controller
{
    public function index(Request $request)
    {
        $index = $request->get('index', 'NIFTY');
        // Set default strike step and range
        $strikeStep = $index == 'NIFTY' ? 100 : 100; // Change as required (for NIFTY: 100, for BANKNIFTY/SENSEX: 100/200)
        $strikeRange = intval($request->get('strike_range', 400));
        $delta = intval($request->get('delta', 20));

        // Get previous working day, latest expiry and spot price
        $prevDay = DB::table('nse_working_days')->where('previous', 1)->value('working_date');
        $expiry = DB::table('expiries')
                    ->where('instrument_type', 'OPT')
                    ->where('is_current', 1)
                    ->where('trading_symbol', $index)
                    ->value('expiry_date');
        $spotPrice = DB::table('option_chains')
                       ->where('trading_symbol', $index)
                       ->where('expiry', $expiry)
                       ->orderByDesc('captured_at')
                       ->value('underlying_spot_price');

        // Calculate base strikes
        $atmStrike = round($spotPrice / $strikeStep) * $strikeStep;
        $startStrike = $atmStrike - $strikeRange;
        $endStrike = $atmStrike + $strikeRange;
        $baseStrikes = [];
        for ($strike = $startStrike; $strike <= $endStrike; $strike += $strikeStep) {
            $baseStrikes[] = $strike;
        }

        // Build CE OTM and PE OTM lists for each base
        $tableRows = [];
        $allNeededStrikes = [];
        foreach ($baseStrikes as $base) {
            $ceOtm = $base + $strikeStep;
            $peOtm = $base - $strikeStep;
            $allNeededStrikes[] = $ceOtm;
            $allNeededStrikes[] = $peOtm;
            $tableRows[] = [
                'step' => $strikeStep,
                'base' => $base,
                'ce_otm' => $ceOtm,
                'pe_otm' => $peOtm
            ];
        }
        $allNeededStrikes = array_unique($allNeededStrikes);

        // Fetch OHLC and LTP in bulk
         $ohlcRows = DB::table('daily_ohlc_quotes')
                      ->where('symbol_name', $index)
                      ->where('expiry_date', $expiry)
                      ->where('quote_date', $prevDay)
                      ->whereIn('strike', $allNeededStrikes)
                      ->get();
         $ltpRows = DB::table('option_chains')
                     ->where('trading_symbol', $index)
                     ->where('expiry', $expiry)
                     ->whereIn('strike_price', $allNeededStrikes)
                     ->whereIn('option_type', ['CE', 'PE'])
                     ->orderByDesc('captured_at')
                     ->get();

        // Index for fast lookup
        $ohlc = [];
        foreach ($ohlcRows as $r) $ohlc[$r->strike .'.00'. $r->option_type] = $r;
        $ltps = [];
        foreach ($ltpRows as $r) $ltps[$r->strike_price .$r->option_type] = $r->ltp;

        // Assemble table with all calculation for Blade
        foreach ($tableRows as &$row) {
            $ceKey = $row['ce_otm'] . '.00CE';
            $peKey = $row['pe_otm'] . '.00PE';
            $ohlcCe = $ohlc[$ceKey] ?? null;
            $ohlcPe = $ohlc[$peKey] ?? null;
            $ltpCe = $ltps[$ceKey] ?? null;
            $ltpPe = $ltps[$peKey] ?? null;

            $row['snipper_avg'] = ($ohlcCe && $ohlcPe) ? ($ohlcCe->close + $ohlcPe->close) / 2 : null;
            $row['close_ce'] = $ohlcCe->close ?? null;
            $row['close_pe'] = $ohlcPe->close ?? null;
            $row['high_ce'] = $ohlcCe->high ?? null;
            $row['high_pe'] = $ohlcPe->high ?? null;
            $row['ltp_ce'] = $ltpCe;
            $row['ltp_pe'] = $ltpPe;
            $row['ce_diff'] = ($row['snipper_avg'] !== null && $ltpCe !== null) ? $ltpCe - $row['snipper_avg'] : null;
            $row['pe_diff'] = ($row['snipper_avg'] !== null && $ltpPe !== null) ? $ltpPe - $row['snipper_avg'] : null;
        }
        unset($row);

        return view('snipper-point', [
            'index'       => $index,
            'spotPrice'   => $spotPrice,
            'strikeRange' => $strikeRange,
            'delta'       => $delta,
            'baseStrikes' => $baseStrikes,
            'strikeStep'  => $strikeStep,
            'tableRows'   => $tableRows,
            'prevDay'     => $prevDay
        ]);
    }
}
