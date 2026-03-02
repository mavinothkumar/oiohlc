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
        $symbol          = $request->get('symbol', 'NIFTY');
        $strikesEachSide = (int) $request->get('strikes_each_side', 3);
        $quoteDate       = $request->get('date');
        $expiry          = $request->get('expiry');

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
            $prevDate    = null;
            foreach ($days as $day) {
                if ($day->current == 1)  $currentDate = $day->working_date;
                if ($day->previous == 1) $prevDate    = $day->working_date;
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

        $atmIndexOpen   = (float) $trend->atm_index_open;
        $strikeInterval = str_contains(strtoupper($symbol), 'BANK') ? 100 : 50;
        $atm            = (int) (round($atmIndexOpen / $strikeInterval) * $strikeInterval);

        $strikes = [];
        for ($i = -$strikesEachSide; $i <= $strikesEachSide; $i++) {
            $strikes[] = $atm + ($i * $strikeInterval);
        }

        // ── 3. Detect underlying_key dynamically ─────────────────────────────
        // Instead of hardcoding LIKE %NIFTY%, discover the actual key from DB
      $underlyingKey = DB::table('option_chains')
                           ->where('trading_symbol', 'LIKE', "%{$symbol}%")
                           ->value('underlying_key');

        if (!$underlyingKey) {
            return response()->json(['error' => "No underlying_key found for {$symbol} in option_chains."], 422);
        }

        // ── 4. Available expiries ─────────────────────────────────────────────
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

        // ── 5. All snapshots ─────────────────────────────────────────────────
        // Use DATE() in SQL to avoid timezone issues with TIMESTAMP column
        $allRows = DB::table('option_chains')
                     ->where('underlying_key', $underlyingKey)
                     ->where('expiry', $expiry)
                     ->whereIn('strike_price', $strikes)
                     ->whereRaw('DATE(CONVERT_TZ(captured_at, "+00:00", "+05:30")) = ?', [$quoteDate])
                     ->orderByDesc('captured_at')
                     ->get();

        if ($allRows->isEmpty()) {
            return response()->json([
                'error' => "No option_chain rows found for date={$quoteDate}, expiry={$expiry}, underlying={$underlyingKey}. "
                           . "Strikes: " . implode(',', $strikes)
            ], 422);
        }

        // ── 6. Group by 3-min bucket ─────────────────────────────────────────
        $snapshots = $allRows->groupBy(function ($row) {
            $ts      = strtotime($row->captured_at); // no offset addition
            $snapped = floor($ts / 180) * 180;
            return date('H:i', $snapped);
        });


        // ── 7. GLOBAL Top-3: High OI +/- and High Volume +/- across ALL strikes ──
        $top3OiPos  = $allRows->where('diff_oi', '>', 0)
                              ->sortByDesc('diff_oi')->take(3)
                              ->pluck('diff_oi')->map(fn($v) => (float)$v)->values()->toArray();

        $top3OiNeg  = $allRows->where('diff_oi', '<', 0)
                              ->sortBy('diff_oi')->take(3)
                              ->pluck('diff_oi')->map(fn($v) => (float)$v)->values()->toArray();

        $top3VolPos = $allRows->where('diff_volume', '>', 0)
                              ->sortByDesc('diff_volume')->take(3)
                              ->pluck('diff_volume')->map(fn($v) => (float)$v)->values()->toArray();

        $top3VolNeg = $allRows->where('diff_volume', '<', 0)
                              ->sortBy('diff_volume')->take(3)
                              ->pluck('diff_volume')->map(fn($v) => (float)$v)->values()->toArray();

// ── 8. PER-STRIKE Top-3: for border-only highlighting ────────────────────
// For each strike, find top 3 positive and negative diff_oi and diff_volume
        $perStrikeHighlights = [];
        foreach ($strikes as $strike) {
            $strikeRows = $allRows->filter(fn($r) => (int)(float)$r->strike_price === $strike);

            $perStrikeHighlights[$strike] = [
                'oi_pos'  => $strikeRows->where('diff_oi', '>', 0)->sortByDesc('diff_oi')
                                        ->take(3)->pluck('diff_oi')->map(fn($v) => (float)$v)->values()->toArray(),
                'oi_neg'  => $strikeRows->where('diff_oi', '<', 0)->sortBy('diff_oi')
                                        ->take(3)->pluck('diff_oi')->map(fn($v) => (float)$v)->values()->toArray(),
                'vol_pos' => $strikeRows->where('diff_volume', '>', 0)->sortByDesc('diff_volume')
                                        ->take(3)->pluck('diff_volume')->map(fn($v) => (float)$v)->values()->toArray(),
                'vol_neg' => $strikeRows->where('diff_volume', '<', 0)->sortBy('diff_volume')
                                        ->take(3)->pluck('diff_volume')->map(fn($v) => (float)$v)->values()->toArray(),
            ];
        }

// ── 9. Build rows ─────────────────────────────────────────────────────────
        $rows = [];
        foreach ($snapshots as $time => $timeRows) {
            $ceData = $timeRows->where('option_type', 'CE')->keyBy(fn($r) => (int)(float)$r->strike_price);
            $peData = $timeRows->where('option_type', 'PE')->keyBy(fn($r) => (int)(float)$r->strike_price);

            $strikeData = [];
            foreach ($strikes as $strike) {
                $ce = $ceData[$strike] ?? null;
                $pe = $peData[$strike] ?? null;
                $strikeData[$strike] = [
                    'ce' => $ce ? [
                        'diff_ltp'    => $ce->diff_ltp,
                        'diff_oi'     => $ce->diff_oi,
                        'diff_volume' => $ce->diff_volume,
                        'build_up'    => $ce->build_up,
                    ] : null,
                    'pe' => $pe ? [
                        'diff_ltp'    => $pe->diff_ltp,
                        'diff_oi'     => $pe->diff_oi,
                        'diff_volume' => $pe->diff_volume,
                        'build_up'    => $pe->build_up,
                    ] : null,
                ];
            }

            $rows[] = [
                'time'        => $time,
                'strike_data' => $strikeData,
            ];
        }


        return response()->json([
            'symbol'          => $symbol,
            'quote_date'      => $quoteDate,
            'prev_date'       => $prevDate,
            'expiry'          => $expiry,
            'expiries'        => $expiries->values(),
            'atm_index_open'  => $atmIndexOpen,
            'atm'             => $atm,
            'strikes'         => $strikes,       // array of ints
            'top3_oi_pos'          => $top3OiPos,
            'top3_oi_neg'          => $top3OiNeg,
            'top3_vol_pos'         => $top3VolPos,
            'top3_vol_neg'         => $top3VolNeg,            // NEW
            'per_strike_highlights' => $perStrikeHighlights,  // NEW
            'rows'                 => $rows,
            'last_updated'         => now()->format('d M Y, H:i:s'),
            'debug'           => [
                'underlying_key' => $underlyingKey,
                'total_rows'     => $allRows->count(),
                'snapshot_times' => $snapshots->keys()->take(5)->values(),
            ],
        ]);
    }

}
