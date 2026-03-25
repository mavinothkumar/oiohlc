<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BuildUpAnalysisController extends Controller
{
    public function index(Request $request)
    {
        $date    = $request->input('date', Carbon::today()->toDateString());
        $strikes = (int) $request->input('strikes', 2);

        $emptyDefaults = [
            'date'              => $date,
            'strikes'           => $strikes,
            'expiryDate'        => null,
            'expiry'            => null,
            'spotPrice'         => 0,
            'nearestStrike'     => 0,
            'strikeList'        => [],
            'buildUpTotals'     => $this->emptyBuildUpTotals(),
            'chartCE_OI'        => [],
            'chartPE_OI'        => [],
            'chartCE_Vol'       => [],
            'chartPE_Vol'       => [],
            'chartLabels'       => [],
            'bias'              => null,
            'biasScore'         => 0,
            'biasStrength'      => null,
            'bullishOI'         => 0,
            'bearishOI'         => 0,
            'recentWindows'     => [],
            'sentimentTimeline' => [],
        ];

        // 1. Get active expiry
        $expiry = DB::table('nse_expiries')
                    ->where('trading_symbol', 'NIFTY')
                    ->where('instrument_type', 'OPT')
                    ->where('is_current', 1)
                    ->first();

        if (!$expiry) {
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

        // 2. Get latest spot price
        $latest = DB::table('option_chains')
                    ->where('trading_symbol', 'NIFTY')
                    ->where('expiry', $expiryDate)
                    ->orderByDesc('captured_at')
                    ->first(['underlying_spot_price']);

        if (!$latest) {
            return view('build-up-analysis', array_merge($emptyDefaults, [
                'expiryDate' => $expiryDate,
                'expiry'     => $expiry,
                'emptyState' => [
                    'icon'    => '📭',
                    'title'   => 'No Option Chain Data',
                    'message' => 'Option chain data for NIFTY has not been populated yet for ' . $expiryDate . '.',
                    'hint'    => 'Data starts flowing in after market opens at 09:15 AM IST.',
                ],
            ]));
        }

        // 3. Strikes
        $spotPrice     = $latest->underlying_spot_price;
        $nearestStrike = round($spotPrice / 50) * 50;
        $strikeList    = [];
        for ($i = -$strikes; $i <= $strikes; $i++) {
            $strikeList[] = $nearestStrike + ($i * 50);
        }

        // 4. Fetch full-day rows
        $startTime = $date . ' 09:15:00';
        $endTime   = $date . ' 15:30:00';

        $rows = DB::table('option_chains')
                  ->where('trading_symbol', 'NIFTY')
                  ->where('expiry', $expiryDate)
                  ->whereIn('strike_price', $strikeList)
                  ->whereBetween('captured_at', [$startTime, $endTime])
                  ->orderBy('captured_at')
                  ->get(['strike_price', 'option_type', 'diff_oi', 'diff_volume', 'diff_ltp', 'build_up', 'captured_at']);

        // 5. Full-day build-up totals
        $buildUpTotals = $this->emptyBuildUpTotals();
        foreach ($rows as $row) {
            $this->accumulateRow($row, $buildUpTotals);
        }

        // 6. Bias calculation (full day)
        [$bullishOI, $bearishOI, $biasScore, $bias, $biasStrength] = $this->calcBias($buildUpTotals);

        // 7. Chart data (full day grouped bar)
        $chartLabels = ['Long Build', 'Short Build', 'Short Cover', 'Long Unwind'];
        $chartCE_OI  = array_column($buildUpTotals['CE'], 'oi');
        $chartPE_OI  = array_column($buildUpTotals['PE'], 'oi');
        $chartCE_Vol = array_column($buildUpTotals['CE'], 'volume');
        $chartPE_Vol = array_column($buildUpTotals['PE'], 'volume');

        // 8. Recent windows: last 15 min and last 30 min
        $latestCaptured = $rows->last()?->captured_at ?? null;
        $recentWindows  = [];

        if ($latestCaptured) {
            $latestTs = Carbon::parse($latestCaptured);

            foreach ([15 => 'Last 15 Min', 30 => 'Last 30 Min'] as $mins => $label) {
                $windowStart = $latestTs->copy()->subMinutes($mins)->toDateTimeString();
                $windowTotals = $this->emptyBuildUpTotals();

                foreach ($rows as $row) {
                    if ($row->captured_at >= $windowStart) {
                        $this->accumulateRow($row, $windowTotals);
                    }
                }

                [, , $wScore, $wBias, $wStrength] = $this->calcBias($windowTotals);

                $recentWindows[$label] = [
                    'totals'   => $windowTotals,
                    'score'    => $wScore,
                    'bias'     => $wBias,
                    'strength' => $wStrength,
                    'ce_oi'    => array_column($windowTotals['CE'], 'oi'),
                    'pe_oi'    => array_column($windowTotals['PE'], 'oi'),
                    'ce_vol'   => array_column($windowTotals['CE'], 'volume'),
                    'pe_vol'   => array_column($windowTotals['PE'], 'volume'),
                ];
            }
        }

        // 9. Sentiment timeline (full day, bucketed every 15 min)
        $sentimentTimeline = $this->buildSentimentTimeline($rows, $date);

        return view('build-up-analysis', compact(
            'date', 'strikes', 'expiry', 'expiryDate',
            'spotPrice', 'nearestStrike', 'strikeList',
            'buildUpTotals', 'chartLabels',
            'chartCE_OI', 'chartPE_OI', 'chartCE_Vol', 'chartPE_Vol',
            'bias', 'biasScore', 'biasStrength', 'bullishOI', 'bearishOI',
            'recentWindows', 'sentimentTimeline'
        ));
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function emptyBuildUpTotals(): array
    {
        $keys = ['Long Build', 'Short Build', 'Short Cover', 'Long Unwind'];
        $empty = [];
        foreach (['CE', 'PE'] as $type) {
            foreach ($keys as $k) {
                $empty[$type][$k] = ['oi' => 0, 'volume' => 0];
            }
        }
        return $empty;
    }

    private function accumulateRow(object $row, array &$totals): void
    {
        $diffOi  = $row->diff_oi     ?? 0;
        $diffLtp = $row->diff_ltp    ?? 0;
        $diffVol = $row->diff_volume ?? 0;
        $type    = $row->option_type;
        $buildUp = $row->build_up ?? $this->classifyBuildUp($diffOi, $diffLtp);

        if ($buildUp && isset($totals[$type][$buildUp])) {
            $totals[$type][$buildUp]['oi']     += abs($diffOi);
            $totals[$type][$buildUp]['volume'] += abs($diffVol);
        }
    }

    private function calcBias(array $buildUpTotals): array
    {
        $bullishOI =
            ($buildUpTotals['CE']['Long Build']['oi']  * 2) +
            ($buildUpTotals['CE']['Short Cover']['oi'] * 1) +
            ($buildUpTotals['PE']['Short Build']['oi'] * 2) +
            ($buildUpTotals['PE']['Long Unwind']['oi'] * 1);

        $bearishOI =
            ($buildUpTotals['CE']['Short Build']['oi'] * 2) +
            ($buildUpTotals['CE']['Long Unwind']['oi'] * 1) +
            ($buildUpTotals['PE']['Long Build']['oi']  * 2) +
            ($buildUpTotals['PE']['Short Cover']['oi'] * 1);

        $total     = $bullishOI + $bearishOI;
        $biasScore = $total > 0 ? round((($bullishOI - $bearishOI) / $total) * 100) : 0;

        $bias = match (true) {
            $biasScore > 20  => 'Bullish',
            $biasScore < -20 => 'Bearish',
            default          => 'Sideways',
        };

        $biasStrength = match (true) {
            abs($biasScore) >= 60 => 'Strong',
            abs($biasScore) >= 35 => 'Moderate',
            default               => 'Weak',
        };

        return [$bullishOI, $bearishOI, $biasScore, $bias, $biasStrength];
    }

    /**
     * Build a 15-min bucketed sentiment score timeline for the full day.
     * Returns array of ['time' => 'HH:MM', 'score' => int, 'bias' => string]
     */
    private function buildSentimentTimeline($rows, string $date): array
    {
        $buckets = [];

        foreach ($rows as $row) {
            $ts     = Carbon::parse($row->captured_at);
            // floor to nearest 15-min bucket
            $bucket = $ts->copy()->floorMinutes(15)->format('H:i');
            if (!isset($buckets[$bucket])) {
                $buckets[$bucket] = $this->emptyBuildUpTotals();
            }
            $this->accumulateRow($row, $buckets[$bucket]);
        }

        ksort($buckets);

        $timeline = [];
        foreach ($buckets as $time => $totals) {
            [, , $score, $bias] = $this->calcBias($totals);
            $timeline[] = ['time' => $time, 'score' => $score, 'bias' => $bias];
        }

        return $timeline;
    }

    private function classifyBuildUp(int|float $diffOi, int|float $diffLtp): ?string
    {
        if ($diffOi == 0 || $diffLtp == 0) return null;
        if ($diffOi > 0 && $diffLtp > 0)  return 'Long Build';
        if ($diffOi > 0 && $diffLtp < 0)  return 'Short Build';
        if ($diffOi < 0 && $diffLtp < 0)  return 'Long Unwind';
        if ($diffOi < 0 && $diffLtp > 0)  return 'Short Cover';
        return null;
    }
}
