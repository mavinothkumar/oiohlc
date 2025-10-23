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
}


