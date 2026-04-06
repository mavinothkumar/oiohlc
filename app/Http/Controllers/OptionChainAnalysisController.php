<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OptionChainAnalysisController extends Controller
{
    public function index()
    {
        return view('option-chain-analysis');
    }

    /**
     * JSON endpoint.
     * ?strikes=3  (default)
     * ?date=YYYY-MM-DD  (defaults to today)
     */
    public function getData(Request $request)
    {
        $strikeRange = (int) $request->query('strikes', 3);
        $date        = $request->query('date', now()->toDateString());

        // 1. Current expiry
        $expiry = DB::table('nse_expiries')
                    ->where('instrument_type', 'OPT')
                    ->where('trading_symbol', 'NIFTY')
                    ->where('is_current', 1)
                    ->first();

        if (!$expiry) {
            return response()->json(['error' => 'No current expiry found'], 404);
        }

        // 2. Most recent record → spot price
        $latest = DB::table('option_chains')
                    ->whereDate('captured_at', $date)
                    ->where('expiry', $expiry->expiry_date)
                    ->orderByDesc('captured_at')
                    ->first();

        if (!$latest) {
            return response()->json(['error' => 'No option chain data found for ' . $date], 404);
        }

        $spotPrice = (float) $latest->underlying_spot_price;

        // 3. All strikes → find ATM → select ± N strikes
        $allStrikes = DB::table('option_chains')
                        ->whereDate('captured_at', $date)
                        ->where('expiry', $expiry->expiry_date)
                        ->select('strike_price')
                        ->distinct()
                        ->orderBy('strike_price')
                        ->pluck('strike_price')
                        ->map(fn($s) => (float) $s)
                        ->toArray();

        if (empty($allStrikes)) {
            return response()->json(['error' => 'No strikes found'], 404);
        }

        $atm = collect($allStrikes)->reduce(
            fn($carry, $strike) => abs($strike - $spotPrice) < abs($carry - $spotPrice) ? $strike : $carry,
            $allStrikes[0]
        );

        $atmIndex        = array_search($atm, $allStrikes);
        $low             = max(0, $atmIndex - $strikeRange);
        $high            = min(count($allStrikes) - 1, $atmIndex + $strikeRange);
        $selectedStrikes = array_slice($allStrikes, $low, $high - $low + 1);

        // 4. All 5-min timestamps — skip 09:15 (first candle)
        $timestamps = DB::table('option_chains')
                        ->whereDate('captured_at', $date)
                        ->where('expiry', $expiry->expiry_date)
                        ->whereIn('strike_price', $selectedStrikes)
                        ->select(DB::raw('DATE_FORMAT(captured_at, "%H:%i") as ts'))
                        ->distinct()
                        ->orderBy('ts')
                        ->pluck('ts')
                        ->filter(fn($t) => $t !== '09:15')
                        ->values()
                        ->toArray();

        if (empty($timestamps)) {
            return response()->json(['error' => 'No candle data after 09:15'], 404);
        }

        // 5. Fetch all rows for selected strikes on this date
        $rows = DB::table('option_chains')
                  ->whereDate('captured_at', $date)
                  ->where('expiry', $expiry->expiry_date)
                  ->whereIn('strike_price', $selectedStrikes)
                  ->where(DB::raw('DATE_FORMAT(captured_at, "%H:%i")'), '!=', '09:15')
                  ->select([
                      'strike_price', 'option_type',
                      'diff_ltp', 'diff_oi', 'diff_volume', 'build_up',
                      DB::raw('DATE_FORMAT(captured_at, "%H:%i") as ts'),
                  ])
                  ->orderBy('captured_at')
                  ->orderBy('strike_price')
                  ->get();

        // 6. Build per-timestamp aggregated data
        $tsData = [];

        foreach ($timestamps as $ts) {
            $tsRows     = $rows->where('ts', $ts);
            $details    = [];
            $totalDiffOI    = 0;
            $totalDiffPrice = 0;
            $buyCounts  = 0;
            $sellCounts = 0;

            foreach ($selectedStrikes as $strike) {
                $ce = $tsRows->first(fn($r) => (float)$r->strike_price === $strike && $r->option_type === 'CE');
                $pe = $tsRows->first(fn($r) => (float)$r->strike_price === $strike && $r->option_type === 'PE');

                $ceDiffOI    = $ce ? (int)$ce->diff_oi      : 0;
                $peDiffOI    = $pe ? (int)$pe->diff_oi      : 0;
                $ceDiffVol   = $ce ? (int)$ce->diff_volume  : 0;
                $peDiffVol   = $pe ? (int)$pe->diff_volume  : 0;
                $ceDiffPrice = $ce ? (float)$ce->diff_ltp   : 0;
                $peDiffPrice = $pe ? (float)$pe->diff_ltp   : 0;
                $ceBuildUp   = $ce ? $ce->build_up : null;
                $peBuildUp   = $pe ? $pe->build_up : null;

                $combOI    = $ceDiffOI + $peDiffOI;
                $combPrice = $ceDiffPrice + $peDiffPrice;
                $combBuild = $this->computeBuildUp($combOI, $combPrice);

                $totalDiffOI    += $combOI;
                $totalDiffPrice += $combPrice;

                // Buy = Long Build or Short Cover; Sell = Short Build or Long Unwind
                foreach ([$ceBuildUp, $peBuildUp] as $bu) {
                    if (in_array($bu, ['Long Build', 'Short Cover'])) $buyCounts++;
                    if (in_array($bu, ['Short Build', 'Long Unwind'])) $sellCounts++;
                }

                $details[] = [
                    'strike'            => $strike,
                    'ce_diff_oi'        => $ceDiffOI,
                    'pe_diff_oi'        => $peDiffOI,
                    'ce_diff_volume'    => $ceDiffVol,
                    'pe_diff_volume'    => $peDiffVol,
                    'ce_diff_price'     => $ceDiffPrice,
                    'pe_diff_price'     => $peDiffPrice,
                    'ce_build_up'       => $ceBuildUp,
                    'pe_build_up'       => $peBuildUp,
                    'combined_diff_oi'  => $combOI,
                    'combined_build_up' => $combBuild,
                ];
            }

            $tsData[] = [
                'ts'               => $ts,
                'strike_details'   => $details,
                'total_diff_oi'    => $totalDiffOI,
                'total_diff_price' => $totalDiffPrice,
                'overall_build_up' => $this->computeBuildUp($totalDiffOI, $totalDiffPrice),
                'signal'           => $buyCounts >= $sellCounts ? 'Buy' : 'Sell',
                'buy_count'        => $buyCounts,
                'sell_count'       => $sellCounts,
            ];
        }

        // 7. Cumulative series (09:20 → now)
        $cumOI = 0; $cumPrice = 0;
        $cumulative = [];
        foreach ($tsData as $row) {
            $cumOI    += $row['total_diff_oi'];
            $cumPrice += $row['total_diff_price'];
            $cumulative[] = [
                'ts'          => $row['ts'],
                'cum_diff_oi' => $cumOI,
                'cum_price'   => $cumPrice,
                'build_up'    => $this->computeBuildUp($cumOI, $cumPrice),
            ];
        }

        return response()->json([
            'expiry'           => $expiry->expiry_date,
            'spot'             => $spotPrice,
            'atm_strike'       => $atm,
            'selected_strikes' => $selectedStrikes,
            'timestamps'       => array_values($timestamps),
            'ts_data'          => $tsData,
            'cumulative'       => $cumulative,
        ]);
    }

    /**
     * Dynamic build-up from OI + Price direction.
     *
     *  OI ↑, Price ↑  → Long Build   (bullish)
     *  OI ↑, Price ↓  → Short Build  (bearish)
     *  OI ↓, Price ↑  → Short Cover  (bullish)
     *  OI ↓, Price ↓  → Long Unwind  (bearish)
     */
    private function computeBuildUp(int|float $diffOI, float $diffPrice): string
    {
        if ($diffOI >= 0 && $diffPrice >= 0) return 'Long Build';
        if ($diffOI >= 0 && $diffPrice <  0) return 'Short Build';
        if ($diffOI <  0 && $diffPrice >= 0) return 'Short Cover';
        return 'Long Unwind';
    }
}
