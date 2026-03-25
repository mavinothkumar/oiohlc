<?php

namespace App\Services;

use App\Models\BiasSnapshot;
use Illuminate\Support\Collection;

class SnapshotPredictionService
{
    /**
     * Run all strategies against the current snapshot and history.
     * Returns an array of strategy results.
     */
    public function predict(BiasSnapshot $current, Collection $history): array
    {
        return [
            $this->strategyScoreTrend($current, $history),
            $this->strategyOIShift($current, $history),
            $this->strategyVolumeSpike($current, $history),
            $this->strategyBuildUpDominance($current, $history),
            $this->strategyConsolidationBreakout($current, $history),
            $this->strategyReversalDetection($current, $history),
        ];
    }

    /**
     * Aggregate all strategy signals into a single market prediction.
     */
    public function aggregate(array $strategies): array
    {
        $bullish   = 0;
        $bearish   = 0;
        $sideways  = 0;
        $totalConf = 0;
        $triggered = 0;

        foreach ($strategies as $s) {
            if (! $s['triggered']) continue;
            $triggered++;
            $totalConf += $s['confidence'];

            if ($s['signal'] === 'BULLISH')  $bullish++;
            elseif ($s['signal'] === 'BEARISH') $bearish++;
            else $sideways++;
        }

        if ($triggered === 0) {
            return [
                'signal'     => 'WATCH',
                'confidence' => 0,
                'label'      => '⏳ Watching',
                'reason'     => 'Not enough data yet to generate a signal.',
            ];
        }

        $avgConf = round($totalConf / $triggered);

        if ($bullish > $bearish && $bullish > $sideways) {
            return ['signal' => 'BULLISH',  'confidence' => $avgConf, 'label' => '🟢 Bullish',  'reason' => "$bullish of $triggered strategies are bullish."];
        } elseif ($bearish > $bullish && $bearish > $sideways) {
            return ['signal' => 'BEARISH',  'confidence' => $avgConf, 'label' => '🔴 Bearish',  'reason' => "$bearish of $triggered strategies are bearish."];
        } else {
            return ['signal' => 'SIDEWAYS', 'confidence' => $avgConf, 'label' => '🟡 Sideways', 'reason' => "Mixed signals across $triggered strategies."];
        }
    }

    // ──────────────────────────────────────────────
    //  STRATEGY 1 — Score Trend (last N snapshots)
    // ──────────────────────────────────────────────
    private function strategyScoreTrend(BiasSnapshot $current, Collection $history): array
    {
        $base = ['strategy' => 'ScoreTrend', 'triggered' => false, 'signal' => 'WATCH',
                 'confidence' => 0, 'label' => '', 'reason' => ''];

        if ($history->count() < 2) return $base;

        $scores = $history->pluck('bias_score')->slice(-5)->values();
        $first  = $scores->first();
        $last   = $scores->last();
        $diff   = $last - $first;

        if (abs($diff) < 5) return $base;

        $signal     = $diff > 0 ? 'BULLISH' : 'BEARISH';
        $confidence = min(100, abs($diff) * 2);

        return array_merge($base, [
            'triggered'  => true,
            'signal'     => $signal,
            'confidence' => $confidence,
            'label'      => $signal === 'BULLISH' ? '📈 Score Rising' : '📉 Score Falling',
            'reason'     => "Bias score moved from $first → $last (Δ$diff) over last " . $scores->count() . " snapshots.",
        ]);
    }

    // ──────────────────────────────────────────────
    //  STRATEGY 2 — OI Shift
    // ──────────────────────────────────────────────
    private function strategyOIShift(BiasSnapshot $current, Collection $history): array
    {
        $base = ['strategy' => 'OIShift', 'triggered' => false, 'signal' => 'WATCH',
                 'confidence' => 0, 'label' => '', 'reason' => ''];

        if ($history->count() < 2) return $base;

        $prev = $history->slice(-2, 1)->first();

        $ceDiff = ($current->ce_long_build_oi + $current->ce_short_cover_oi)
                  - ($prev->ce_long_build_oi    + $prev->ce_short_cover_oi);

        $peDiff = ($current->pe_short_build_oi + $current->pe_long_unwind_oi)
                  - ($prev->pe_short_build_oi    + $prev->pe_long_unwind_oi);

        $netOIBias = $peDiff - $ceDiff;

        if (abs($netOIBias) < 1000) return $base;

        $signal     = $netOIBias > 0 ? 'BULLISH' : 'BEARISH';
        $confidence = min(100, (int)(abs($netOIBias) / 500));

        return array_merge($base, [
            'triggered'  => true,
            'signal'     => $signal,
            'confidence' => $confidence,
            'label'      => $signal === 'BULLISH' ? '📊 OI Bullish Shift' : '📊 OI Bearish Shift',
            'reason'     => "Net OI bias: PE-side +" . number_format($peDiff) . " vs CE-side +" . number_format($ceDiff) . ".",
        ]);
    }

    // ──────────────────────────────────────────────
    //  STRATEGY 3 — Volume Spike
    // ──────────────────────────────────────────────
    private function strategyVolumeSpike(BiasSnapshot $current, Collection $history): array
    {
        $base = ['strategy' => 'VolumeSpike', 'triggered' => false, 'signal' => 'WATCH',
                 'confidence' => 0, 'label' => '', 'reason' => ''];

        if ($history->count() < 3) return $base;

        $avgVol = $history->slice(0, -1)->avg('total_volume');
        if ($avgVol == 0) return $base;

        $ratio = $current->total_volume / $avgVol;

        if ($ratio < 1.5) return $base;

        // Direction is determined by bias score
        $signal     = $current->bias_score >= 0 ? 'BULLISH' : 'BEARISH';
        $confidence = min(100, (int)(($ratio - 1) * 50));

        return array_merge($base, [
            'triggered'  => true,
            'signal'     => $signal,
            'confidence' => $confidence,
            'label'      => '🔥 Volume Spike',
            'reason'     => "Current volume is " . round($ratio, 1) . "x the recent average, bias " . ($signal === 'BULLISH' ? 'bullish' : 'bearish') . ".",
        ]);
    }

    // ──────────────────────────────────────────────
    //  STRATEGY 4 — Build-Up Dominance
    // ──────────────────────────────────────────────
    private function strategyBuildUpDominance(BiasSnapshot $current, Collection $history): array
    {
        $base = ['strategy' => 'BuildUpDominance', 'triggered' => false, 'signal' => 'WATCH',
                 'confidence' => 0, 'label' => '', 'reason' => ''];

        $bullOI = $current->pe_short_build_oi  + $current->pe_long_unwind_oi
                  + $current->ce_short_cover_oi  + $current->ce_long_build_oi;

        $bearOI = $current->ce_short_build_oi  + $current->ce_long_unwind_oi
                  + $current->pe_long_build_oi   + $current->pe_short_cover_oi;

        $total = $bullOI + $bearOI;
        if ($total == 0) return $base;

        $bullPct = ($bullOI / $total) * 100;
        $bearPct = ($bearOI / $total) * 100;

        if (abs($bullPct - $bearPct) < 15) return $base;

        $signal     = $bullPct > $bearPct ? 'BULLISH' : 'BEARISH';
        $confidence = min(100, (int) abs($bullPct - $bearPct));

        return array_merge($base, [
            'triggered'  => true,
            'signal'     => $signal,
            'confidence' => $confidence,
            'label'      => $signal === 'BULLISH' ? '💪 Bullish Build-Up' : '🐻 Bearish Build-Up',
            'reason'     => "Bullish OI: " . round($bullPct, 1) . "% vs Bearish OI: " . round($bearPct, 1) . "%.",
        ]);
    }

    // ──────────────────────────────────────────────
    //  STRATEGY 5 — Consolidation / Breakout
    // ──────────────────────────────────────────────
    private function strategyConsolidationBreakout(BiasSnapshot $current, Collection $history): array
    {
        $base = ['strategy' => 'ConsolidationBreakout', 'triggered' => false, 'signal' => 'WATCH',
                 'confidence' => 0, 'label' => '', 'reason' => ''];

        if ($history->count() < 3) return $base;

        // Look at last 4 snapshots (20 mins) for tight range
        $scores = $history->pluck('bias_score')->slice(-4)->values();
        $min    = $scores->min();
        $max    = $scores->max();
        $range  = $max - $min;

        // Tight band: market was consolidating
        $isConsolidating = $range <= 10;

        // Current snapshot breaks out of that band
        $latestScore  = $current->bias_score;
        $didBreakout  = $latestScore > $max + 3 || $latestScore < $min - 3;

        if (! $isConsolidating || ! $didBreakout) return $base;

        // Determine breakout direction using OI confirmation
        $ceBullOI  = $current->ce_long_build_oi  + $current->ce_short_cover_oi;
        $peBullOI  = $current->pe_short_build_oi  + $current->pe_long_unwind_oi;
        $oiBullish = ($peBullOI + $ceBullOI) > 0;

        $signal     = ($latestScore > $max + 3) ? 'BULLISH' : 'BEARISH';
        $confidence = min(100, 50 + (int) abs($latestScore - ($signal === 'BULLISH' ? $max : $min)) * 3);

        return array_merge($base, [
            'triggered'  => true,
            'signal'     => $signal,
            'confidence' => $confidence,
            'label'      => $signal === 'BULLISH' ? '🚀 Bullish Breakout' : '💥 Bearish Breakout',
            'reason'     => "Score consolidated in range [$min–$max] for 4 snapshots, then broke " . ($signal === 'BULLISH' ? 'above' : 'below') . " to $latestScore.",
        ]);
    }

    // ──────────────────────────────────────────────
    //  STRATEGY 6 — Reversal Detection
    // ──────────────────────────────────────────────
    private function strategyReversalDetection(BiasSnapshot $current, Collection $history): array
    {
        $base = ['strategy' => 'ReversalDetection', 'triggered' => false, 'signal' => 'WATCH',
                 'confidence' => 0, 'label' => '', 'reason' => ''];

        if ($history->count() < 4) return $base;

        $scores = $history->pluck('bias_score')->slice(-5)->values();

        // Detect V-shape (down then up) or inverted V (up then down)
        $n    = $scores->count();
        $peak = $scores->max();
        $trough = $scores->min();

        $peakIdx   = $scores->search($peak);
        $troughIdx = $scores->search($trough);

        // Bullish reversal: score was falling (trough near end) then current rises
        $bullishReversal = $troughIdx >= ($n - 2)
                           && $current->bias_score > $trough + 8
                           && ($peak - $trough) >= 10;

        // Bearish reversal: score was rising (peak near end) then current falls
        $bearishReversal = $peakIdx >= ($n - 2)
                           && $current->bias_score < $peak - 8
                           && ($peak - $trough) >= 10;

        if (! $bullishReversal && ! $bearishReversal) return $base;

        $signal     = $bullishReversal ? 'BULLISH' : 'BEARISH';
        $confidence = min(100, (int) abs($current->bias_score - ($bullishReversal ? $trough : $peak)) * 4);

        return array_merge($base, [
            'triggered'  => true,
            'signal'     => $signal,
            'confidence' => $confidence,
            'label'      => $signal === 'BULLISH' ? '🔄 Bullish Reversal' : '🔄 Bearish Reversal',
            'reason'     => $bullishReversal
                ? "Score hit trough of $trough recently, now recovering to {$current->bias_score}."
                : "Score peaked at $peak recently, now declining to {$current->bias_score}.",
        ]);
    }
}
