<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use App\Models\DailyTrend;
use App\Models\OhlcQuote;

class TrendController extends Controller
{
    public function index()
    {
        $date = request()->input('date');

        if ($date) {
            $previousDay = $date;
        } else {
            // 1. Working days (previous + current)
            $previousDay = DB::table('nse_working_days')
                             ->where('previous', 1)
                             ->value('working_date');
        }
        if ( ! $previousDay) {
            die('Working days not configured');
        }

        // 2. Precomputed daily trends (static yesterday data)
        $dailyTrends = DailyTrend::whereDate('quote_date', $previousDay)
                                 ->whereIn('symbol_name', ['NIFTY', 'BANKNIFTY', 'SENSEX']) // add FINNIFTY when ready
                                 ->get();

        if ($dailyTrends->isEmpty()) {
            die('Daily trends not populated for previous day');
        }

        return view('trend.index', [
            'previousDay' => $previousDay,
            'dailyTrends' => $dailyTrends,
        ]);
    }

    public function index2()
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
            die('Working days not configured');
        }

        // 2. Precomputed daily trends (static yesterday data)
        $dailyTrends = DailyTrend::whereDate('quote_date', $previousDay)
                                 ->whereIn('symbol_name', ['NIFTY', 'BANKNIFTY', 'SENSEX']) // add FINNIFTY when ready
                                 ->get()
                                 ->keyBy('symbol_name');

        if ($dailyTrends->isEmpty()) {
            die('Daily trends not populated for previous day');
        }

        // 3. Minimal option contracts (one CE + one PE per symbol+strike)
        $optionContracts = [];
        foreach ($dailyTrends as $symbol => $trend) {

            $expiry = DB::table('nse_expiries')
                        ->where('instrument_type', 'OPT')
                        ->where('is_current', 1)
                        ->where('trading_symbol', $symbol)->limit(1)
                        ->value('expiry_date');

            $strike = (int) $trend->strike;

            $optionContracts[] = [
                'trading_symbol' => $symbol,
                'strike_price'   => $strike,
                'side'           => 'CE',
                'expiry_date'    => $expiry,
            ];
            $optionContracts[] = [
                'trading_symbol' => $symbol,
                'strike_price'   => $strike,
                'side'           => 'PE',
                'expiry_date'    => $expiry,
            ];
        }

        // 4. Fetch only needed option LTPs
        $optionLtps = collect();

        if ( ! empty($optionContracts)) {
            $rawOptions = OhlcQuote::query()
                                   ->whereDate('created_at', $currentDay)
                                   ->whereIn('instrument_type', ['CE', 'PE'])
                                   ->where(function ($q) use ($optionContracts) {
                                       foreach ($optionContracts as $c) {
                                           $q->orWhere(function ($sub) use ($c) {
                                               $sub->where('trading_symbol', $c['trading_symbol'])
                                                   ->where('strike_price', $c['strike_price'])
                                                   ->where('instrument_type', $c['side'])
                                                   ->where('expiry_date', $c['expiry_date']);
                                           });
                                       }
                                   })
                                   ->orderBy('created_at')
                                   ->get();

            $optionLtps = $rawOptions
                ->groupBy(function ($row) {
                    return $row->trading_symbol.'_'.(int) $row->strike_price.'_'.$row->instrument_type;
                })
                ->map->last();
        }

        // 5. Current-day index LTPs
        $indexLtps = OhlcQuote::query()
                              ->whereDate('created_at', $currentDay)
                              ->where('instrument_type', 'INDEX')
                              ->whereIn('trading_symbol', ['Nifty 50', 'Nifty Bank', 'BSE SENSEX']) // add 'Nifty Fin Service' later
                              ->orderBy('created_at')
                              ->get()
                              ->groupBy('trading_symbol')
            ->map->last();

        // Mapping for intraday index symbol
        $symbolMap = [
            'NIFTY'     => 'Nifty 50',
            'BANKNIFTY' => 'Nifty Bank',
            'SENSEX'    => 'BSE SENSEX',
            // 'FINNIFTY'  => 'Nifty Fin Service',
        ];

        $rows = [];

        // 6. Build view rows (CE + PE per symbol)
        foreach ($dailyTrends as $symbol => $trend) {
            $strike     = (int) $trend->strike;
            $earthHigh  = $trend->earth_high;
            $earthLow   = $trend->earth_low;
            $earthValue = $trend->earth_value;

            $minR = $trend->min_r;
            $minS = $trend->min_s;
            $maxR = $trend->max_r;
            $maxS = $trend->max_s;

            // joint CE+PE range for "Broken" logic
            $jointMin = min(
                $trend->ce_high,
                $trend->ce_low,
                $trend->ce_close,
                $trend->pe_high,
                $trend->pe_low,
                $trend->pe_close
            );

            $jointMax = max(
                $trend->ce_high,
                $trend->ce_low,
                $trend->ce_close,
                $trend->pe_high,
                $trend->pe_low,
                $trend->pe_close
            );

            // thresholds
            $sat = match ($symbol) {
                'NIFTY', 'FINNIFTY' => 10,
                'BANKNIFTY' => 20,
                'SENSEX' => 30,
                default => 10,
            };

            $indexSat = match ($symbol) {
                'NIFTY', 'FINNIFTY' => 20,
                'BANKNIFTY' => 40,
                'SENSEX' => 50,
                default => 20,
            };

            // Index LTP
            $idxTradingSymbol = $symbolMap[$symbol] ?? null;
            $idxLtpRow        = $idxTradingSymbol ? ($indexLtps[$idxTradingSymbol] ?? null) : null;
            $indexLtp         = $idxLtpRow?->last_price;

            // Option LTPs for CE/PE at this strike
            $baseSymbol = $symbol;

            $ceKey     = $baseSymbol.'_'.$strike.'_CE';
            $ceLtpRow  = $optionLtps[$ceKey] ?? null;
            $pairCeLtp = $ceLtpRow?->last_price;

            $peKey     = $baseSymbol.'_'.$strike.'_PE';
            $peLtpRow  = $optionLtps[$peKey] ?? null;
            $pairPeLtp = $peLtpRow?->last_price;

            foreach (['CE', 'PE'] as $side) {
                $isCe = $side === 'CE';

                $high  = $isCe ? $trend->ce_high : $trend->pe_high;
                $low   = $isCe ? $trend->ce_low : $trend->pe_low;
                $close = $isCe ? $trend->ce_close : $trend->pe_close;

                $optionLtp = $isCe ? $pairCeLtp : $pairPeLtp;

                // Use precomputed type from daily_trend
                $type = $isCe ? $trend->ce_type : $trend->pe_type;

                // Map type to Tailwind color
                $typeColor = match (true) {
                    str_starts_with($type, 'Profit') => 'bg-green-100 text-green-800',
                    str_starts_with($type, 'Panic') => 'bg-red-100 text-red-800',
                    default => 'bg-yellow-100 text-yellow-800',
                };

                $highCloseDiff = max(0, $high - $close);
                $closeLowDiff  = max(0, $close - $low);

                // CE/PE near flags (dots)
                $ceNearHigh  = false;
                $ceNearLow   = false;
                $ceNearClose = false;
                $peNearHigh  = false;
                $peNearLow   = false;
                $peNearClose = false;

                if ( ! is_null($pairCeLtp) || ! is_null($pairPeLtp)) {
                    $levels = [
                        'high'  => $high,
                        'low'   => $low,
                        'close' => $close,
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

                // Index vs R/S & Earth dots
                $idxMinRNear = false;
                $idxMinSNear = false;
                $idxMaxRNear = false;
                $idxMaxSNear = false;
                $idxEHNear   = false;
                $idxELNear   = false;

                if ( ! is_null($indexLtp)) {
                    $idxMinRNear = abs($indexLtp - $minR) <= $indexSat;
                    $idxMinSNear = abs($indexLtp - $minS) <= $indexSat;
                    $idxMaxRNear = abs($indexLtp - $maxR) <= $indexSat;
                    $idxMaxSNear = abs($indexLtp - $maxS) <= $indexSat;

                    if ( ! is_null($earthHigh)) {
                        $idxEHNear = abs($indexLtp - $earthHigh) <= $indexSat;
                    }
                    if ( ! is_null($earthLow)) {
                        $idxELNear = abs($indexLtp - $earthLow) <= $indexSat;
                    }
                }

                // Broken status
                $broken      = null;
                $brokenColor = null;

                if ( ! is_null($optionLtp)) {
                    if ($optionLtp < $jointMin) {
                        $broken      = 'Down';
                        $brokenColor = 'bg-red-100 text-red-800';
                    } elseif ($optionLtp > $jointMax) {
                        $broken      = 'Up';
                        $brokenColor = 'bg-green-100 text-green-800';
                    }
                }

                $rows[] = [
                    'symbol'      => $symbol,
                    'strike'      => $strike,
                    'option_type' => $side,

                    'high'            => $high,
                    'low'             => $low,
                    'close'           => $close,
                    'index_close'     => $trend->index_close,
                    'index_open'      => $trend->current_day_index_open,
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

                    'broken'       => $broken,
                    'broken_color' => $brokenColor,

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
