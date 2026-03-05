<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OiDiffLiveController extends Controller
{
    public function index()
    {
        return view('oi-live');
    }

    public function data(Request $request)
    {
        $symbol = $request->get('symbol', 'NIFTY');
        $strikesEachSide = (int) $request->get('strikes_each_side', 2);
        $quoteDate = $request->get('date');
        $expiry = $request->get('expiry');

        // NEW: timeframe (minutes) for delta window, default 3
        $tf = (int) $request->get('tf', 3);
        if (!in_array($tf, [3, 6, 15, 30], true)) {
            $tf = 3;
        }
        $step = (int) ($tf / 3); // 3->1, 15->5, 30->10

        // ── 1. Resolve dates ─────────────────────────────────────────────────
        if (!$quoteDate) {
            $days = DB::table('nse_working_days')
                      ->where(function ($q) {
                          $q->where('current', 1)->orWhere('previous', 1);
                      })
                      ->orderByDesc('working_date')
                      ->limit(2)
                      ->get();

            $currentDate = null;
            $prevDate = null;

            foreach ($days as $day) {
                if ($day->current == 1) $currentDate = $day->working_date;
                if ($day->previous == 1) $prevDate = $day->working_date;
            }

            if (!$currentDate || !$prevDate) {
                return response()->json(['error' => 'Working days not configured.'], 422);
            }

            $quoteDate = $currentDate;
        } else {
            $prevDate = DB::table('nse_working_days')
                          ->where('working_date', '<', $quoteDate)
                          ->orderByDesc('working_date')
                          ->value('working_date');
        }

        // ── 2. ATM from previous day daily_trend ─────────────────────────────
        $trend = DB::table('daily_trend')
                   ->where('quote_date', $prevDate)
                   ->where('symbol_name', $symbol)
                   ->first();

        if (!$trend || is_null($trend->atm_index_open)) {
            return response()->json(['error' => "No ATM index open for {$prevDate} / {$symbol}. Check daily_trend table."], 422);
        }

        $atmIndexOpen = (float) $trend->atm_index_open;
        $strikeInterval = str_contains(strtoupper($symbol), 'BANK') ? 100 : 50;
        $atm = (int) (round($atmIndexOpen / $strikeInterval) * $strikeInterval);

        $strikes = [];
        for ($i = -$strikesEachSide; $i <= $strikesEachSide; $i++) {
            $strikes[] = $atm + ($i * $strikeInterval);
        }

        // ── 3. Detect underlying_key dynamically ─────────────────────────────
        $underlyingKey = DB::table('option_chains')
                           ->where('trading_symbol', 'LIKE', "%{$symbol}%")
                           ->value('underlying_key');

        if (!$underlyingKey) {
            return response()->json(['error' => "No underlying_key found for {$symbol} in option_chains."], 422);
        }

        // ── 4. Available expiries ────────────────────────────────────────────
        $expiries = DB::table('nse_expiries')
                      ->where('trading_symbol', 'NIFTY')
                      ->where('instrument_type', 'OPT')
                      ->where('is_current', '1')
                      ->pluck('expiry_date');

        if (!$expiry) {
            $expiry = $expiries->first();
        }

        if (!$expiry) {
            return response()->json(['error' => "No expiry found for {$symbol}."], 422);
        }

        // ── 5. All snapshots for that day ────────────────────────────────────
        $allRows = DB::table('option_chains')
                     ->where('underlying_key', $underlyingKey)
                     ->where('expiry', $expiry)
                     ->whereIn('strike_price', $strikes)
                     ->whereRaw('DATE(CONVERT_TZ(captured_at, "+00:00", "+05:30")) = ?', [$quoteDate])
                     ->orderBy('captured_at')
                     ->get();

        if ($allRows->isEmpty()) {
            return response()->json([
                'error' => "No option_chain rows found for date={$quoteDate}, expiry={$expiry}, underlying={$underlyingKey}. "
                           . "Strikes: " . implode(',', $strikes)
            ], 422);
        }

        // ── 6. Group by 3-min bucket ─────────────────────────────────────────
        $snapshots = $allRows->groupBy(function ($row) {
            $ts = strtotime($row->captured_at);
            $snapped = floor($ts / 180) * 180;
            return date('Y-m-d H:i', $snapped); // CHANGED
        });

        // Convert to indexed arrays for step-back access
        $times = $snapshots->keys()->values();
        $snapshotList = $snapshots->values();

        // ── 7/8. We'll compute top3/highlights from computed deltas (not diff_* columns) ──
        $computedAllDiffOi = [];
        $computedAllDiffVol = [];
        $computedPerStrikeOi = [];
        $computedPerStrikeVol = [];

        foreach ($strikes as $s) {
            $computedPerStrikeOi[$s] = [];
            $computedPerStrikeVol[$s] = [];
        }
        $anchorTs = strtotime($times[0]);            // first bucket of the day (ex: 2026-03-02 09:15)
        $tfSeconds = $tf * 60;

        // ── 9. Build rows with delta from ltp/oi/volume ───────────────────────
        $rows = [];

        for ($i = 0; $i < $times->count(); $i++) {
            $time = $times[$i];
            $timeRows = $snapshotList[$i];

            $time = $times[$i];
            $bucketTs = strtotime($time);

// NEW: only keep boundary buckets for selected timeframe
            if ((($bucketTs - $anchorTs) % $tfSeconds) !== 0) {
                continue;
            }

            $prevIndex = $i - $step;
            $prevRows = $prevIndex >= 0 ? $snapshotList[$prevIndex] : collect();

            $ceNow = $timeRows->where('option_type', 'CE')->keyBy(fn ($r) => (int)(float)$r->strike_price);
            $peNow = $timeRows->where('option_type', 'PE')->keyBy(fn ($r) => (int)(float)$r->strike_price);

            $cePrev = $prevRows->where('option_type', 'CE')->keyBy(fn ($r) => (int)(float)$r->strike_price);
            $pePrev = $prevRows->where('option_type', 'PE')->keyBy(fn ($r) => (int)(float)$r->strike_price);

            $strikeData = [];



            foreach ($strikes as $strike) {

                $ce = $ceNow[$strike] ?? null;
                $pe = $peNow[$strike] ?? null;

                $ceP = $cePrev[$strike] ?? null;
                $peP = $pePrev[$strike] ?? null;

                $ceDiffLtp = ($ce && $ceP) ? (float)$ce->ltp - (float)$ceP->ltp : null;
                $ceDiffOi = ($ce && $ceP) ? (int)$ce->oi - (int)$ceP->oi : null;
                $ceDiffVol = ($ce && $ceP) ? (int)$ce->volume - (int)$ceP->volume : null;

                $peDiffLtp = ($pe && $peP) ? (float)$pe->ltp - (float)$peP->ltp : null;
                $peDiffOi = ($pe && $peP) ? (int)$pe->oi - (int)$peP->oi : null;
                $peDiffVol = ($pe && $peP) ? (int)$pe->volume - (int)$peP->volume : null;

                // Collect for top3/highlights (ignore nulls)
                if ($ceDiffOi !== null) {
                    $computedAllDiffOi[] = (float)$ceDiffOi;
                    $computedPerStrikeOi[$strike][] = (float)$ceDiffOi;
                }
                if ($peDiffOi !== null) {
                    $computedAllDiffOi[] = (float)$peDiffOi;
                    $computedPerStrikeOi[$strike][] = (float)$peDiffOi;
                }
                if ($ceDiffVol !== null) {
                    $computedAllDiffVol[] = (float)$ceDiffVol;
                    $computedPerStrikeVol[$strike][] = (float)$ceDiffVol;
                }
                if ($peDiffVol !== null) {
                    $computedAllDiffVol[] = (float)$peDiffVol;
                    $computedPerStrikeVol[$strike][] = (float)$peDiffVol;
                }

                $strikeData[$strike] = [
                    'ce' => $ce ? [
                        // current values (always available for that snapshot)
                        'ltp' => (float) $ce->ltp,
                        'oi' => (int) $ce->oi,
                        'volume' => (int) $ce->volume,

                        // deltas vs previous boundary (null for first boundary like 09:15)
                        'diff_ltp' => $ceDiffLtp,
                        'diff_oi' => $ceDiffOi,
                        'diff_volume' => $ceDiffVol,

                        'build_up' => $ce->build_up,
                    ] : null,

                    'pe' => $pe ? [
                        'ltp' => (float) $pe->ltp,
                        'oi' => (int) $pe->oi,
                        'volume' => (int) $pe->volume,

                        'diff_ltp' => $peDiffLtp,
                        'diff_oi' => $peDiffOi,
                        'diff_volume' => $peDiffVol,

                        'build_up' => $pe->build_up,
                    ] : null,
                ];
            }

            $rows[] = [
                'time' => date('H:i', strtotime($time)),
                'strike_data' => $strikeData,
            ];
        }

        // Helper: top 3 positive/negative
        $top3Pos = function (array $arr) {
            $pos = array_values(array_filter($arr, fn($v) => $v > 0));
            rsort($pos);
            return array_slice($pos, 0, 3);
        };
        $top3Neg = function (array $arr) {
            $neg = array_values(array_filter($arr, fn($v) => $v < 0));
            sort($neg);
            return array_slice($neg, 0, 3);
        };

        // GLOBAL top3 from computed deltas
        $top3OiPos = $top3Pos($computedAllDiffOi);
        $top3OiNeg = $top3Neg($computedAllDiffOi);
        $top3VolPos = $top3Pos($computedAllDiffVol);
        $top3VolNeg = $top3Neg($computedAllDiffVol);

        // PER-STRIKE top3 from computed deltas
        $perStrikeHighlights = [];
        foreach ($strikes as $strike) {
            $perStrikeHighlights[$strike] = [
                'oi_pos' => $top3Pos($computedPerStrikeOi[$strike]),
                'oi_neg' => $top3Neg($computedPerStrikeOi[$strike]),
                'vol_pos' => $top3Pos($computedPerStrikeVol[$strike]),
                'vol_neg' => $top3Neg($computedPerStrikeVol[$strike]),
            ];
        }

        $rows = array_reverse($rows);

        return response()->json([
            'symbol' => $symbol,
            'quote_date' => $quoteDate,
            'prev_date' => $prevDate,
            'expiry' => $expiry,
            'expiries' => $expiries->values(),
            'atm_index_open' => $atmIndexOpen,
            'atm' => $atm,
            'strikes' => $strikes,

            // NEW: timeframe info
            'tf' => $tf,

            'top3_oi_pos' => $top3OiPos,
            'top3_oi_neg' => $top3OiNeg,
            'top3_vol_pos' => $top3VolPos,
            'top3_vol_neg' => $top3VolNeg,
            'per_strike_highlights' => $perStrikeHighlights,
            'rows' => $rows,
            'last_updated' => now()->format('d M Y, H:i:s'),
            'debug' => [
                'underlying_key' => $underlyingKey,
                'total_rows' => $allRows->count(),
                'snapshot_times' => $times->take(5)->values(),
            ],
        ]);
    }


}
