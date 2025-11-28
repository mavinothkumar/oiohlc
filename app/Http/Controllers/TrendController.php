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

        if ( ! $previousDay || ! $currentDay) {
            abort(404, 'Working days not configured');
        }

        // 2. Yesterday index OHLC from daily_ohlc_quotes
        $yesterdayIndexes = DailyOhlcQuote::where('option_type', 'INDEX')
                                          ->where('quote_date', $previousDay)
                                          ->whereIn('symbol_name', ['NIFTY', 'BANKNIFTY', 'SENSEX', 'FINNIFTY'])
                                          ->get()
                                          ->keyBy('symbol_name');

        // 3. Current-day index OPEN for Earth levels
        $currentIndexOpens = OhlcDayQuote::query()
                                         ->where('instrument_type', 'INDEX')
                                         ->whereDate('created_at', $currentDay)
                                         ->whereIn('trading_symbol', ['Nifty 50', 'Nifty Bank', 'BSE SENSEX', 'Nifty Fin Service'])
                                         ->orderBy('created_at')
                                         ->get()
                                         ->groupBy('trading_symbol')
            ->map->first();

        // 4. Current option expiries (OPT) per symbol
        $currentOptExpiries = DB::table('expiries')
                                ->where('is_current', 1)
                                ->where('instrument_type', 'OPT')
                                ->whereIn('trading_symbol', ['NIFTY', 'BANKNIFTY', 'FINNIFTY', 'SENSEX'])
                                ->get()
                                ->keyBy('trading_symbol');

        // symbol => expiry_date (Y-m-d)
        $symbolExpiryMap = $currentOptExpiries->mapWithKeys(function ($row) {
            return [$row->trading_symbol => $row->expiry_date];
        });

        // 5. Current-day option LTPs from ohlc_quotes (CE/PE), strict on (symbol + expiry)
        $optionLtps = collect();
        if ($symbolExpiryMap->isNotEmpty()) {
            $rawOptions = OhlcQuote::query()
                                   ->whereDate('created_at', $currentDay)
                                   ->whereIn('instrument_type', ['CE', 'PE'])
                                   ->get();

            // keep only rows where base symbol + expiry match current OPT expiry
            $filtered = $rawOptions->filter(function ($row) use ($symbolExpiryMap) {
                // base symbol in ohlc_quotes must match expiries.trading_symbol (NIFTY/BANKNIFTY/FINNIFTY/SENSEX)
                $base     = $row->trading_symbol;   // adjust here if you later store full option symbol
                $expected = $symbolExpiryMap[$base] ?? null;
                if ( ! $expected) {
                    return false;
                }

                $rowDate      = substr((string) $row->expiry_date, 0, 10);
                $expectedDate = substr((string) $expected, 0, 10);

                return $rowDate === $expectedDate;
            });

            $optionLtps = $filtered
                ->groupBy(function ($row) {
                    // key: BASE_SYMBOL_STRIKE_SIDE   e.g. NIFTY_26250_CE
                    return $row->trading_symbol.'_'.(int) $row->strike_price.'_'.$row->instrument_type;
                })
                ->map->last();
        }

        // 6. Current-day index LTPs from ohlc_quotes
        $indexLtps = OhlcQuote::query()
                              ->whereDate('created_at', $currentDay)
                              ->where('instrument_type', 'INDEX')
                              ->whereIn('trading_symbol', ['Nifty 50', 'Nifty Bank', 'Nifty Fin Service', 'BSE SENSEX'])
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
            $openRow            = $earthTradingSymbol
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

            // ---- best CE/PE pair per strike ----
            $groupedByStrike = $options->groupBy('strike');

            $bestPair = null;
            $bestDiff = null;

            foreach ($groupedByStrike as $strike => $contracts) {
                $ce = $contracts->firstWhere('option_type', 'CE');
                $pe = $contracts->firstWhere('option_type', 'PE');

                if ( ! $ce || ! $pe) {
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

            if ( ! $bestPair) {
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
                'NIFTY', 'FINNIFTY' => 15,
                'BANKNIFTY' => 20,
                'SENSEX' => 30,
                default => 10,
            };

            $index_sat = match ($symbol) {
                'NIFTY', 'FINNIFTY' => 20,
                'BANKNIFTY' => 40,
                'SENSEX' => 50,
                default => 20,
            };

// Side thresholds (for type logic)
            $sideThreshold = match ($symbol) {
                'NIFTY', 'FINNIFTY' => 30,
                'BANKNIFTY' => 60,
                'SENSEX' => 90,
                default => 30,
            };

// index LTP for this symbol
            $idxTradingSymbol = $earthTradingSymbol;
            $idxLtpRow        = $idxTradingSymbol ? ($indexLtps[$idxTradingSymbol] ?? null) : null;
            $indexLtp         = $idxLtpRow?->last_price;

// pair-level option LTPs for this symbol+strike
            $pairCeLtp = null;
            $pairPeLtp = null;

            $baseSymbol = $symbol; // NIFTY/BANKNIFTY/FINNIFTY/SENSEX

            $ceKey     = $baseSymbol.'_'.(int) $strike.'_CE';
            $ceLtpRow  = $optionLtps[$ceKey] ?? null;
            $pairCeLtp = $ceLtpRow?->last_price;

            $peKey     = $baseSymbol.'_'.(int) $strike.'_PE';
            $peLtpRow  = $optionLtps[$peKey] ?? null;
            $pairPeLtp = $peLtpRow?->last_price;

            foreach (['ce', 'pe'] as $side) {
                $contract = $bestPair[$side];

                // ---- Type logic (Profit / Panic / Side) ----
                $highCloseDiff = max(0, $contract->high - $contract->close);
                $closeLowDiff  = max(0, $contract->close - $contract->low);

                if ($highCloseDiff > $closeLowDiff) {
                    $type      = 'Profit';
                    $typeColor = 'bg-green-100 text-green-800';
                } elseif ($highCloseDiff < $closeLowDiff) {
                    $type      = 'Panic';
                    $typeColor = 'bg-red-100 text-red-800';
                } else {
                    $type      = 'Side';
                    $typeColor = 'bg-yellow-100 text-yellow-800';
                }

                $minDiff = min($highCloseDiff, $closeLowDiff);
                if ($minDiff > $sideThreshold) {
                    if ($type === 'Side') {
                        $type      = 'Side';
                        $typeColor = 'bg-yellow-100 text-yellow-800';
                    } else {
                        $type      .= ' Side';
                        $typeColor = 'bg-yellow-100 text-yellow-800';
                    }
                }

                // LTP shown in OPT LTP column = that side's LTP
                $optionLtp = $contract->option_type === 'CE' ? $pairCeLtp : $pairPeLtp;

                // --------------------------------------------
                // CE & PE cross‑comparison for dots
                // At each of this row's HIGH/LOW/CLOSE, whichever
                // LTP (CE or PE) is closer within $sat wins:
                // CE -> red flag, PE -> green flag.
                // --------------------------------------------
                $ceNearHigh = $ceNearLow = $ceNearClose = false;
                $peNearHigh = $peNearLow = $peNearClose = false;

                if ( ! is_null($pairCeLtp) || ! is_null($pairPeLtp)) {
                    $levels = [
                        'high'  => $contract->high,
                        'low'   => $contract->low,
                        'close' => $contract->close,
                    ];

                    foreach ($levels as $key => $price) {
                        $bestSource = null; // 'CE' or 'PE'
                        $bestDiff   = null;

                        if ( ! is_null($pairCeLtp)) {
                            $bestSource = 'CE';
                            $bestDiff   = abs($pairCeLtp - $price);
                        }

                        if ( ! is_null($pairPeLtp)) {
                            $peDiff = abs($pairPeLtp - $price);
                            if (is_null($bestDiff) || $peDiff < $bestDiff) {
                                $bestDiff   = $peDiff;
                                $bestSource = 'PE';
                            }
                        }

                        if ( ! is_null($bestDiff) && $bestDiff <= $sat) {
                            if ($bestSource === 'CE') {
                                if ($key === 'high') {
                                    $ceNearHigh = true;
                                }
                                if ($key === 'low') {
                                    $ceNearLow = true;
                                }
                                if ($key === 'close') {
                                    $ceNearClose = true;
                                }
                            } else {
                                if ($key === 'high') {
                                    $peNearHigh = true;
                                }
                                if ($key === 'low') {
                                    $peNearLow = true;
                                }
                                if ($key === 'close') {
                                    $peNearClose = true;
                                }
                            }
                        }
                    }
                }

                // ---- index LTP vs R/S & Earth (orange) ----
                $idxMinRNear = $idxMinSNear = $idxMaxRNear = $idxMaxSNear = false;
                $idxEHNear   = $idxELNear = false;

                if ( ! is_null($indexLtp)) {
                    $idxMinRNear = abs($indexLtp - $minR) <= $index_sat;
                    $idxMinSNear = abs($indexLtp - $minS) <= $index_sat;
                    $idxMaxRNear = abs($indexLtp - $maxR) <= $index_sat;
                    $idxMaxSNear = abs($indexLtp - $maxS) <= $index_sat;
                    if ( ! is_null($earthHigh)) {
                        $idxEHNear = abs($indexLtp - $earthHigh) <= $index_sat;
                    }
                    if ( ! is_null($earthLow)) {
                        $idxELNear = abs($indexLtp - $earthLow) <= $index_sat;
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

                    'option_ltp' => $optionLtp,
                    'index_ltp'  => $indexLtp,

                    'ce_near_high'  => $ceNearHigh,
                    'ce_near_low'   => $ceNearLow,
                    'ce_near_close' => $ceNearClose,
                    'pe_near_high'  => $peNearHigh,
                    'pe_near_low'   => $peNearLow,
                    'pe_near_close' => $peNearClose,

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
