<?php

namespace App\Domain\Risk\Services;

use App\Domain\Greeks\DTOs\GreekSet;
use App\Domain\Risk\DTOs\ExposureMetrics;
use App\Domain\Risk\DTOs\RiskStateResult;

class RiskEngine
{
    /**
     * Compute comprehensive exposure metrics.
     */
    public function calculateExposure(array $legsGreeks, GreekSet $netGreeks): ExposureMetrics
    {
        $absDelta = 0.0;
        $longVal = 0.0;
        $shortVal = 0.0;
        $ceVal = 0.0;
        $peVal = 0.0;
        $contributions = [];

        $totalAbsDeltaSum = 0.0;
        foreach ($legsGreeks as $lg) {
            $totalAbsDeltaSum += abs($lg['position_greeks']['delta']);
        }

        foreach ($legsGreeks as $lg) {
            $side = strtoupper($lg['side']);
            $opt = strtoupper($lg['option_type']);
            $posG = $lg['position_greeks'];
            $qty = $lg['quantity'];
            $price = $lg['contract_greeks']['theoretical_price'] ?? 0.0;
            $legVal = $price * $qty;

            $absDelta += abs($posG['delta']);

            if ($side === 'BUY') {
                $longVal += $legVal;
            } else {
                $shortVal += $legVal;
            }

            if ($opt === 'CE') {
                $ceVal += $legVal;
            } else {
                $peVal += $legVal;
            }

            $deltaPct = $totalAbsDeltaSum > 0.001
                ? round((abs($posG['delta']) / $totalAbsDeltaSum) * 100, 1)
                : 0.0;

            $contributions[] = [
                'index' => $lg['index'],
                'side' => $side,
                'option_type' => $opt,
                'strike' => $lg['strike'],
                'quantity' => $qty,
                'delta' => round($posG['delta'], 2),
                'delta_contribution_pct' => $deltaPct,
                'gamma' => round($posG['gamma'], 5),
                'theta' => round($posG['theta'], 2),
                'vega' => round($posG['vega'], 2),
                'current_value' => round($legVal, 2),
            ];
        }

        return new ExposureMetrics(
            netGreeks: $netGreeks,
            absoluteDelta: $absDelta,
            longExposureValue: $longVal,
            shortExposureValue: $shortVal,
            ceExposureValue: $ceVal,
            peExposureValue: $peVal,
            legContributions: $contributions
        );
    }

    /**
     * Compare inception assumptions against current live parameters.
     */
    public function trackOriginalAssumptions(array $entrySnapshot, array $currentSnapshot): array
    {
        $entrySpot = (float)($entrySnapshot['spot'] ?? 0.0);
        $currSpot = (float)($currentSnapshot['spot'] ?? $entrySpot);
        $spotDiff = $currSpot - $entrySpot;
        $spotDiffPct = $entrySpot > 0 ? ($spotDiff / $entrySpot) * 100 : 0.0;

        $entryIv = (float)($entrySnapshot['iv'] ?? 0.0);
        $currIv = (float)($currentSnapshot['iv'] ?? $entryIv);
        $ivDiff = $currIv - $entryIv;

        $entryDelta = (float)($entrySnapshot['delta'] ?? 0.0);
        $currDelta = (float)($currentSnapshot['delta'] ?? 0.0);
        $deltaDiff = $currDelta - $entryDelta;

        $entryGamma = (float)($entrySnapshot['gamma'] ?? 0.0);
        $currGamma = (float)($currentSnapshot['gamma'] ?? 0.0);

        $entryTheta = (float)($entrySnapshot['theta'] ?? 0.0);
        $currTheta = (float)($currentSnapshot['theta'] ?? 0.0);

        $entryVega = (float)($entrySnapshot['vega'] ?? 0.0);
        $currVega = (float)($currentSnapshot['vega'] ?? 0.0);

        $entryDte = (float)($entrySnapshot['dte'] ?? 0.0);
        $currDte = (float)($currentSnapshot['dte'] ?? $entryDte);

        $entryExpectedMove = (float)($entrySnapshot['expected_move'] ?? 0.0);
        $currExpectedMove = (float)($currentSnapshot['expected_move'] ?? $entryExpectedMove);

        return [
            'spot' => [
                'entry' => round($entrySpot, 2),
                'current' => round($currSpot, 2),
                'change' => round($spotDiff, 2),
                'change_pct' => round($spotDiffPct, 2),
            ],
            'iv' => [
                'entry' => round($entryIv * 100, 2),
                'current' => round($currIv * 100, 2),
                'change' => round($ivDiff * 100, 2),
            ],
            'delta' => [
                'entry' => round($entryDelta, 2),
                'current' => round($currDelta, 2),
                'change' => round($deltaDiff, 2),
            ],
            'gamma' => [
                'entry' => round($entryGamma, 5),
                'current' => round($currGamma, 5),
                'change' => round($currGamma - $entryGamma, 5),
            ],
            'theta' => [
                'entry' => round($entryTheta, 2),
                'current' => round($currTheta, 2),
                'change' => round($currTheta - $entryTheta, 2),
            ],
            'vega' => [
                'entry' => round($entryVega, 2),
                'current' => round($currVega, 2),
                'change' => round($currVega - $entryVega, 2),
            ],
            'dte' => [
                'entry' => $entryDte,
                'current' => $currDte,
                'change' => $currDte - $entryDte,
            ],
            'expected_move' => [
                'entry' => round($entryExpectedMove, 2),
                'current' => round($currExpectedMove, 2),
                'change' => round($currExpectedMove - $entryExpectedMove, 2),
            ],
        ];
    }
}
