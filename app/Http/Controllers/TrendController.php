<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use App\Models\DailyOhlcQuote;
use App\Models\OhlcDayQuote;
use App\Models\OhlcQuote;

class TrendController extends Controller
{
    public function index()
    {
        // 1. Working days (previous + current)
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

        // 2. Yesterday index OHLC from daily_ohlc_quotes
        $yesterdayIndexes = DailyOhlcQuote::where('option_type', 'INDEX')
                                          ->where('quote_date', $previousDay)
                                          ->whereIn('symbol_name', ['NIFTY','BANKNIFTY','SENSEX','FINNIFTY'])
                                          ->get()
                                          ->keyBy('symbol_name');

        // 3. Current-day index OPEN for Earth levels
        $currentIndexOpens = OhlcDayQuote::query()
                                         ->where('instrument_type', 'INDEX')
                                         ->whereDate('created_at', $currentDay)
                                         ->whereIn('trading_symbol', ['Nifty 50','Nifty Bank','BSE SENSEX','Nifty Fin Service'])
                                         ->orderBy('created_at')
                                         ->get()
                                         ->groupBy('trading_symbol')
            ->map->first();

        // 4. Current option expiries (OPT) per symbol
        $currentOptExpiries = DB::table('expiries')
                                ->where('is_current', 1)
                                ->where('instrument_type', 'OPT')
                                ->whereIn('trading_symbol', ['NIFTY','BANKNIFTY','FINNIFTY','SENSEX'])
                                ->get()
                                ->keyBy('trading_symbol');

        $symbolExpiryMap = $currentOptExpiries->mapWithKeys(function ($row) {
            return [$row->trading_symbol => $row->expiry_date];
        });

        // 5. Current-day option LTPs from ohlc_quotes (CE/PE), filtered to current expiry
        $optionLtps = collect();
        if ($symbolExpiryMap->isNotEmpty()) {
            $optionLtps = OhlcQuote::query()
                                   ->whereDate('created_at', $currentDay)
                                   ->whereIn('instrument_type', ['CE','PE'])
                                   ->whereIn('trading_symbol', $symbolExpiryMap->keys())
                                   ->whereIn('expiry_date', $symbolExpiryMap->values())
                                   ->get()
                                   ->groupBy(function ($row) {
                                       // key per underlying+strike+side; here trading_symbol is index name
                                       return $row->trading_symbol . '_' . (int) $row->strike_price . '_' . $row->instrument_type;
                                   })
                ->map->last();
        }

        // 6. Current-day index LTPs from ohlc_quotes
        $indexLtps = OhlcQuote::query()
                              ->whereDate('created_at', $currentDay)
                              ->where('instrument_type', 'INDEX')
                              ->whereIn('trading_symbol', ['Nifty 50','Nifty Bank','Nifty Fin Service','BSE SENSEX'])
                              ->orderBy('created_at')
                              ->get()
                              ->groupBy('trading_symbol')
            ->map->last();

        // Map daily_ohlc_quotes.symbol_name -> intraday/trading_symbol
        $symbolMap = [
            'NIFTY'     => 'Nifty 50',
            'BANKNIFTY' => 'Nifty Bank',
            'SENSEX'    => 'BSE SENSEX',
            'FINNIFTY'  => 'Nifty Fin Service',
        ];

        $rows = [];

        foreach ($yesterdayIndexes as $symbol => $indexRow) {

            // ---- Earth levels (26.11% of yesterday range) ----
            $highLowDiff = $indexRow->high - $indexRow->low;
            $earthValue  = $highLowDiff * 0.2611;

            $earthHigh = null;
            $earthLow  = null;

            $earthTradingSymbol = $symbolMap[$symbol] ?? null;
            $openRow = $earthTradingSymbol
                ? ($currentIndexOpens[$earthTradingSymbol] ?? null)
                : null;

            if ($openRow) {
                $open      = $openRow->open;
                $earthHigh = $open + $earthValue;
                $earthLow  = $open - $earthValue;
            }

            // ---- current expiry selection for yesterday OHLC (DailyOhlcQuote) ----
            $currentExpiry = DailyOhlcQuote::where('quote_date', $previousDay)
                                           ->where('symbol_name', $symbol)
                                           ->whereIn('option_type', ['CE','PE'])
                                           ->orderBy('expiry_date')
                                           ->value('expiry_date');

            $optionQuery = DailyOhlcQuote::where('quote_date', $previousDay)
                                         ->where('symbol_name', $symbol)
                                         ->whereIn('option_type', ['CE','PE']);

            if ($currentExpiry) {
                $optionQuery->where('expiry_date', $currentExpiry);
            }

            $options = $optionQuery->get();
            if ($options->isEmpty()) {
                continue;
            }

            // ---- best CE/PE pair per strike ----
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

            // ---- Min/Max R/S ----
            $minR = $strike + $ceClose;
            $minS = $strike - $peClose;
            $maxR = $strike + $sumClose;
            $maxS = $strike - $sumClose;

            // saturation thresholds for LTP matching
            $sat = match ($symbol) {
                'NIFTY','FINNIFTY' => 5,
                'BANKNIFTY'        => 10,
                'SENSEX'           => 20,
                default            => 5,
            };

            // Side thresholds (for type logic)
            $sideThreshold = match ($symbol) {
                'NIFTY','FINNIFTY' => 30,
                'BANKNIFTY'        => 60,
                'SENSEX'           => 90,
                default            => 30,
            };

            // index LTP for this symbol
            $idxTradingSymbol = $earthTradingSymbol;
            $idxLtpRow = $idxTradingSymbol ? ($indexLtps[$idxTradingSymbol] ?? null) : null;
            $indexLtp  = $idxLtpRow?->last_price;

            foreach (['ce','pe'] as $side) {
                $contract = $bestPair[$side];

                // option LTP key: intraday ohlc_quotes trading_symbol is same as index trading_symbol
                $optTradingSymbol = $idxTradingSymbol;
                $optKey = $optTradingSymbol . '_' . (int) $strike . '_' . $contract->instrument_type ?? $contract->option_type;

                // your grouping above used instrument_type (CE/PE), but DailyOhlcQuote has option_type;
                // to be safe, use instrument_type from ohlc_quotes key, but fallback to option_type text.
                if (! isset($optionLtps[$optKey])) {
                    $optKey = $symbol . '_' . (int) $strike . '_' . $contract->option_type;
                }

                $optLtpRow = $optionLtps[$optKey] ?? null;
                $optionLtp = $optLtpRow?->last_price;

                // ---- Type logic (Profit/Panic/Side) ----
                $highCloseDiff = max(0, $contract->high - $contract->close);
                $closeLowDiff  = max(0, $contract->close - $contract->low);

                if ($highCloseDiff > $closeLowDiff) {
                    $type = 'Profit';
                    $typeColor = 'bg-green-100 text-green-800';
                } elseif ($highCloseDiff < $closeLowDiff) {
                    $type = 'Panic';
                    $typeColor = 'bg-red-100 text-red-800';
                } else {
                    $type = 'Side';
                    $typeColor = 'bg-yellow-100 text-yellow-800';
                }

                $minDiff = min($highCloseDiff, $closeLowDiff);
                if ($minDiff > $sideThreshold) {
                    if ($type === 'Side') {
                        $type = 'Side';
                        $typeColor = 'bg-yellow-100 text-yellow-800';
                    } else {
                        $type .= ' Side';
                        $typeColor = 'bg-yellow-100 text-yellow-800';
                    }
                }

                // ---- CE/PE near yesterday OHLC (field-level flags) ----
                $ceNearHigh = $ceNearLow = $ceNearClose = false;
                $peNearHigh = $peNearLow = $peNearClose = false;

                if (!is_null($optionLtp)) {
                    if ($contract->option_type === 'CE') {
                        $ceNearHigh  = abs($optionLtp - $contract->high)  <= $sat;
                        $ceNearLow   = abs($optionLtp - $contract->low)   <= $sat;
                        $ceNearClose = abs($optionLtp - $contract->close) <= $sat;
                    } elseif ($contract->option_type === 'PE') {
                        $peNearHigh  = abs($optionLtp - $contract->high)  <= $sat;
                        $peNearLow   = abs($optionLtp - $contract->low)   <= $sat;
                        $peNearClose = abs($optionLtp - $contract->close) <= $sat;
                    }
                }

                // ---- index LTP vs R/S & Earth (orange) ----
                $idxMinRNear = $idxMinSNear = $idxMaxRNear = $idxMaxSNear = false;
                $idxEHNear   = $idxELNear   = false;

                if (!is_null($indexLtp)) {
                    $idxMinRNear = abs($indexLtp - $minR)      <= $sat;
                    $idxMinSNear = abs($indexLtp - $minS)      <= $sat;
                    $idxMaxRNear = abs($indexLtp - $maxR)      <= $sat;
                    $idxMaxSNear = abs($indexLtp - $maxS)      <= $sat;
                    if (!is_null($earthHigh)) {
                        $idxEHNear = abs($indexLtp - $earthHigh) <= $sat;
                    }
                    if (!is_null($earthLow)) {
                        $idxELNear = abs($indexLtp - $earthLow)  <= $sat;
                    }
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

                    'min_r'       => $minR,
                    'min_s'       => $minS,
                    'max_r'       => $maxR,
                    'max_s'       => $maxS,
                    'earth_value' => $earthValue,
                    'earth_high'  => $earthHigh,
                    'earth_low'   => $earthLow,

                    'option_ltp'  => $optionLtp,
                    'index_ltp'   => $indexLtp,

                    // CE/PE field-level flags
                    'ce_near_high'   => $ceNearHigh,
                    'ce_near_low'    => $ceNearLow,
                    'ce_near_close'  => $ceNearClose,
                    'pe_near_high'   => $peNearHigh,
                    'pe_near_low'    => $peNearLow,
                    'pe_near_close'  => $peNearClose,

                    // index vs R/S & Earth
                    'idx_minr_near' => $idxMinRNear,
                    'idx_mins_near' => $idxMinSNear,
                    'idx_maxr_near' => $idxMaxRNear,
                    'idx_maxs_near' => $idxMaxSNear,
                    'idx_eh_near'   => $idxEHNear,
                    'idx_el_near'   => $idxELNear,
                ];
            }
        }

        return view('trend.index', [
            'previousDay' => $previousDay,
            'rows'        => $rows,
        ]);
    }
}
