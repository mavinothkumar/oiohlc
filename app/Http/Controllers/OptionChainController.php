<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class OptionChainController extends Controller
{
    public function index(Request $request)
    {
        /* -------------------------------------------------
         |  Basic request inputs / defaults
         * ------------------------------------------------ */
        $symbol = strtoupper($request->input('symbol', 'NIFTY'));
        $range  = (int) $request->input('range', 300);   // ± points around underlying
        $limit  = (int) $request->input('limit', 10);    // leaders shown per type
        $date   = $request->input('date', now()->toDateString());
        $expiry = $request->input('expiry_date');

        /* current-series expiry if none provided */
        if (! $expiry) {
            $expiry = DB::table('expiries')
                        ->where('trading_symbol', $symbol)
                        ->where('is_current', 1)
                        ->where('instrument_type', 'OPT')
                        ->value('expiry_date');
        }

        /* latest underlying strike for the trading day */
        $underlying = DB::table('option_chains_3m')
                        ->where('trading_symbol', $symbol)
                        ->where('expiry', $expiry)
                        ->whereDate('captured_at', $date)
                        ->orderByDesc('captured_at')
                        ->value('underlying_spot_price');



        /* strike range (rounded to 50-pt buckets) */
        $minStrike = floor(($underlying - $range) / 50) * 50;
        $maxStrike = ceil(($underlying + $range) / 50) * 50;

        if (! $underlying) {
            return view('option-chain-table', [
                'symbol'      => $symbol,
                'expiry'      => $expiry,
                'date'        => $date,
                'underlying'  => $underlying,
                'range'       => [$minStrike, $maxStrike],
            ]);
        }

        /* -------------------------------------------------
         |  Build-type constants & skip-times
         * ------------------------------------------------ */
        $buildTypes = ['Long Build', 'Short Build', 'Long Unwind', 'Short Cover'];

        /*  pre-open snapshots we don’t count in “top-N” */
        $skipTimes  = ['09:18:00', '09:21:00', '09:24:00', '09:27:00'];
        $timeList   = '\'' . implode("','", $skipTimes) . '\'';   // SQL safe

        /* containers */
        $buildupsOi  = [];
        $buildupsVol = [];
        $timeline    = [];

        /* helper to generate a unique key:  HH:MM|strike|CE|PE */
        $makeKey = static fn($row) =>
            Carbon::parse($row->captured_at)->format('H:i') . '|' .
            (int) $row->strike_price . '|' .
            $row->option_type;

        /* helper → largest row among skipped stamps (pre-open) */
        $largestSkipped = function ($col, $type) use (
            $symbol, $expiry, $minStrike, $maxStrike, $date, $timeList
        ) {
            return DB::table('option_chains_3m')
                     ->select('captured_at','strike_price','option_type','diff_oi','diff_volume')
                     ->where('trading_symbol',  $symbol)
                     ->where('expiry', $expiry)
                     ->whereBetween('strike_price', [$minStrike, $maxStrike])
                     ->where('build_up', $type)
                     ->whereDate('captured_at', $date)
                     ->whereRaw("TIME(captured_at) IN ($timeList)")
                     ->orderByDesc(DB::raw("ABS($col)"))
                     ->first();
        };

        /* -------------------------------------------------
         |  Main build-type loop
         * ------------------------------------------------ */
        foreach ($buildTypes as $type) {

            /* ---------- OI leaders (skip pre-open) ---------- */
            $rowsOiTop = DB::table('option_chains_3m')
                           ->select('captured_at','strike_price','option_type',
                               'diff_oi','diff_ltp','diff_volume')
                           ->where('trading_symbol', $symbol)
                           ->where('expiry', $expiry)
                           ->whereBetween('strike_price', [$minStrike, $maxStrike])
                           ->where('build_up', $type)
                           ->whereDate('captured_at', $date)
                           ->whereRaw("TIME(captured_at) NOT IN ($timeList)")
                           ->orderByDesc(DB::raw('ABS(diff_oi)'))
                           ->limit($limit)
                           ->get()
                           ->map(function ($row, $idx) {   // add rank
                               $row->oi_rank = $idx + 1;
                               return $row;
                           });

            $rowOiSkip = $largestSkipped('diff_oi', $type);
            $rowsOi    = $rowOiSkip ? collect([$rowOiSkip])->merge($rowsOiTop) : $rowsOiTop;

            /* ---------- VOL leaders (skip pre-open) ---------- */
            $rowsVolTop = DB::table('option_chains_3m')
                            ->select('captured_at','strike_price','option_type',
                                'diff_oi','diff_ltp','diff_volume')
                            ->where('trading_symbol', $symbol)
                            ->where('expiry', $expiry)
                            ->whereBetween('strike_price', [$minStrike, $maxStrike])
                            ->where('build_up', $type)
                            ->whereDate('captured_at', $date)
                            ->whereRaw("TIME(captured_at) NOT IN ($timeList)")
                            ->orderByDesc(DB::raw('ABS(diff_volume)'))
                            ->limit($limit)
                            ->get()
                            ->map(function ($row, $idx) {   // add rank
                                $row->vol_rank = $idx + 1;
                                return $row;
                            });

            $rowVolSkip = $largestSkipped('diff_volume', $type);
            $rowsVol    = $rowVolSkip ? collect([$rowVolSkip])->merge($rowsVolTop) : $rowsVolTop;

            /* ---- merge OI + VOL rows on the same key ---- */
            $rowsOiMap  = $rowsOi->keyBy($makeKey);
            $rowsVolMap = $rowsVol->keyBy($makeKey);
            $mergedKeys = array_unique(
                array_merge($rowsOiMap->keys()->all(), $rowsVolMap->keys()->all())
            );

            foreach ($mergedKeys as $key) {
                $rowOi  = $rowsOiMap[$key]  ?? null;
                $rowVol = $rowsVolMap[$key] ?? null;
                $row    = $rowOi ?? $rowVol;             // whichever exists

                $strike = (int) $row->strike_price;
                $time   = Carbon::parse($row->captured_at)->format('H:i');
                $opt    = $row->option_type;
                $diff_ltp    = $row->diff_ltp ?? null;

                /* per-strike summary tables (if you keep them) */
                if ($rowOi) {
                    $buildupsOi[$strike][$type][] = [
                        'time'      => $time,
                        'opt_type'  => $opt,
                        'oi_diff'   => $rowOi->diff_oi,
                        'vol_diff'  => $rowOi->diff_volume,
                        'oi_rank'   => $rowOi->oi_rank ?? null,
                        'diff_ltp'   => $diff_ltp,
                    ];
                }
                if ($rowVol) {
                    $buildupsVol[$strike][$type][] = [
                        'time'      => $time,
                        'opt_type'  => $opt,
                        'oi_diff'   => $rowVol->diff_oi,
                        'vol_diff'  => $rowVol->diff_volume,
                        'vol_rank'  => $rowVol->vol_rank ?? null,
                        'diff_ltp'   => $diff_ltp,
                    ];
                }

                /* timeline (Tab 1) ------------------------------------------------ */
                $timeline[$key] ??= [
                    'time'      => $time,
                    'strike'    => $strike,
                    'opt_type'  => $opt,
                    'diff_ltp'   => $diff_ltp,
                ];

                /* build-type sub-array */
                $timeline[$key][$type] ??= [
                    'source'    => [],
                ];

                if ($rowOi) {
                    $timeline[$key][$type]['source'][] = 'oi';
                    $timeline[$key][$type]['oi_diff']  = $rowOi->diff_oi;
                    $timeline[$key][$type]['oi_rank']  = $rowOi->oi_rank ?? null;
                }
                if ($rowVol) {
                    $timeline[$key][$type]['source'][] = 'vol';
                    $timeline[$key][$type]['vol_diff'] = $rowVol->diff_volume;
                    $timeline[$key][$type]['vol_rank'] = $rowVol->vol_rank ?? null;
                }
            }
        } /* /foreach buildType */

        /* sort per-strike summaries */
        ksort($buildupsOi);
        ksort($buildupsVol);

        /* timeline → list, latest first */
        $timelineList = array_values($timeline);
        usort($timelineList, fn($a, $b) => strcmp($b['time'], $a['time']));

        /* ---------- NEW ----------  best_rank for every timeline row */
        foreach ($timelineList as &$row) {
            $best = null;
            foreach (['Long Build','Short Build','Long Unwind','Short Cover'] as $bt) {
                if (isset($row[$bt]['oi_rank'])) {
                    $best = $best === null ? $row[$bt]['oi_rank'] : min($best, $row[$bt]['oi_rank']);
                }
                if (isset($row[$bt]['vol_rank'])) {
                    $best = $best === null ? $row[$bt]['vol_rank'] : min($best, $row[$bt]['vol_rank']);
                }
            }
            $row['best_rank'] = $best;   // null if row carries no ranks at all
        }
        unset($row);
        /* ---------- /NEW ---------- */

        /* which build-type columns to show? */
        $colVisible = array_fill_keys($buildTypes, false);
        foreach ($timelineList as $row) {
            foreach ($buildTypes as $t) {
                if (isset($row[$t])) $colVisible[$t] = true;
            }
        }

        /* ---------------- view ---------------- */
        return view('option-chain-table', [
            'symbol'      => $symbol,
            'expiry'      => $expiry,
            'date'        => $date,
            'underlying'  => $underlying,
            'range'       => [$minStrike, $maxStrike],
            'buildupsOi'  => $buildupsOi,
            'buildupsVol' => $buildupsVol,
            'timeline'    => $timelineList,
            'colVisible'  => $colVisible,
        ]);
    }

    public function showBuildUp(Request $request)
    {
        $symbol = $request->input('symbol', 'NIFTY');
        $strikeWindow = (int) $request->input('strike_window', 7); // Odd number only!

        // 1. Get expiry for the selected symbol
        $expiry = DB::table('expiries')
                    ->where('trading_symbol', $symbol)
                    ->where('instrument_type', 'OPT')
                    ->where('is_current', 1)
                    ->orderBy('expiry_date', 'desc')
                    ->value('expiry_date');

        // 2. Get latest underlying_spot_price for this symbol/expiry
        $latestSpotRow = DB::table('option_chains_3m')
                           ->where('trading_symbol', $symbol)
                           ->where('expiry', $expiry)
                           ->orderByDesc('captured_at')
                           ->first();

        if (!$latestSpotRow) {
            return view('option_chain_buildup', [
                'symbol' => $symbol,
                'expiry' => $expiry,
                'strike_window' => $strikeWindow,
                'rows' => [],
            ]);
        }

        $latestSpot = $latestSpotRow->underlying_spot_price;

        // 3. Find the closest ATM strike
        $strikes = DB::table('option_chains_3m')
                     ->where('trading_symbol', $symbol)
                     ->where('expiry', $expiry)
                     ->pluck('strike_price')
                     ->unique()
                     ->sort()
                     ->values();

        $atm = $strikes->min(function ($strike) use ($latestSpot) {
            return abs($strike - $latestSpot);
        });

        $atmStrike = $strikes->first(function ($strike) use ($latestSpot, $atm) {
            return abs($strike - $latestSpot) === $atm;
        });

        // Window logic (assumes strikes are sorted)
        $atmIndex = $strikes->search($atmStrike);
        $halfWindow = intval($strikeWindow / 2);
        $strikeWindowArr = $strikes->slice(max(0, $atmIndex - $halfWindow), $strikeWindow);

        // 4. For each timestamp, pull rows within window for CE and PE, group by timestamp
        $result = [];
        $timestamps = DB::table('option_chains_3m')
                        ->where('trading_symbol', $symbol)
                        ->where('expiry', $expiry)
                        ->select('captured_at')
                        ->distinct()
                        ->orderBy('captured_at')
                        ->get()
                        ->pluck('captured_at');

        foreach ($timestamps as $timestamp) {
            foreach (['CE', 'PE'] as $optionType) {
                $windowRows = DB::table('option_chains_3m')
                                ->where('trading_symbol', $symbol)
                                ->where('expiry', $expiry)
                                ->where('captured_at', $timestamp)
                                ->whereIn('strike_price', $strikeWindowArr)
                                ->where('option_type', $optionType)
                                ->get();

                if ($windowRows->count() === $strikeWindow) {
                    $buildUpSet = $windowRows->pluck('build_up')->filter()->unique();
                    if ($buildUpSet->count() === 1) {
                        $result[] = [
                            'timestamp' => $timestamp,
                            'option_type' => $optionType,
                            'strikes' => $windowRows->pluck('strike_price')->toArray(),
                            'build_up' => $buildUpSet->first(),
                            'diff_oi' => $windowRows->pluck('diff_oi')->toArray(),
                            'diff_vol' => $windowRows->pluck('diff_volume')->toArray(),
                            'diff_ltp' => $windowRows->pluck('diff_ltp')->toArray(),
                        ];
                    }
                }
            }
        }

        $grouped = [];
        foreach ($result as $item) {
            $ts = $item['timestamp'];
            if (!isset($grouped[$ts])) {
                $grouped[$ts] = ['timestamp' => $ts, 'CE' => null, 'PE' => null];
            }
            $grouped[$ts][$item['option_type']] = $item;
        }
// Order by timestamp DESC
        $grouped = collect($grouped)->sortByDesc('timestamp')->values();

        return view('option_chain_buildup', [
            'symbol' => $symbol,
            'expiry' => $expiry,
            'strike_window' => $strikeWindow,
            'rows' => $grouped,
        ]);
    }

    public function showBuildUpAll(Request $request)
    {
        $symbols = ['NIFTY', 'BANKNIFTY', 'SENSEX'];
        $strikeWindow = (int) $request->input('strike_window', 7);
        $onlyWithBoth = $request->boolean('only_with_both', false);

        // 1. Get all current expiries for all symbols
        $expiries = DB::table('expiries')
                      ->whereIn('trading_symbol', $symbols)
                      ->where('instrument_type', 'OPT')
                      ->where('is_current', 1)
                      ->get()
                      ->mapWithKeys(function ($item) {
                          return [$item->trading_symbol => $item->expiry_date];
                      });

        $allRows = [];

        foreach ($symbols as $symbol) {
            $expiry = $expiries[$symbol] ?? null;
            if (!$expiry) continue;

            // 2. Latest spot
            $latestSpotRow = DB::table('option_chains_3m')
                               ->where('trading_symbol', $symbol)
                               ->where('expiry', $expiry)
                               ->orderByDesc('captured_at')
                               ->first();
            if (!$latestSpotRow) continue;
            $latestSpot = $latestSpotRow->underlying_spot_price;

            // 3. All strikes
            $strikes = DB::table('option_chains_3m')
                         ->where('trading_symbol', $symbol)
                         ->where('expiry', $expiry)
                         ->pluck('strike_price')
                         ->unique()
                         ->sort()
                         ->values();

            // 4. ATM logic
            $atm = $strikes->min(function ($strike) use ($latestSpot) {
                return abs($strike - $latestSpot);
            });
            $atmStrike = $strikes->first(function ($strike) use ($latestSpot, $atm) {
                return abs($strike - $latestSpot) === $atm;
            });
            $atmIndex = $strikes->search($atmStrike);
            $halfWindow = intval($strikeWindow / 2);
            $strikeWindowArr = $strikes->slice(max(0, $atmIndex - $halfWindow), $strikeWindow);

            // 5. Distinct timestamps (latest first!)
            $timestamps = DB::table('option_chains_3m')
                            ->where('trading_symbol', $symbol)
                            ->where('expiry', $expiry)
                            ->select('captured_at')
                            ->distinct()
                            ->orderByDesc('captured_at')
                            ->get()
                            ->pluck('captured_at');

            foreach ($timestamps as $timestamp) {
                $row = [
                    'symbol' => $symbol,
                    'expiry' => $expiry,
                    'timestamp' => $timestamp,
                    'CE' => null,
                    'PE' => null
                ];
                foreach (['CE', 'PE'] as $optionType) {
                    $windowRows = DB::table('option_chains_3m')
                                    ->where('trading_symbol', $symbol)
                                    ->where('expiry', $expiry)
                                    ->where('captured_at', $timestamp)
                                    ->whereIn('strike_price', $strikeWindowArr)
                                    ->where('option_type', $optionType)
                                    ->get();

                    if ($windowRows->count() === $strikeWindow) {
                        $buildUpSet = $windowRows->pluck('build_up')->filter()->unique();
                        if ($buildUpSet->count() === 1) {
                            $row[$optionType] = [
                                'strikes' => $windowRows->pluck('strike_price')->toArray(),
                                'build_up' => $buildUpSet->first(),
                                'diff_oi' => $windowRows->pluck('diff_oi')->toArray(),
                                'diff_vol' => $windowRows->pluck('diff_volume')->toArray(),
                                'diff_ltp' => $windowRows->pluck('diff_ltp')->toArray(),
                            ];
                        }
                    }
                }
                // Checkbox logic
                if ($onlyWithBoth) {
                    if ($row['CE'] && $row['PE']) $allRows[] = $row;
                } else {
                    if ($row['CE'] || $row['PE']) $allRows[] = $row;
                }
            }
        }

        // 6. Sort entire result by most recent timestamp
        usort($allRows, fn($a, $b) => strcmp($b['timestamp'], $a['timestamp']));

        return view('option_chain_buildup_all', [
            'strike_window' => $strikeWindow,
            'rows' => $allRows,
            'only_with_both' => $onlyWithBoth,
        ]);
    }


}


