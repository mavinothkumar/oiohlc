<?php

namespace App\Domain\Greeks\Services;

use App\Domain\Greeks\Calculators\BlackScholesCalculator;
use App\Domain\Greeks\DTOs\GreekSet;

class GreeksEngine
{
    /**
     * Calculate individual leg Greeks based on option parameters and side.
     *
     * @param string $side 'BUY' or 'SELL'
     * @param string $optionType 'CE' or 'PE'
     * @param float $spot
     * @param float $strike
     * @param float $dte Days to expiry (can be fractional)
     * @param float $iv Implied Volatility (e.g. 0.15)
     * @param int $quantity Absolute quantity (e.g. 65)
     * @param float $rate Risk free rate
     * @return array{per_unit: GreekSet, position: GreekSet, multiplier: int}
     */
    public function calculateLegGreeks(
        string $side,
        string $optionType,
        float $spot,
        float $strike,
        float $dte,
        float $iv,
        int $quantity = 1,
        float $rate = 0.07
    ): array {
        $t = max($dte / 365.0, 0.0001);
        $baseGreeks = BlackScholesCalculator::calculate($optionType, $spot, $strike, $t, $iv, $rate);

        $sign = strtoupper($side) === 'BUY' ? 1.0 : -1.0;
        $qty = abs($quantity);

        // Position Greek = quantity * side * per_unit_greek
        $positionGreeks = new GreekSet(
            delta: $baseGreeks->delta * $sign * $qty,
            gamma: $baseGreeks->gamma * $sign * $qty,
            theta: $baseGreeks->theta * $sign * $qty,
            vega: $baseGreeks->vega * $sign * $qty,
            rho: $baseGreeks->rho * $sign * $qty,
            theoreticalPrice: $baseGreeks->theoreticalPrice,
            iv: $iv
        );

        $unitGreeks = new GreekSet(
            delta: $baseGreeks->delta * $sign,
            gamma: $baseGreeks->gamma * $sign,
            theta: $baseGreeks->theta * $sign,
            vega: $baseGreeks->vega * $sign,
            rho: $baseGreeks->rho * $sign,
            theoreticalPrice: $baseGreeks->theoreticalPrice,
            iv: $iv
        );

        return [
            'raw_contract' => $baseGreeks,
            'per_unit' => $unitGreeks,
            'position' => $positionGreeks,
            'sign' => (int)$sign,
            'quantity' => $qty,
        ];
    }

    /**
     * Calculate aggregated Greeks across all legs of a strategy or position.
     *
     * @param array<int, array{
     *     side: string,
     *     option_type: string,
     *     strike: float,
     *     dte: float,
     *     iv: float,
     *     quantity: int,
     *     price?: float
     * }> $legs
     * @param float $spot
     * @param float $rate
     * @return array{total: GreekSet, legs: array}
     */
    public function calculatePositionGreeks(array $legs, float $spot, float $rate = 0.07): array
    {
        $totalDelta = 0.0;
        $totalGamma = 0.0;
        $totalTheta = 0.0;
        $totalVega = 0.0;
        $totalRho = 0.0;
        $legResults = [];

        foreach ($legs as $index => $leg) {
            $side = strtoupper($leg['side'] ?? 'BUY');
            $optionType = strtoupper($leg['option_type'] ?? 'CE');
            $strike = (float)($leg['strike'] ?? $spot);
            $dte = (float)($leg['dte'] ?? 1.0);
            $iv = (float)($leg['iv'] ?? 0.15);
            $quantity = (int)($leg['quantity'] ?? 1);

            $legGreeks = $this->calculateLegGreeks($side, $optionType, $spot, $strike, $dte, $iv, $quantity, $rate);

            $totalDelta += $legGreeks['position']->delta;
            $totalGamma += $legGreeks['position']->gamma;
            $totalTheta += $legGreeks['position']->theta;
            $totalVega += $legGreeks['position']->vega;
            $totalRho += $legGreeks['position']->rho;

            $legResults[] = [
                'index' => $index,
                'side' => $side,
                'option_type' => $optionType,
                'strike' => $strike,
                'quantity' => $quantity,
                'dte' => $dte,
                'iv' => $iv,
                'contract_greeks' => $legGreeks['raw_contract']->toArray(),
                'unit_greeks' => $legGreeks['per_unit']->toArray(),
                'position_greeks' => $legGreeks['position']->toArray(),
            ];
        }

        $positionTotal = new GreekSet(
            delta: $totalDelta,
            gamma: $totalGamma,
            theta: $totalTheta,
            vega: $totalVega,
            rho: $totalRho
        );

        return [
            'total' => $positionTotal,
            'legs' => $legResults,
        ];
    }
}
