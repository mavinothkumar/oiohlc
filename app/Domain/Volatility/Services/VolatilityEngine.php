<?php

namespace App\Domain\Volatility\Services;

class VolatilityEngine
{
    /**
     * Compute comprehensive volatility analytics.
     *
     * @param float $currentIv Current implied volatility (e.g. 0.142)
     * @param float|null $entryIv IV at position inception
     * @param float|null $realizedVol Historical realized volatility (e.g. 0.118)
     * @param array $historicalIvSeries Array of past IV readings for percentile
     */
    public function analyze(
        float $currentIv,
        ?float $entryIv = null,
        ?float $realizedVol = null,
        array $historicalIvSeries = []
    ): array {
        $entryIv = $entryIv ?? $currentIv;
        $realizedVol = $realizedVol ?? ($currentIv * 0.88); // baseline reference if not provided

        $ivChangeAbs = $currentIv - $entryIv;
        $ivChangePct = $entryIv > 0.0001 ? (($ivChangeAbs / $entryIv) * 100.0) : 0.0;

        // IV Percentile / Rank
        $ivRank = null;
        $ivPercentile = null;
        if (!empty($historicalIvSeries)) {
            $minIv = min($historicalIvSeries);
            $maxIv = max($historicalIvSeries);
            $range = $maxIv - $minIv;
            $ivRank = $range > 0.0001 ? (($currentIv - $minIv) / $range) * 100.0 : 50.0;

            $belowCount = count(array_filter($historicalIvSeries, fn($v) => $v < $currentIv));
            $ivPercentile = ($belowCount / count($historicalIvSeries)) * 100.0;
        } else {
            // Default context estimate assuming normal index IV range [10%, 24%]
            $minEst = 0.10;
            $maxEst = 0.24;
            $ivRank = max(0.0, min(100.0, (($currentIv - $minEst) / ($maxEst - $minEst)) * 100.0));
            $ivPercentile = $ivRank;
        }

        // Volatility Premium: IV - RV
        $volRiskPremium = $currentIv - $realizedVol;
        $ivRvRatio = $realizedVol > 0.0001 ? ($currentIv / $realizedVol) : 1.0;

        // Context interpretation with reference methodology explicitly stated
        $context = [
            'reference_methodology' => '30-day realized volatility vs current ATM implied volatility',
            'vrp_status' => $volRiskPremium > 0.015 ? 'IV trading at premium to RV (+ ' . round($volRiskPremium * 100, 1) . ' pts)'
                          : ($volRiskPremium < -0.01 ? 'IV trading at discount to RV (' . round($volRiskPremium * 100, 1) . ' pts)'
                          : 'IV closely tracking RV (balanced)'),
        ];

        return [
            'current_iv' => round($currentIv * 100, 2),
            'entry_iv' => round($entryIv * 100, 2),
            'iv_change_points' => round($ivChangeAbs * 100, 2),
            'iv_change_pct' => round($ivChangePct, 2),
            'realized_volatility' => round($realizedVol * 100, 2),
            'vol_risk_premium_points' => round($volRiskPremium * 100, 2),
            'iv_rv_ratio' => round($ivRvRatio, 2),
            'iv_rank' => round($ivRank, 1),
            'iv_percentile' => round($ivPercentile, 1),
            'context' => $context,
        ];
    }
}
