<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OiDiffController extends Controller
{
    /**
     * Main page — renders the filter + table.
     */
    public function index(Request $request)
    {
        $request->validate([
            'date'      => 'nullable|date',
            'expiry'    => 'nullable|date',
            'strikes'   => 'nullable|array|max:6',
            'strikes.*' => 'nullable|numeric',
        ]);

        $date    = $request->input('date');
        $expiry  = $request->input('expiry');
        $minutes  = $request->input('minutes', '3minute');
        $strikes = array_filter((array) $request->input('strikes', []), fn($v) => $v !== null && $v !== '');
        $strikes = array_map('floatval', array_values($strikes));

        // ── Early return: show empty form if required filters are missing ──
        if (! $date || ! $expiry || empty($strikes)) {
            return view('test.oi-diff', [
                'date'       => $date,
                'expiry'     => $expiry,
                'strikes'    => $strikes,
                'tableData'  => [],
                'timestamps' => [],
                'allStrikes' => $strikes,
                'highlight'  => ['oi_pos' => [], 'oi_neg' => [], 'vol_pos' => [], 'vol_neg' => []],
            ]);
        }

        // ── All filters present — run DB queries ──
        $rows = DB::table('expired_ohlc')
                  ->where('underlying_symbol', 'NIFTY')
                  ->where('expiry', $expiry)
                  ->where('interval', $minutes)
                  ->whereDate('timestamp', $date)
                  ->whereIn('strike', $strikes)
                  ->whereIn('instrument_type', ['CE', 'PE'])
                  ->orderBy('timestamp', 'desc')
                  ->get(['strike', 'instrument_type', 'close', 'volume', 'open_interest', 'timestamp']);

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(float) $row->strike][$row->instrument_type][$row->timestamp] = $row;
        }

        $timestamps = $rows->pluck('timestamp')->unique()->sortDesc()->values()->toArray();

        $tableData   = [];
        $allOiDiffs  = [];
        $allVolDiffs = [];

        foreach ($timestamps as $idx => $ts) {
            $nextTs = $timestamps[$idx + 1] ?? null;

            foreach ($strikes as $strike) {
                foreach (['CE', 'PE'] as $type) {
                    $curr = $grouped[$strike][$type][$ts]    ?? null;
                    $prev = $nextTs ? ($grouped[$strike][$type][$nextTs] ?? null) : null;

                    $oiDiff  = ($curr && $prev) ? (int) $curr->open_interest - (int) $prev->open_interest : null;
                    $volDiff = ($curr && $prev) ? (int) $curr->volume - (int) $prev->volume               : null;
                    $closeDiff = ($curr && $prev) ? round((float) $curr->close - (float) $prev->close, 2)  : null;

// ── Build-up type ─────────────────────────────────────────────────────
                    $buildUp = null;
                    if ($oiDiff !== null && $closeDiff !== null) {
                        if ($oiDiff > 0 && $closeDiff > 0)      $buildUp = 'LB'; // Long Build
                        elseif ($oiDiff > 0 && $closeDiff < 0)  $buildUp = 'SB'; // Short Build
                        elseif ($oiDiff < 0 && $closeDiff < 0)  $buildUp = 'LU'; // Long Unwind
                        elseif ($oiDiff < 0 && $closeDiff > 0)  $buildUp = 'SC'; // Short Cover
                    }

                    $tableData[$ts][$strike][$type] = [
                        'close'      => $curr ? (float) $curr->close : null,
                        'close_diff' => $closeDiff,
                        'oi'         => $curr ? (int) $curr->open_interest : null,
                        'oi_diff'    => $oiDiff,
                        'vol'        => $curr ? (int) $curr->volume : null,
                        'vol_diff'   => $volDiff,
                        'build_up'   => $buildUp,
                    ];

                    $cellKey = "{$ts}|{$strike}|{$type}";

                    if ($oiDiff !== null) {
                        $allOiDiffs[]  = ['ts' => $ts, 'strike' => $strike, 'type' => $type, 'val' => $oiDiff];
                    }
                    if ($volDiff !== null) {
                        $allVolDiffs[] = ['ts' => $ts, 'strike' => $strike, 'type' => $type, 'val' => $volDiff];
                    }
                }
            }
        }

        $highlight = $this->computeHighlights($allOiDiffs, $allVolDiffs, $strikes);

        $allStrikes = $strikes;

        return view('test.oi-diff', compact(
            'date', 'expiry', 'strikes',
            'tableData', 'timestamps', 'allStrikes', 'highlight'
        ));
    }


    /**
     * AJAX — fetch expiries for a date.
     */
    public function fetchExpiries(Request $request)
    {
        $request->validate(['date' => 'required|date']);

        // Single nearest expiry
        $expiry = DB::table('expired_expiries')
                    ->where('underlying_symbol', 'NIFTY')
                    ->where('instrument_type', 'OPT')
                    ->where('expiry_date', '>=', $request->date)
                    ->orderBy('expiry_date')
                    ->value('expiry_date');

        $strikes   = collect();
        $atmStrike = null;

        if ($expiry) {
            // All distinct strikes for that expiry on that date
            $strikes = DB::table('expired_ohlc')
                         ->where('underlying_symbol', 'NIFTY')
                         ->where('expiry', $expiry)
                         ->where('interval', '3minute')
                         ->whereDate('timestamp', $request->date)
                         ->whereIn('instrument_type', ['CE', 'PE'])
                         ->distinct()
                         ->orderBy('strike')
                         ->pluck('strike');

            // ATM index open for auto-filling 6 boxes
            $atmStrike = DB::table('daily_trend')
                           ->where('symbol_name', 'NIFTY')
                           ->where('quote_date', $request->date)
                           ->where('expiry_date', $expiry)
                           ->value('atm_index_open');
        }

        return response()->json([
            'expiry'  => $expiry,
            'strikes' => $strikes,
            'atm'     => $atmStrike ? (int) $atmStrike : null, // ← cast to int
        ]);
    }



    /**
     * Build highlight keys for top-3 positive/negative OI diff and Vol diff.
     */
    private function computeHighlights(array $oiDiffs, array $volDiffs, array $strikes): array
    {
        $makeKey = fn($item) => "{$item['ts']}|{$item['strike']}|{$item['type']}";

        // ── Overall top 3 ────────────────────────────────────────────────────────
        usort($oiDiffs, fn($a, $b) => $b['val'] <=> $a['val']);
        $oiTopPos = array_flip(array_map($makeKey, array_slice($oiDiffs, 0, 3)));

        usort($oiDiffs, fn($a, $b) => $a['val'] <=> $b['val']);
        $oiTopNeg = array_flip(array_map($makeKey, array_slice($oiDiffs, 0, 3)));

        usort($volDiffs, fn($a, $b) => $b['val'] <=> $a['val']);
        $volTopPos = array_flip(array_map($makeKey, array_slice($volDiffs, 0, 3)));

        usort($volDiffs, fn($a, $b) => $a['val'] <=> $b['val']);
        $volTopNeg = array_flip(array_map($makeKey, array_slice($volDiffs, 0, 3)));

        // ── Per-strike top 3 ─────────────────────────────────────────────────────
        $strikeOiPos  = [];
        $strikeOiNeg  = [];
        $strikeVolPos = [];
        $strikeVolNeg = [];

        foreach ($strikes as $strike) {
            // Filter diffs for this strike only
            $sOi  = array_values(array_filter($oiDiffs,  fn($x) => $x['strike'] == $strike));
            $sVol = array_values(array_filter($volDiffs, fn($x) => $x['strike'] == $strike));

            usort($sOi, fn($a, $b) => $b['val'] <=> $a['val']);
            foreach (array_slice($sOi, 0, 3) as $item) {
                $strikeOiPos[$makeKey($item)] = true;
            }

            usort($sOi, fn($a, $b) => $a['val'] <=> $b['val']);
            foreach (array_slice($sOi, 0, 3) as $item) {
                $strikeOiNeg[$makeKey($item)] = true;
            }

            usort($sVol, fn($a, $b) => $b['val'] <=> $a['val']);
            foreach (array_slice($sVol, 0, 3) as $item) {
                $strikeVolPos[$makeKey($item)] = true;
            }

            usort($sVol, fn($a, $b) => $a['val'] <=> $b['val']);
            foreach (array_slice($sVol, 0, 3) as $item) {
                $strikeVolNeg[$makeKey($item)] = true;
            }
        }

        return [
            // Overall
            'oi_pos'        => $oiTopPos,
            'oi_neg'        => $oiTopNeg,
            'vol_pos'       => $volTopPos,
            'vol_neg'       => $volTopNeg,
            // Per-strike
            'strike_oi_pos'  => $strikeOiPos,
            'strike_oi_neg'  => $strikeOiNeg,
            'strike_vol_pos' => $strikeVolPos,
            'strike_vol_neg' => $strikeVolNeg,
        ];
    }


}
