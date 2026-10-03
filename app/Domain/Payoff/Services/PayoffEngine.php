<?php

namespace App\Domain\Payoff\Services;

use App\Domain\Breakeven\Services\BreakevenEngine;
use App\Domain\Greeks\Calculators\BlackScholesCalculator;
use App\Domain\Payoff\DTOs\PayoffPoint;
use App\Domain\Payoff\DTOs\PayoffResult;
use App\Domain\Pnl\Services\PnlEngine;

class PayoffEngine
{
    public function __construct(
        protected PnlEngine $pnlEngine = new PnlEngine(),
        protected BreakevenEngine $breakevenEngine = new BreakevenEngine()
    ) {}

    /**
     * Generate complete payoff curve for any strategy legs.
     *
     * @param array $legs Normalized legs
     * @param float $spot Current underlying price
     * @param float|null $minSpot Range lower bound (optional)
     * @param float|null $maxSpot Range upper bound (optional)
     * @param int $numPoints Number of plot points (default 100)
     * @param float $rate Risk free rate
     * @return PayoffResult
     */
    public function generate(
        array $legs,
        float $spot,
        ?float $minSpot = null,
        ?float $maxSpot = null,
        int $numPoints = 80,
        float $rate = 0.07
    ): PayoffResult {
        if (empty($legs)) {
            return new PayoffResult([], $this->breakevenEngine->calculate([], $spot), $spot, 0.0, []);
        }

        $strikes = [];
        foreach ($legs as $l) {
            $strikes[] = (float)$l['strike'];
        }
        $strikes = array_unique($strikes);
        sort($strikes);

        $minStrike = min($strikes);
        $maxStrike = max($strikes);

        $rangePadding = max(200.0, ($maxStrike - $minStrike) * 0.5, $spot * 0.04);
        $start = $minSpot ?? max(1.0, min($minStrike, $spot) - $rangePadding);
        $end = $maxSpot ?? (max($maxStrike, $spot) + $rangePadding);

        // Compute breakevens
        $beResult = $this->breakevenEngine->calculate($legs, $spot);

        // Gather key spots to make sure they are on the curve
        $keySpots = array_merge([$spot, $start, $end], $strikes, $beResult->breakevens);
        $step = ($end - $start) / max(10, $numPoints);

        $spots = [];
        for ($s = $start; $s <= $end; $s += $step) {
            $spots[] = $s;
        }
        foreach ($keySpots as $ks) {
            if ($ks >= $start && $ks <= $end) {
                $spots[] = $ks;
            }
        }
        $spots = array_unique(array_map(fn($v) => round($v, 2), $spots));
        sort($spots);

        $points = [];
        $currentPnl = 0.0;

        foreach ($spots as $testSpot) {
            $expiryPnl = 0.0;
            $t0Pnl = 0.0;
            $deltaTotal = 0.0;
            $gammaTotal = 0.0;
            $thetaTotal = 0.0;
            $vegaTotal = 0.0;

            foreach ($legs as $leg) {
                $side = strtoupper($leg['side']);
                $opt = strtoupper($leg['option_type']);
                $strike = (float)$leg['strike'];
                $entryPrice = (float)$leg['entry_price'];
                $qty = abs((int)$leg['quantity']);
                $dte = max(0.001, (float)($leg['dte'] ?? 1.0));
                $iv = max(0.01, (float)($leg['iv'] ?? 0.15));
                $sign = $side === 'BUY' ? 1.0 : -1.0;

                // Expiry P&L
                $expiryPnl += $this->pnlEngine->calculateLegExpiryPayoff($side, $opt, $strike, $entryPrice, $qty, $testSpot);

                // T+0 P&L: Theoretical Black-Scholes price at testSpot
                $t = $dte / 365.0;
                $greeks = BlackScholesCalculator::calculate($opt, $testSpot, $strike, $t, $iv, $rate);
                $theoPrice = $greeks->theoreticalPrice;

                $legT0 = $sign * ($theoPrice - $entryPrice) * $qty;
                $t0Pnl += $legT0;

                // Greek contributions at testSpot
                $deltaTotal += $greeks->delta * $sign * $qty;
                $gammaTotal += $greeks->gamma * $sign * $qty;
                $thetaTotal += $greeks->theta * $sign * $qty;
                $vegaTotal += $greeks->vega * $sign * $qty;
            }

            if (abs($testSpot - $spot) < ($step / 2.0)) {
                $currentPnl = $t0Pnl;
            }

            $points[] = new PayoffPoint(
                spot: $testSpot,
                pnlExpiry: $expiryPnl,
                pnlT0: $t0Pnl,
                delta: $deltaTotal,
                gamma: $gammaTotal,
                theta: $thetaTotal,
                vega: $vegaTotal
            );
        }

        return new PayoffResult(
            points: $points,
            breakevenResult: $beResult,
            currentSpot: $spot,
            currentPnl: $currentPnl,
            strikes: $strikes
        );
    }
}
