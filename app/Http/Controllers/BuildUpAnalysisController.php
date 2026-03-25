<?php

namespace App\Http\Controllers;

use App\Models\BiasSnapshot;
use App\Services\SnapshotPredictionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BuildUpAnalysisController extends Controller
{
    public function __construct(
        protected SnapshotPredictionService $predictionService,
    ) {}

    public function index(Request $request)
    {
        $date    = $request->input('date', Carbon::today()->toDateString());
        $strikes = (int) $request->input('strikes', 2);

        $emptyDefaults = [
            'date'          => $date,
            'strikes'       => $strikes,
            'expiryDate'    => null,
            'expiry'        => null,
            'spotPrice'     => 0,
            'nearestStrike' => 0,
            'strikeList'    => [],
            'buildUpTotals' => $this->emptyBuildUpTotals(),
            'chartLabels'   => ['Long Build', 'Short Build', 'Short Cover', 'Long Unwind'],
            'chartCE_OI'    => [0, 0, 0, 0],
            'chartPE_OI'    => [0, 0, 0, 0],
            'chartCE_Vol'   => [0, 0, 0, 0],
            'chartPE_Vol'   => [0, 0, 0, 0],
            'bias'          => null,
            'biasScore'     => 0,
            'biasStrength'  => null,
            'bullishOI'     => 0,
            'bearishOI'     => 0,
            'prediction'    => [
                'signal'     => 'WATCH',
                'confidence' => 0,
                'label'      => '⏳ Watching',
                'reason'     => 'No snapshots available yet.',
            ],
            'strategies'    => [],
            'session'       => $this->predictionService->evaluateSession('NIFTY'),
        ];

        // ── 1. Get current active expiry ──────────────────────────────
        $expiry = DB::table('nse_expiries')
                    ->where('trading_symbol', 'NIFTY')
                    ->where('instrument_type', 'OPT')
                    ->where('is_current', 1)
                    ->first();

        if (! $expiry) {
            return view('build-up-analysis', array_merge($emptyDefaults, [
                'emptyState' => [
                    'icon'    => '🕐',
                    'title'   => 'Market Not Opened Yet',
                    'message' => 'No active expiry found for NIFTY.',
                    'hint'    => 'Expiry data is usually available from 09:15 AM on trading days.',
                ],
            ]));
        }

        $expiryDate = $expiry->expiry_date;

        // ── 2. Get latest spot price ──────────────────────────────────
        $latest = DB::table('option_chains')
                    ->where('trading_symbol', 'NIFTY')
                    ->where('expiry', $expiryDate)
                    ->orderByDesc('captured_at')
                    ->first(['underlying_spot_price']);

        if (! $latest) {
            return view('build-up-analysis', array_merge($emptyDefaults, [
                'expiryDate' => $expiryDate,
                'expiry'     => $expiry,
                'emptyState' => [
                    'icon'    => '📭',
                    'title'   => 'No Option Chain Data',
                    'message' => "Option chain data for NIFTY has not been populated yet for $expiryDate.",
                    'hint'    => 'Data starts flowing in after market opens at 09:15 AM IST.',
                ],
            ]));
        }

        // ── 3. Compute ATM strike & strike list ───────────────────────
        $spotPrice     = $latest->underlying_spot_price;
        $nearestStrike = round($spotPrice / 50) * 50;

        $strikeList = [];
        for ($i = -$strikes; $i <= $strikes; $i++) {
            $strikeList[] = $nearestStrike + ($i * 50);
        }

        // ── 4. Load BiasSnapshot history ──────────────────────────────
        //      ✅ Read pre-computed snapshots — no OI re-computation here
        $history = BiasSnapshot::where('trading_symbol', 'NIFTY')
                               ->whereDate('date', $date)
                               ->orderBy('captured_at')
                               ->get();

        // ── 5. Build chart data from latest snapshot ──────────────────
        //      ✅ Use the last saved snapshot for display, not raw chains
        $buildUpTotals = $this->emptyBuildUpTotals();
        $bias          = null;
        $biasScore     = 0;
        $biasStrength  = null;
        $bullishOI     = 0;
        $bearishOI     = 0;

        if ($history->isNotEmpty()) {
            $latest_snap = $history->last();

            $buildUpTotals = [
                'CE' => [
                    'Long Build'  => ['oi' => $latest_snap->ce_long_build_oi,  'volume' => $latest_snap->ce_long_build_vol],
                    'Short Build' => ['oi' => $latest_snap->ce_short_build_oi, 'volume' => $latest_snap->ce_short_build_vol],
                    'Short Cover' => ['oi' => $latest_snap->ce_short_cover_oi, 'volume' => $latest_snap->ce_short_cover_vol],
                    'Long Unwind' => ['oi' => $latest_snap->ce_long_unwind_oi, 'volume' => $latest_snap->ce_long_unwind_vol],
                ],
                'PE' => [
                    'Long Build'  => ['oi' => $latest_snap->pe_long_build_oi,  'volume' => $latest_snap->pe_long_build_vol],
                    'Short Build' => ['oi' => $latest_snap->pe_short_build_oi, 'volume' => $latest_snap->pe_short_build_vol],
                    'Short Cover' => ['oi' => $latest_snap->pe_short_cover_oi, 'volume' => $latest_snap->pe_short_cover_vol],
                    'Long Unwind' => ['oi' => $latest_snap->pe_long_unwind_oi, 'volume' => $latest_snap->pe_long_unwind_vol],
                ],
            ];

            $bias         = $latest_snap->bias;
            $biasScore    = $latest_snap->bias_score;
            $biasStrength = $latest_snap->bias_strength;
            $bullishOI    = $latest_snap->bullish_oi;
            $bearishOI    = $latest_snap->bearish_oi;
        }

        // ── 6. ✅ Fixed chart data using collect()->pluck() ───────────
        $chartLabels = ['Long Build', 'Short Build', 'Short Cover', 'Long Unwind'];
        $chartCE_OI  = collect($buildUpTotals['CE'])->pluck('oi')->values()->toArray();
        $chartPE_OI  = collect($buildUpTotals['PE'])->pluck('oi')->values()->toArray();
        $chartCE_Vol = collect($buildUpTotals['CE'])->pluck('volume')->values()->toArray();
        $chartPE_Vol = collect($buildUpTotals['PE'])->pluck('volume')->values()->toArray();

        // ── 7. Run strategies & prediction ───────────────────────────
        $prediction = [
            'signal'     => 'WATCH',
            'confidence' => 0,
            'label'      => '⏳ Watching',
            'reason'     => 'No snapshots saved yet. Snapshots are captured every 5 minutes.',
        ];
        $strategies = [];

        if ($history->isNotEmpty()) {
            $current    = $history->last();
            $strategies = $this->predictionService->predict($current, $history);
            $prediction = $this->predictionService->aggregate($strategies);
        }

        // ── 8. Session-level picture ──────────────────────────────────
        $session = $this->predictionService->evaluateSession('NIFTY');

        // ── 9. Return view ────────────────────────────────────────────
        return view('build-up-analysis', compact(
            'date', 'strikes', 'expiry', 'expiryDate',
            'spotPrice', 'nearestStrike', 'strikeList',
            'buildUpTotals',
            'chartLabels', 'chartCE_OI', 'chartPE_OI', 'chartCE_Vol', 'chartPE_Vol',
            'bias', 'biasScore', 'biasStrength',
            'bullishOI', 'bearishOI',
            'prediction', 'strategies', 'session',
        ));
    }

    private function emptyBuildUpTotals(): array
    {
        $empty = ['oi' => 0, 'volume' => 0];
        return [
            'CE' => ['Long Build' => $empty, 'Short Build' => $empty, 'Short Cover' => $empty, 'Long Unwind' => $empty],
            'PE' => ['Long Build' => $empty, 'Short Build' => $empty, 'Short Cover' => $empty, 'Long Unwind' => $empty],
        ];
    }

    private function classifyBuildUp(int|float $diffOi, int|float $diffLtp): ?string
    {
        if ($diffOi == 0 || $diffLtp == 0) return null;
        if ($diffOi > 0 && $diffLtp > 0)   return 'Long Build';
        if ($diffOi > 0 && $diffLtp < 0)   return 'Short Build';
        if ($diffOi < 0 && $diffLtp < 0)   return 'Long Unwind';
        if ($diffOi < 0 && $diffLtp > 0)   return 'Short Cover';
        return null;
    }
}
