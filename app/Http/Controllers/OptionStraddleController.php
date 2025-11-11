<?php

namespace App\Http\Controllers;

use App\Models\OptionChain3M;
use App\Models\DailyOhlcQuote;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class OptionStraddleController extends Controller
{
    public function show(Request $request)
    {
        $index = $request->input('index', 'NIFTY');
        $strikeDiff = $request->input('strikeDiff', 100);
        $atmOverride = $request->input('atm_strike');
        $proximity = $request->input('proximity', 5);

        // 2. Get previous trading day
        $prevDay = DB::table('nse_working_days')
                     ->where('previous', 1)
                     ->orderByDesc('working_date')
                     ->first();
        $prevDate = $prevDay ? $prevDay->working_date : now()->subDay()->toDateString();
        $today = Carbon::now();

        // 3. Get ATM (from option_chain_3m or override)
        $atmRecord = OptionChain3M::where('trading_symbol', $index)
                                  ->whereDate('captured_at', $prevDate)
                                  ->orderByDesc('captured_at')
                                  ->first();
        $atmStrike = $atmOverride ?? (
        $atmRecord ? (round($atmRecord->underlying_spot_price / $strikeDiff) * $strikeDiff) : 0
        );

        // 4. Get 3min records for ATM
        $atm_ce = OptionChain3M::where([
            ['trading_symbol', $index],
            ['strike_price', $atmStrike],
            ['option_type', 'CE'],
        ])->whereDate('captured_at', $today)
                               ->orderBy('captured_at', 'desc')->get();

        $atm_pe = OptionChain3M::where([
            ['trading_symbol', $index],
            ['strike_price', $atmStrike],
            ['option_type', 'PE'],
        ])->whereDate('captured_at', $today)
                               ->orderBy('captured_at', 'desc')->get();

        // 5. Get OTM strikes
        $peStrike = $atmStrike - $strikeDiff;
        $ceStrike = $atmStrike + $strikeDiff;

        $otm_pe_ohlc = DailyOhlcQuote::where([
            ['symbol_name', $index],
            ['strike', $peStrike],
            ['option_type', 'PE'],
            ['quote_date', $prevDate],
        ])->first();

        $otm_ce_ohlc = DailyOhlcQuote::where([
            ['symbol_name', $index],
            ['strike', $ceStrike],
            ['option_type', 'CE'],
            ['quote_date', $prevDate],
        ])->first();

        // 6. Prepare top summary row (16 combinations)
        $summaryRows = [];
        if ($otm_ce_ohlc && $otm_pe_ohlc) {
            $summaryRows = [
                'Open+Open'   => ($otm_ce_ohlc->open + $otm_pe_ohlc->open) / 2,
                'Open+Close'  => ($otm_ce_ohlc->open + $otm_pe_ohlc->close) / 2,
                'Open+High'   => ($otm_ce_ohlc->open + $otm_pe_ohlc->high) / 2,
                'Open+Low'    => ($otm_ce_ohlc->open + $otm_pe_ohlc->low) / 2,
                'Close+Open'  => ($otm_ce_ohlc->close + $otm_pe_ohlc->open) / 2,
                'Close+Close' => ($otm_ce_ohlc->close + $otm_pe_ohlc->close) / 2,
                'Close+High'  => ($otm_ce_ohlc->close + $otm_pe_ohlc->high) / 2,
                'Close+Low'   => ($otm_ce_ohlc->close + $otm_pe_ohlc->low) / 2,
                'High+Open'   => ($otm_ce_ohlc->high + $otm_pe_ohlc->open) / 2,
                'High+Close'  => ($otm_ce_ohlc->high + $otm_pe_ohlc->close) / 2,
                'High+High'   => ($otm_ce_ohlc->high + $otm_pe_ohlc->high) / 2,
                'High+Low'    => ($otm_ce_ohlc->high + $otm_pe_ohlc->low) / 2,
                'Low+Open'    => ($otm_ce_ohlc->low + $otm_pe_ohlc->open) / 2,
                'Low+Close'   => ($otm_ce_ohlc->low + $otm_pe_ohlc->close) / 2,
                'Low+High'    => ($otm_ce_ohlc->low + $otm_pe_ohlc->high) / 2,
                'Low+Low'     => ($otm_ce_ohlc->low + $otm_pe_ohlc->low) / 2
            ];
        }

        // 7. Prepare all rows for the view
        $results = [];
        foreach ($atm_ce as $i => $ce) {
            $pe = $atm_pe[$i] ?? null;
            $results[] = [
                'captured_at'     => $ce->captured_at,
                'atm_ce_ltp'      => $ce->ltp,
                'atm_pe_ltp'      => $pe ? $pe->ltp : null,
            ];
        }

        foreach ($results as $idx => $row) {
            $ce_hits = [];
            $pe_hits = [];
            foreach($summaryRows as $label => $value) {
                if ($row['atm_ce_ltp'] !== null && abs($row['atm_ce_ltp'] - $value) <= $proximity) {
                    $ce_hits[] = $label;
                }
                if ($row['atm_pe_ltp'] !== null && abs($row['atm_pe_ltp'] - $value) <= $proximity) {
                    $pe_hits[] = $label;
                }
            }
            $results[$idx]['atm_ce_hits'] = $ce_hits; // Array of labels
            $results[$idx]['atm_pe_hits'] = $pe_hits;
        }

        return view('options.straddle', [
            'summaryRows'  => $summaryRows,
            'results'      => $results,
            'prevDate'     => $prevDate,
            'atmStrike'    => $atmStrike,
            'peStrike'     => $peStrike,
            'ceStrike'     => $ceStrike,
            'strikeDiff'   => $strikeDiff,
            'atmOverride'  => $atmOverride,
            'index'        => $index,
            'otm_pe_ohlc'  => $otm_pe_ohlc,
            'otm_ce_ohlc'  => $otm_ce_ohlc,
            'proximity' => $proximity,
        ]);
    }
}
