<?php

namespace App\Domain\Scenario\Services;

use App\Domain\Greeks\Calculators\BlackScholesCalculator;
use App\Domain\Scenario\DTOs\ScenarioCell;
use App\Domain\Scenario\DTOs\ScenarioMatrix;

class ScenarioEngine
{
    /**
     * Generate full Spot × IV scenario grid.
     */
    public function generateMatrix(
        array $legs,
        float $spot,
        ?array $spotChanges = null,
        ?array $ivChanges = null,
        int $daysPassed = 0,
        float $rate = 0.07
    ): ScenarioMatrix {
        $spotChanges = $spotChanges ?? config('scenarios.spot_steps', [-200, -100, -50, 0, 50, 100, 200]);
        $ivChanges = $ivChanges ?? config('scenarios.iv_steps_pct', [-0.05, -0.02, 0.0, 0.02, 0.05]);

        $matrix = [];

        foreach ($spotChanges as $ds) {
            $matrix[(string)$ds] = [];
            $simSpot = max(1.0, $spot + $ds);

            foreach ($ivChanges as $div) {
                $cell = $this->evaluateScenario($legs, $spot, $ds, $div, $daysPassed, $rate);
                $matrix[(string)$ds][(string)$div] = $cell;
            }
        }

        return new ScenarioMatrix(
            spotChanges: $spotChanges,
            ivChanges: $ivChanges,
            daysPassed: $daysPassed,
            matrix: $matrix
        );
    }

    /**
     * Evaluate single hypothetical scenario.
     */
    public function evaluateScenario(
        array $legs,
        float $baseSpot,
        float $spotChange,
        float $ivChangePct,
        int $daysPassed = 0,
        float $rate = 0.07
    ): ScenarioCell {
        $simSpot = max(1.0, $baseSpot + $spotChange);
        $totalPnl = 0.0;
        $totalValue = 0.0;
        $totalDelta = 0.0;
        $totalGamma = 0.0;
        $totalTheta = 0.0;
        $totalVega = 0.0;

        foreach ($legs as $leg) {
            $side = strtoupper($leg['side']);
            $opt = strtoupper($leg['option_type']);
            $strike = (float)$leg['strike'];
            $entryPrice = (float)$leg['entry_price'];
            $qty = abs((int)$leg['quantity']);
            $baseIv = max(0.01, (float)($leg['iv'] ?? 0.15));
            $baseDte = max(0.001, (float)($leg['dte'] ?? 1.0));
            $sign = $side === 'BUY' ? 1.0 : -1.0;

            // Shifted parameters
            $simIv = max(0.01, $baseIv + $ivChangePct);
            $simDte = max(0.0001, $baseDte - $daysPassed);
            $t = $simDte / 365.0;

            $greeks = BlackScholesCalculator::calculate($opt, $simSpot, $strike, $t, $simIv, $rate);
            $simPrice = $greeks->theoreticalPrice;

            $legPnl = $sign * ($simPrice - $entryPrice) * $qty;
            $legVal = ($side === 'BUY' ? 1.0 : -1.0) * $simPrice * $qty;

            $totalPnl += $legPnl;
            $totalValue += $legVal;
            $totalDelta += $greeks->delta * $sign * $qty;
            $totalGamma += $greeks->gamma * $sign * $qty;
            $totalTheta += $greeks->theta * $sign * $qty;
            $totalVega += $greeks->vega * $sign * $qty;
        }

        return new ScenarioCell(
            spotChange: $spotChange,
            simulatedSpot: $simSpot,
            ivChangePct: $ivChangePct,
            daysPassed: $daysPassed,
            pnl: $totalPnl,
            positionValue: $totalValue,
            delta: $totalDelta,
            gamma: $totalGamma,
            theta: $totalTheta,
            vega: $totalVega
        );
    }
}
