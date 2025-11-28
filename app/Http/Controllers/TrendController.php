<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\DailyOhlcQuote;
use App\Models\OhlcDayQuote;

class TrendController extends Controller
{
    public function index()
    {
        // 1. Previous & current working days (DB::table as you noted)
        $days = DB::table('nse_working_days')
                  ->where(function ($q) {
                      $q->where('previous', 1)
                        ->orWhere('current', 1);
                  })
                  ->orderByDesc('id')
                  ->get();

        $previousDay = optional($days->firstWhere('previous', 1))->working_date;
        $currentDay  = optional($days->firstWhere('current', 1))->working_date;

        if (! $previousDay || ! $currentDay) {
            abort(404, 'Working days not configured');
        }

        // 2. Yesterday INDEX OHLC (for NIFTY, BANKNIFTY, SENSEX, FINNIFTY)
        $yesterdayIndexes = DailyOhlcQuote::where('option_type', 'INDEX')
                                          ->where('quote_date', $previousDay)
                                          ->whereIn('symbol_name', ['NIFTY', 'BANKNIFTY', 'SENSEX', 'FINNIFTY'])
                                          ->get()
                                          ->keyBy('symbol_name');

        // 3. Current day INDEX open from ohlc_day_quotes
        $currentIndexOpens = OhlcDayQuote::query()
                                         ->where('instrument_type', 'INDEX')
                                         ->whereDate('created_at', $currentDay)
                                         ->whereIn('trading_symbol', ['Nifty 50', 'Nifty Bank', 'BSE SENSEX', 'Nifty Fin Service'])
                                         ->orderBy('created_at')
                                         ->get()
                                         ->groupBy('trading_symbol')
            ->map->first();

        // Map INDEX symbol_name (in daily_ohlc_quotes) to trading_symbol (in ohlc_day_quotes)
        $symbolMap = [
            'NIFTY'     => 'Nifty 50',
            'BANKNIFTY' => 'Nifty Bank',
            'SENSEX'    => 'BSE SENSEX',
            'FINNIFTY'  => 'Nifty Fin Service',
        ];

        $rows = [];

        foreach ($yesterdayIndexes as $symbol => $indexRow) {

            // ---- Earth value (26.11% of yesterday high-low) ----
            $highLowDiff = $indexRow->high - $indexRow->low;
            $earthValue  = $highLowDiff * 0.2611;   // 26.11%

            $earthHigh = null;
            $earthLow  = null;

            $tradingSymbol = $symbolMap[$symbol] ?? null;
            $openRow = $tradingSymbol
                ? ($currentIndexOpens[$tradingSymbol] ?? null)
                : null;

            if ($openRow) {
                $open = $openRow->open;
                $earthHigh = $open + $earthValue;  // E-H
                $earthLow  = $open - $earthValue;  // E-L
            }

            // ---- Find current expiry for this symbol (if any) ----
            $currentExpiry = DailyOhlcQuote::where('quote_date', $previousDay)
                                           ->where('symbol_name', $symbol)
                                           ->whereIn('option_type', ['CE', 'PE'])
                                           ->orderBy('expiry_date')
                                           ->value('expiry_date');

            $optionQuery = DailyOhlcQuote::where('quote_date', $previousDay)
                                         ->where('symbol_name', $symbol)
                                         ->whereIn('option_type', ['CE', 'PE']);

            if ($currentExpiry) {
                $optionQuery->where('expiry_date', $currentExpiry);
            }

            $options = $optionQuery->get();
            if ($options->isEmpty()) {
                continue;
            }

            // ---- best CE/PE pair (min |CE close - PE close|) per strike ----
            $groupedByStrike = $options->groupBy('strike');

            $bestPair = null;
            $bestDiff = null;

            foreach ($groupedByStrike as $strike => $contracts) {
                $ce = $contracts->firstWhere('option_type', 'CE');
                $pe = $contracts->firstWhere('option_type', 'PE');

                if (! $ce || ! $pe) {
                    continue;
                }

                $diff = abs($ce->close - $pe->close);

                if ($bestDiff === null || $diff < $bestDiff) {
                    $bestDiff = $diff;
                    $bestPair = [
                        'strike' => $strike,
                        'ce'     => $ce,
                        'pe'     => $pe,
                    ];
                }
            }

            if (! $bestPair) {
                continue;
            }

            $strike   = $bestPair['strike'];
            $ceClose  = $bestPair['ce']->close;
            $peClose  = $bestPair['pe']->close;
            $sumClose = $ceClose + $peClose;

            // ---- symbol+strike level R/S ----
            $minR = $strike + $ceClose;
            $minS = $strike - $peClose;
            $maxR = $strike + $sumClose;
            $maxS = $strike - $sumClose;

            // ---- push CE & PE rows ----
            foreach (['ce', 'pe'] as $side) {
                $contract = $bestPair[$side];

                $highCloseDiff = max(0, $contract->high - $contract->close);
                $closeLowDiff  = max(0, $contract->close - $contract->low);

                $type = 'Side';
                $typeColor = 'bg-yellow-100 text-yellow-800';

                if ($highCloseDiff < $closeLowDiff) {
                    $type = 'Panic';
                    $typeColor = 'bg-red-100 text-red-800';
                } elseif ($closeLowDiff < $highCloseDiff) {
                    $type = 'Profit';
                    $typeColor = 'bg-green-100 text-green-800';
                }

                if ($highCloseDiff > 30 || $closeLowDiff > 30) {
                    $type = 'Side';
                    $typeColor = 'bg-yellow-100 text-yellow-800';
                }

                $rows[] = [
                    'symbol'          => $symbol,
                    'strike'          => $strike,
                    'option_type'     => $contract->option_type,
                    'high'            => $contract->high,
                    'low'             => $contract->low,
                    'close'           => $contract->close,
                    'high_close_diff' => $highCloseDiff,
                    'close_low_diff'  => $closeLowDiff,
                    'type'            => $type,
                    'type_color'      => $typeColor,

                    // symbol+strike level
                    'min_r'       => $minR,
                    'min_s'       => $minS,
                    'max_r'       => $maxR,
                    'max_s'       => $maxS,
                    'earth_value' => $earthValue,
                    'earth_high'  => $earthHigh,
                    'earth_low'   => $earthLow,
                ];
            }
        }

        return view('trend.index', [
            'previousDay' => $previousDay,
            'rows'        => $rows,
        ]);
    }
}
