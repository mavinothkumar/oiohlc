<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Expiry;
use Carbon\Carbon;

class BuildUpSummaryController extends Controller
{
    public function index(Request $request)
    {
        /* ───────────── Basic inputs ───────────── */
        $tz        = 'Asia/Kolkata';
        $allowed   = ['NIFTY', 'BANKNIFTY', 'SENSEX'];

        $symbol    = strtoupper($request->input('symbol', 'NIFTY'));
        if (!in_array($symbol, $allowed, true)) $symbol = 'NIFTY';

        $expiryOverride = $request->input('expiry');            // YYYY-MM-DD or empty
        $overrideRange  = (int) $request->input('range', 0);    // ± strike band
        $sortCol        = $request->input('sort', 'Long Build'); // which TOTAL column to sort rows by

        $sortable = ['Long Build', 'Short Build', 'Long Unwind', 'Short Cover'];
        if (!in_array($sortCol, $sortable, true)) $sortCol = 'Long Build';

        /* ───────────── Time window ───────────── */
        $fromQ = $request->input('from');
        $toQ   = $request->input('to');
        $dateQ = $request->input('date');                       // legacy single-date param

        if ($fromQ || $toQ) {
            $dayStart = $fromQ ? Carbon::parse($fromQ, $tz) : Carbon::now($tz)->setTime(9, 15, 0);
            $dayEnd   = $toQ   ? Carbon::parse($toQ,   $tz) : Carbon::now($tz);
        } elseif ($dateQ) {
            $dt = Carbon::parse($dateQ, $tz);
            if (strlen(trim($dateQ)) > 10) {                    // full ts
                $dayStart = $dt->copy()->setTime(9, 15, 0);
                $dayEnd   = $dt->copy();
            } else {                                            // just a day
                $dayStart = $dt->copy()->startOfDay();
                $dayEnd   = $dt->copy()->endOfDay();
            }
        } else {
            $now      = Carbon::now($tz);
            $dayStart = $now->copy()->setTime(9, 15, 0);
            $dayEnd   = $now;
        }
        if ($dayEnd->lt($dayStart)) [$dayStart, $dayEnd] = [$dayEnd, $dayStart];

        /* ───────────── Strike band helpers ───────────── */
        $defaultRange = ['NIFTY'=>200, 'BANKNIFTY'=>500, 'SENSEX'=>500];
        $defaultStep  = ['NIFTY'=>50,  'BANKNIFTY'=>100, 'SENSEX'=>100];

        $band = $overrideRange ?: ($defaultRange[$symbol] ?? 200);

        /* ───────────── Expiry ───────────── */
        $expiry = $expiryOverride
            ? Carbon::parse($expiryOverride, $tz)->toDateString()
            : Expiry::where('trading_symbol', $symbol)->where('is_current', 1)->value('expiry_date');

        /* ───────────── Persist UI filters ───────────── */
        $filters = [
            'symbol' => $symbol,
            'expiry' => $expiryOverride ?: '',
            'from'   => $dayStart->format('Y-m-d\TH:i'),
            'to'     => $dayEnd->format('Y-m-d\TH:i'),
            'range'  => $overrideRange ?: '',
            'sort'   => $sortCol,
        ];

        $result = [];
        if (!$expiry) {
            return view('buildups.index', compact('result', 'filters', 'allowed'));
        }

        /* ───────────── Anchor (latest non-empty tick) ───────────── */
        $nearToStart = $dayEnd->copy()->subMinutes(15);
        if ($nearToStart->lt($dayStart)) $nearToStart = $dayStart->copy();

        $anchorEndTs = DB::table('option_chains_3m')
                         ->where('trading_symbol', $symbol)
                         ->where('expiry', $expiry)
                         ->whereBetween('captured_at', [$nearToStart, $dayEnd])
                         ->max('captured_at');

        if (!$anchorEndTs) {
            $anchorEndTs = DB::table('option_chains_3m')
                             ->where('trading_symbol', $symbol)
                             ->where('expiry', $expiry)
                             ->whereBetween('captured_at', [$dayStart, $dayEnd])
                             ->max('captured_at');
        }

        if (!$anchorEndTs) {
            return view('buildups.index', compact('result', 'filters', 'allowed'));
        }

        $anchorEnd  = Carbon::parse($anchorEndTs, $tz);
        $win5Start  = max($dayStart, $anchorEnd->copy()->subMinutes(6));
        $win15Start = max($dayStart, $anchorEnd->copy()->subMinutes(15));

        // push actual anchor back to UI
        $filters['to'] = $anchorEnd->format('Y-m-d\TH:i');

        /* ───────────── Latest underlying price ───────────── */
        $underlying = DB::table('option_chains_3m')
                        ->where('trading_symbol', $symbol)
                        ->where('expiry', $expiry)
                        ->whereBetween('captured_at', [$dayStart, $anchorEnd])
                        ->orderByDesc('captured_at')
                        ->value('underlying_spot_price');

        if (!$underlying) {
            return view('buildups.index', compact('result', 'filters', 'allowed'));
        }

        $minStrike = floor(($underlying - $band) / $defaultStep[$symbol]) * $defaultStep[$symbol];
        $maxStrike = ceil( ($underlying + $band) / $defaultStep[$symbol]) * $defaultStep[$symbol];

        /* ───────────── Aggregate ΔOI totals + 5 m / 15 m windows ───────────── */
        $rows = DB::table('option_chains_3m')
                  ->selectRaw(
                      'strike_price, option_type, build_up,
                 SUM(diff_oi)                                                   AS total_oi,
                 SUM(IF(`captured_at` BETWEEN ? AND ?, diff_oi, 0))               AS oi_5m,
                 SUM(IF(`captured_at` BETWEEN ? AND ?, diff_oi, 0))               AS oi_15m',
                      [
                          $win5Start->toDateTimeString(),  $anchorEnd->toDateTimeString(),
                          $win15Start->toDateTimeString(), $anchorEnd->toDateTimeString(),
                      ]
                  )
                  ->where('trading_symbol',        $symbol)
                  ->where('expiry',   $expiry)
                  ->whereBetween('captured_at', [$dayStart, $anchorEnd])
                  ->whereBetween('strike_price', [$minStrike, $maxStrike])
                  ->whereIn('build_up', ['Long Build','Short Build','Long Unwind','Short Cover'])
                  ->groupBy('strike_price', 'option_type', 'build_up')
                  ->get();

        /* ───────────── Pivot to strike-centric structure ───────────── */
        $strikes = [];
        foreach ($rows as $r) {
            $k   = (int) $r->strike_price;
            $t   = $r->option_type;   // CE / PE
            $b   = $r->build_up;      // Long Build …

            $strikes[$k]['strike'] ??= $k;
            foreach ([$t, "{$t}_5", "{$t}_15"] as $bucket) {
                $strikes[$k][$bucket] ??= [
                    'Long Build'=>0,'Short Build'=>0,
                    'Long Unwind'=>0,'Short Cover'=>0,
                ];
            }

            $strikes[$k][$t][$b]        = (int)$r->total_oi;
            $strikes[$k]["{$t}_5"][$b]  = (int)$r->oi_5m;
            $strikes[$k]["{$t}_15"][$b] = (int)$r->oi_15m;
        }
        // pad any missing buckets
        foreach ($strikes as &$s) {
            foreach (['CE','PE','CE_5','PE_5','CE_15','PE_15'] as $bucket) {
                $s[$bucket] ??= ['Long Build'=>0,'Short Build'=>0,'Long Unwind'=>0,'Short Cover'=>0];
            }
        }
        unset($s);

        /* ───────────── Sort strikes by selected TOTAL column (max of CE/PE) ───────────── */
        uasort($strikes, fn($a,$b) =>
        ($bmax = max($b['CE'][$sortCol], $b['PE'][$sortCol]))
        <=> ($amax = max($a['CE'][$sortCol], $a['PE'][$sortCol]))
            ?: ($b['strike'] <=> $a['strike'])
        );

        /* ───────────── Top-3 highlight sets ───────────── */
        // 5 m & 15 m already existed
        $top3_5  = ['Long Build'=>[], 'Short Build'=>[], 'Long Unwind'=>[], 'Short Cover'=>[]];
        $top3_15 = ['Long Build'=>[], 'Short Build'=>[], 'Long Unwind'=>[], 'Short Cover'=>[]];

        // NEW buckets
        $top3_total = ['Long Build'=>[], 'Short Build'=>[], 'Long Unwind'=>[], 'Short Cover'=>[]];
        $top3_diff  = ['lb_lu'=>[],      'sb_sc'=>[]];   // rank by |value|

        foreach ($strikes as $row) {
            foreach (['CE','PE'] as $t) {
                // ---------- 5m ----------
                $v5 = $row["{$t}_5"];
                if (($v5['Long Build']  ?? 0) > 0) $top3_5['Long Build'][]  = $v5['Long Build'];
                if (($v5['Short Build'] ?? 0) > 0) $top3_5['Short Build'][] = $v5['Short Build'];
                if (($v5['Long Unwind'] ?? 0) < 0) $top3_5['Long Unwind'][] = $v5['Long Unwind'];
                if (($v5['Short Cover'] ?? 0) < 0) $top3_5['Short Cover'][] = $v5['Short Cover'];

                // ---------- 15m ----------
                $v15 = $row["{$t}_15"];
                if (($v15['Long Build']  ?? 0) > 0) $top3_15['Long Build'][]  = $v15['Long Build'];
                if (($v15['Short Build'] ?? 0) > 0) $top3_15['Short Build'][] = $v15['Short Build'];
                if (($v15['Long Unwind'] ?? 0) < 0) $top3_15['Long Unwind'][] = $v15['Long Unwind'];
                if (($v15['Short Cover'] ?? 0) < 0) $top3_15['Short Cover'][] = $v15['Short Cover'];

                // ---------- totals ----------
                $tot = $row[$t];
                $top3_total['Long Build'][]   = $tot['Long Build'];
                $top3_total['Short Build'][]  = $tot['Short Build'];
                $top3_total['Long Unwind'][]  = $tot['Long Unwind'];
                $top3_total['Short Cover'][]  = $tot['Short Cover'];

                // ---------- derived diffs ----------
                $lb_lu = $tot['Long Build']  + $tot['Long Unwind'];
                $sb_sc = $tot['Short Build'] + $tot['Short Cover'];
                $top3_diff['lb_lu'][] = $lb_lu;
                $top3_diff['sb_sc'][] = $sb_sc;
            }
        }

        // dedupe, pick top-3
        foreach (['Long Build','Short Build'] as $col) {
            $lst = array_values(array_unique($top3_5[$col]));  rsort($lst); $top3_5[$col]  = array_slice($lst,0,3);
            $lst = array_values(array_unique($top3_15[$col])); rsort($lst); $top3_15[$col] = array_slice($lst,0,3);

            $lst = array_values(array_unique($top3_total[$col])); rsort($lst); $top3_total[$col] = array_slice($lst,0,3);
        }
        foreach (['Long Unwind','Short Cover'] as $col) {
            $lst = array_values(array_unique($top3_5[$col]));  sort($lst); $top3_5[$col]  = array_slice($lst,0,3);
            $lst = array_values(array_unique($top3_15[$col])); sort($lst); $top3_15[$col] = array_slice($lst,0,3);

            $lst = array_values(array_unique($top3_total[$col])); sort($lst); $top3_total[$col] = array_slice($lst,0,3);
        }
        foreach (['lb_lu','sb_sc'] as $col) {
            $lst = array_values(array_unique($top3_diff[$col]));
            usort($lst, fn($a,$b) => abs($b) <=> abs($a));          // magnitude ranking
            $top3_diff[$col] = array_slice($lst, 0, 3);
        }

        /* ───────────── Pack result ───────────── */
        $result = [
            'meta'        => [
                'symbol'     => $symbol,
                'expiry'     => $expiry,
                'underlying' => $underlying,
                'range_used' => $band,
                'from'       => $dayStart->format('Y-m-d H:i'),
                'to'         => $anchorEnd->format('Y-m-d H:i'),
                'sort'       => $sortCol,
            ],
            'strikes'     => array_values($strikes),
            'top3_5'      => $top3_5,
            'top3_15'     => $top3_15,
            'top3_total'  => $top3_total,
            'top3_diff'   => $top3_diff,
        ];

        return view('buildups.index', compact('result', 'filters', 'allowed'));
    }
}
