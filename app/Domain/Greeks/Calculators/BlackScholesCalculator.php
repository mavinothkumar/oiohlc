<?php

namespace App\Domain\Greeks\Calculators;

use App\Domain\Greeks\DTOs\GreekSet;

class BlackScholesCalculator
{
    /**
     * Cumulative normal distribution function (Abramowitz & Stegun formula 7.1.26).
     */
    public static function cnd(float $x): float
    {
        $a1 = 0.254829592;
        $a2 = -0.284496736;
        $a3 = 1.421413741;
        $a4 = -1.453152027;
        $a5 = 1.061405429;
        $p = 0.3275911;

        $sign = ($x < 0) ? -1 : 1;
        $absX = abs($x) / sqrt(2.0);

        $t = 1.0 / (1.0 + $p * $absX);
        $erf = 1.0 - ((((($a5 * $t + $a4) * $t) + $a3) * $t + $a2) * $t + $a1) * $t * exp(-$absX * $absX);

        return 0.5 * (1.0 + $sign * $erf);
    }

    /**
     * Standard normal probability density function: phi(x) = (1 / sqrt(2 * pi)) * exp(-0.5 * x^2)
     */
    public static function npdf(float $x): float
    {
        return (1.0 / sqrt(2.0 * M_PI)) * exp(-0.5 * $x * $x);
    }

    /**
     * Calculate option price and Greeks for a single contract.
     *
     * @param string $optionType 'CE' or 'PE'
     * @param float $spot Current underlying price
     * @param float $strike Option strike price
     * @param float $timeToExpiry Years to expiry (e.g. DTE / 365)
     * @param float $volatility Implied Volatility (e.g. 0.15 for 15%)
     * @param float $rate Risk-free rate (e.g. 0.07 for 7%)
     * @param float $dividendYield Dividend yield (default 0)
     */
    public static function calculate(
        string $optionType,
        float $spot,
        float $strike,
        float $timeToExpiry,
        float $volatility,
        float $rate = 0.07,
        float $dividendYield = 0.0
    ): GreekSet {
        $optionType = strtoupper($optionType);
        $volatility = max($volatility, 0.0001); // Prevent division by zero
        $t = max($timeToExpiry, 0.00001); // Min expiry fraction ~ a few minutes

        $sqrtT = sqrt($t);
        $d1 = (log($spot / $strike) + ($rate - $dividendYield + 0.5 * $volatility * $volatility) * $t) / ($volatility * $sqrtT);
        $d2 = $d1 - $volatility * $sqrtT;

        $nd1 = self::cnd($d1);
        $nd2 = self::cnd($d2);
        $nNegD1 = self::cnd(-$d1);
        $nNegD2 = self::cnd(-$d2);
        $npdfD1 = self::npdf($d1);

        $discountRate = exp(-$rate * $t);
        $discountDiv = exp(-$dividendYield * $t);

        if ($optionType === 'CE') {
            $price = $spot * $discountDiv * $nd1 - $strike * $discountRate * $nd2;
            $delta = $discountDiv * $nd1;
            // Black-Scholes theta (annualized) -> divided by 365 for 1-day theta
            $thetaAnnual = -($spot * $discountDiv * $npdfD1 * $volatility) / (2.0 * $sqrtT)
                - $rate * $strike * $discountRate * $nd2
                + $dividendYield * $spot * $discountDiv * $nd1;
            $rho = ($strike * $t * $discountRate * $nd2) / 100.0;
        } else {
            $price = $strike * $discountRate * $nNegD2 - $spot * $discountDiv * $nNegD1;
            $delta = -$discountDiv * $nNegD1;
            $thetaAnnual = -($spot * $discountDiv * $npdfD1 * $volatility) / (2.0 * $sqrtT)
                + $rate * $strike * $discountRate * $nNegD2
                - $dividendYield * $spot * $discountDiv * $nNegD1;
            $rho = (-$strike * $t * $discountRate * $nNegD2) / 100.0;
        }

        // Gamma is identical for Call and Put
        $gamma = ($discountDiv * $npdfD1) / ($spot * $volatility * $sqrtT);

        // Vega is identical for Call and Put (annualized -> divided by 100 for 1 percentage point IV move)
        $vega = ($spot * $discountDiv * $sqrtT * $npdfD1) / 100.0;

        $theta = $thetaAnnual / 365.0; // 1-day calendar theta

        return new GreekSet(
            delta: $delta,
            gamma: $gamma,
            theta: $theta,
            vega: $vega,
            rho: $rho,
            theoreticalPrice: max(0.0, $price),
            iv: $volatility
        );
    }

    /**
     * Implied volatility calculation using Newton-Raphson method with bisection fallback.
     */
    public static function impliedVolatility(
        string $optionType,
        float $marketPrice,
        float $spot,
        float $strike,
        float $timeToExpiry,
        float $rate = 0.07,
        float $dividendYield = 0.0
    ): float {
        $optionType = strtoupper($optionType);
        $t = max($timeToExpiry, 0.0001);

        // Intrinsic lower bound
        $intrinsic = $optionType === 'CE'
            ? max(0.0, $spot - $strike)
            : max(0.0, $strike - $spot);

        if ($marketPrice <= $intrinsic) {
            return 0.05; // minimum floor
        }

        // Initial guess (Brenner-Subrahmanyam approximation)
        $sigma = sqrt(2.0 * M_PI / $t) * ($marketPrice / $spot);
        $sigma = max(0.05, min($sigma, 3.0));

        // Newton-Raphson iterations
        for ($i = 0; $i < 40; $i++) {
            $greeks = self::calculate($optionType, $spot, $strike, $t, $sigma, $rate, $dividendYield);
            $diff = $greeks->theoreticalPrice - $marketPrice;

            if (abs($diff) < 0.005) {
                return $sigma;
            }

            $vega = $greeks->vega * 100.0; // raw vega
            if ($vega < 1e-4) {
                break;
            }

            $sigma -= $diff / $vega;
            if ($sigma <= 0.001 || $sigma > 5.0) {
                break;
            }
        }

        // Fallback: Bisection method between 1% and 400%
        $low = 0.01;
        $high = 4.0;
        for ($i = 0; $i < 30; $i++) {
            $mid = ($low + $high) / 2.0;
            $pMid = self::calculate($optionType, $spot, $strike, $t, $mid, $rate, $dividendYield)->theoreticalPrice;
            if (abs($pMid - $marketPrice) < 0.01) {
                return $mid;
            }
            if ($pMid < $marketPrice) {
                $low = $mid;
            } else {
                $high = $mid;
            }
        }

        return max(0.01, min($mid ?? 0.15, 3.0));
    }
}
