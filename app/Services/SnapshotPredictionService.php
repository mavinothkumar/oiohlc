<?php

namespace App\Services;

use App\Models\BiasSnapshot;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SnapshotPredictionService
{
    private array $phases = [
        'OPENING'   => ['09:15', '10:00'],
        'MORNING'   => ['10:00', '12:00'],
        'AFTERNOON' => ['12:00', '14:00'],
        'CLOSING'   => ['14:00', '15:30'],
    ];

    // ══════════════════════════════════════════════
    //  PUBLIC API
    // ══════════════════════════════════════════════

    /**
     * Run all strategies against the current snapshot and history.
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
     * Aggregate all strategy signals into a single 5-min prediction.
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

            if ($s['signal'] === 'BULLISH')       $bullish++;
            elseif ($s['signal'] === 'BEARISH')   $bearish++;
            else                                   $sideways++;
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

    /**
     * Derive full day-level session picture from today's BiasSnapshot rows.
     * No extra table, no scheduler — just reads what's already saved.
     */
    public function evaluateSession(string $symbol): array
    {
        $today = Carbon::today()->toDateString();

        $snapshots = BiasSnapshot::where('trading_symbol', $symbol)
                                 ->where('date', $today)
                                 ->orderBy('captured_at')
                                 ->get(['bias', 'bias_score', 'captured_at']);

        if ($snapshots->isEmpty()) {
            return [
                'dominant_signal' => 'WATCH',
                'current_signal'  => 'WATCH',
                'trend_state'     => 'STEADY',
                'session_phase'   => $this->currentPhase(),
                'bullish_count'   => 0,
                'bearish_count'   => 0,
                'sideways_count'  => 0,
                'total_snapshots' => 0,
                'avg_score'       => 0,
                'signal_log'      => [],
                'last_updated_at' => null,
            ];
        }

        $total         = $snapshots->count();
        $bullishCount  = $snapshots->where('bias', 'Bullish')->count();
        $bearishCount  = $snapshots->where('bias', 'Bearish')->count();
        $sidewaysCount = $snapshots->where('bias', 'Sideways')->count();
        $avgScore      = round($snapshots->avg('bias_score'), 1);
        $signalLog     = $this->buildSignalLog($snapshots);

        return [
            'dominant_signal' => $this->dominantSignal($bullishCount, $bearishCount, $sidewaysCount, $total),
            'current_signal'  => $this->resolveCurrentSignal($snapshots),
            'trend_state'     => $this->resolveTrendState(count($signalLog), $total),
            'session_phase'   => $this->currentPhase(),
            'bullish_count'   => $bullishCount,
            'bearish_count'   => $bearishCount,
            'sideways_count'  => $sidewaysCount,
            'total_snapshots' => $total,
            'avg_score'       => $avgScore,
            'signal_log'      => $signalLog,
            'last_updated_at' => $snapshots->last()->captured_at->format('H:i:s'),
        ];
    }

    // ══════════════════════════════════════════════
    //  5-MIN STRATEGIES
    // ══════════════════════════════════════════════

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

    private function strategyVolumeSpike(BiasSnapshot $current, Collection $history): array
    {
        $base = ['strategy' => 'VolumeSpike', 'triggered' => false, 'signal' => 'WATCH',
                 'confidence' => 0, 'label' => '', 'reason' => ''];

        if ($history->count() < 3) return $base;

        // ✅ Fixed: rolling 6-snapshot window instead of all-day average
        $avgVol = $history->slice(-7, 6)->avg('total_volume');
        if ($avgVol == 0) return $base;

        $ratio = $current->total_volume / $avgVol;
        if ($ratio < 1.5) return $base;

        $signal     = $current->bias_score >= 0 ? 'BULLISH' : 'BEARISH';
        $confidence = min(100, (int)(($ratio - 1) * 50));

        return array_merge($base, [
            'triggered'  => true,
            'signal'     => $signal,
            'confidence' => $confidence,
            'label'      => '🔥 Volume Spike',
            'reason'     => "Current volume is " . round($ratio, 1) . "x the 30-min average, bias " . ($signal === 'BULLISH' ? 'bullish' : 'bearish') . ".",
        ]);
    }

    private function strategyBuildUpDominance(BiasSnapshot $current, Collection $history): array
    {
        $base = ['strategy' => 'BuildUpDominance', 'triggered' => false, 'signal' => 'WATCH',
                 'confidence' => 0, 'label' => '', 'reason' => ''];

        // ✅ Fixed: use delta vs previous snapshot, not raw cumulative OI
        if ($history->isEmpty()) return $base;

        $prev = $history->last();

        $bullOI = ($current->pe_short_build_oi - $prev->pe_short_build_oi)
                  + ($current->pe_long_unwind_oi  - $prev->pe_long_unwind_oi)
                  + ($current->ce_short_cover_oi  - $prev->ce_short_cover_oi)
                  + ($current->ce_long_build_oi   - $prev->ce_long_build_oi);

        $bearOI = ($current->ce_short_build_oi - $prev->ce_short_build_oi)
                  + ($current->ce_long_unwind_oi  - $prev->ce_long_unwind_oi)
                  + ($current->pe_long_build_oi   - $prev->pe_long_build_oi)
                  + ($current->pe_short_cover_oi  - $prev->pe_short_cover_oi);

        $total = abs($bullOI) + abs($bearOI);
        if ($total == 0) return $base;

        $bullPct = (abs($bullOI) / $total) * 100;
        $bearPct = (abs($bearOI) / $total) * 100;

        if (abs($bullPct - $bearPct) < 15) return $base;

        $signal     = $bullOI > $bearOI ? 'BULLISH' : 'BEARISH';
        $confidence = min(100, (int) abs($bullPct - $bearPct));

        return array_merge($base, [
            'triggered'  => true,
            'signal'     => $signal,
            'confidence' => $confidence,
            'label'      => $signal === 'BULLISH' ? '💪 Bullish Build-Up' : '🐻 Bearish Build-Up',
            'reason'     => "This 5-min candle: Bullish OI Δ " . round($bullPct, 1) . "% vs Bearish OI Δ " . round($bearPct, 1) . "%.",
        ]);
    }

    private function strategyConsolidationBreakout(BiasSnapshot $current, Collection $history): array
    {
        $base = ['strategy' => 'ConsolidationBreakout', 'triggered' => false, 'signal' => 'WATCH',
                 'confidence' => 0, 'label' => '', 'reason' => ''];

        if ($history->count() < 3) return $base;

        $scores = $history->pluck('bias_score')->slice(-4)->values();
        $min    = $scores->min();
        $max    = $scores->max();
        $range  = $max - $min;

        $isConsolidating = $range <= 10;
        $latestScore     = $current->bias_score;
        $didBreakout     = $latestScore > $max + 3 || $latestScore < $min - 3;

        if (! $isConsolidating || ! $didBreakout) return $base;

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

    private function strategyReversalDetection(BiasSnapshot $current, Collection $history): array
    {
        $base = ['strategy' => 'ReversalDetection', 'triggered' => false, 'signal' => 'WATCH',
                 'confidence' => 0, 'label' => '', 'reason' => ''];

        if ($history->count() < 4) return $base;

        $scores = $history->pluck('bias_score')->slice(-5)->values();
        $n      = $scores->count();
        $peak   = $scores->max();
        $trough = $scores->min();

        // ✅ Fixed: find LAST occurrence of peak/trough, not first
        $peakIdx   = $scores->keys()->last(fn($k) => $scores[$k] === $peak);
        $troughIdx = $scores->keys()->last(fn($k) => $scores[$k] === $trough);

        $bullishReversal = $troughIdx >= ($n - 2)
                           && $current->bias_score > $trough + 8
                           && ($peak - $trough) >= 10;

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

    // ══════════════════════════════════════════════
    //  SESSION EVALUATION HELPERS
    // ══════════════════════════════════════════════

    private function dominantSignal(int $bull, int $bear, int $side, int $total): string
    {
        $counts = ['BULLISH' => $bull, 'BEARISH' => $bear, 'SIDEWAYS' => $side];
        arsort($counts);
        $top = array_key_first($counts);

        return ($counts[$top] / $total * 100) >= 40 ? $top : 'SIDEWAYS';
    }

    private function resolveCurrentSignal(Collection $snapshots): string
    {
        $last3 = $snapshots->slice(-3)->pluck('bias')->map(fn($b) => strtoupper($b));

        if ($last3->isEmpty()) return 'WATCH';

        $counts = $last3->countBy()->toArray();
        arsort($counts);
        $top = array_key_first($counts);

        return ($counts[$top] >= 2) ? $top : strtoupper($snapshots->last()->bias);
    }

    private function buildSignalLog(Collection $snapshots): array
    {
        $log  = [];
        $prev = null;

        foreach ($snapshots as $index => $snap) {
            $signal = strtoupper($snap->bias);
            if ($signal !== $prev) {
                $log[] = [
                    'signal'   => $signal,
                    'score'    => $snap->bias_score,
                    'time'     => $snap->captured_at->format('H:i'),
                    'phase'    => $this->phaseAt($snap->captured_at->format('H:i')),
                    'snapshot' => $index + 1,
                ];
                $prev = $signal;
            }
        }

        return $log;
    }

    private function resolveTrendState(int $changes, int $total): string
    {
        if ($total < 5) return 'STEADY';

        return match (true) {
            ($changes / $total) <= 0.10 => 'STEADY',
            ($changes / $total) <= 0.30 => 'TRANSITIONING',
            default                     => 'CHOPPY',
        };
    }

    private function currentPhase(): string
    {
        return $this->phaseAt(Carbon::now()->format('H:i'));
    }

    private function phaseAt(string $time): string
    {
        foreach ($this->phases as $phase => [$start, $end]) {
            if ($time >= $start && $time < $end) return $phase;
        }
        return 'CLOSING';
    }
}
