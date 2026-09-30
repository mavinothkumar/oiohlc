<?php

namespace App\Services\Quant;

class ProbabilityEngine
{
    /**
     * Standard Normal Cumulative Distribution Function Phi(x).
     * High precision numerical approximation (Abramowitz and Stegun).
     */
    public static function cdf(float $x): float
    {
        $b1 =  0.319381530;
        $b2 = -0.356563782;
        $b3 =  1.781477937;
        $b4 = -1.821255978;
        $b5 =  1.330274429;
        $p  =  0.2316419;
        $c  =  0.39894228; // 1 / sqrt(2 * pi)

        if ($x >= 0.0) {
            $t = 1.0 / (1.0 + $p * $x);
            return (1.0 - $c * exp(-$x * $x / 2.0) * $t *
                ($t * ($t * ($t * ($t * $b5 + $b4) + $b3) + $b2) + $b1));
        } else {
            $t = 1.0 / (1.0 - $p * $x);
            return ($c * exp(-$x * $x / 2.0) * $t *
                ($t * ($t * ($t * ($t * $b5 + $b4) + $b3) + $b2) + $b1));
        }
    }

    /**
     * Standard Normal Probability Density Function phi(x).
     */
    public static function pdf(float $x): float
    {
        return (1.0 / sqrt(2 * M_PI)) * exp(-0.5 * $x * $x);
    }

    /**
     * Calculate Black-Scholes Delta for Call and Put.
     *
     * @param float $spot Current underlying price
     * @param float $strike Strike price
     * @param float $dte Days to expiry (can be fractional, e.g. 0.5 for intraday)
     * @param float $iv Implied Volatility in percentage (e.g. 13.5 for 13.5%)
     * @param float $rate Risk free rate (annualized, e.g. 0.065 for 6.5%)
     * @return array ['d1' => float, 'd2' => float, 'delta_ce' => float, 'delta_pe' => float, 'pop_ce' => float, 'pop_pe' => float]
     */
    public static function calculateGreeks(
        float $spot,
        float $strike,
        float $dte,
        float $iv,
        float $rate = 0.065
    ): array {
        $t = max($dte, 0.08) / 365.0; // minimum ~2 hours
        $v = max($iv, 1.0) / 100.0;

        $denom = $v * sqrt($t);
        if ($denom <= 0) {
            $denom = 0.0001;
        }

        $d1 = (log($spot / $strike) + ($rate + 0.5 * $v * $v) * $t) / $denom;
        $d2 = $d1 - $denom;

        $deltaCe = self::cdf($d1);
        $deltaPe = $deltaCe - 1.0;

        // Probability of expiring OTM
        // For CE (Strike > Spot), OTM probability is 1 - N(d2)
        // For PE (Strike < Spot), OTM probability is N(d2)
        $popCe = round((1.0 - self::cdf($d2)) * 100, 2);
        $popPe = round(self::cdf($d2) * 100, 2);

        return [
            'd1'       => round($d1, 4),
            'd2'       => round($d2, 4),
            'delta_ce' => round($deltaCe, 4),
            'delta_pe' => round($deltaPe, 4),
            'pop_ce'   => $popCe,
            'pop_pe'   => $popPe,
        ];
    }

    /**
     * Expected Move of Nifty over a specified horizon.
     *
     * @param float $spot
     * @param float $iv Implied volatility or India VIX (e.g. 13.5)
     * @param float $dte Days to expiry
     * @return array ['daily_1sigma' => float, 'expiry_1sigma' => float, 'expiry_1_5sigma' => float, 'expiry_2sigma' => float]
     */
    public static function expectedMove(float $spot, float $iv, float $dte): array
    {
        $v = max($iv, 1.0) / 100.0;
        $dailyMove = $spot * ($v / sqrt(365.0));

        $tDays = max($dte, 0.2);
        $expiryMove = $spot * ($v * sqrt($tDays / 365.0));

        return [
            'daily_1sigma'     => round($dailyMove, 1),
            'expiry_1sigma'    => round($expiryMove, 1),
            'expiry_1_5sigma'  => round($expiryMove * 1.5, 1),
            'expiry_2sigma'    => round($expiryMove * 2.0, 1),
            'upper_1_5sigma'   => round($spot + ($expiryMove * 1.5), 1),
            'lower_1_5sigma'   => round($spot - ($expiryMove * 1.5), 1),
            'upper_2sigma'     => round($spot + ($expiryMove * 2.0), 1),
            'lower_2sigma'     => round($spot - ($expiryMove * 2.0), 1),
        ];
    }

    /**
     * Probability of Profit (POP) for an Iron Condor or Strangle.
     * Range probability that Spot stays between lower short strike and upper short strike.
     */
    public static function condorProbabilityOfProfit(
        float $spot,
        float $lowerShortStrike,
        float $upperShortStrike,
        float $dte,
        float $iv,
        float $rate = 0.065
    ): float {
        $t = max($dte, 0.08) / 365.0;
        $v = max($iv, 1.0) / 100.0;
        $denom = $v * sqrt($t);

        $d2Upper = (log($spot / $upperShortStrike) + ($rate - 0.5 * $v * $v) * $t) / $denom;
        $d2Lower = (log($spot / $lowerShortStrike) + ($rate - 0.5 * $v * $v) * $t) / $denom;

        // Probability of expiring between lower and upper strikes:
        // P(Lower < S_T < Upper) = N(d2Lower) - N(d2Upper)
        $prob = self::cdf($d2Lower) - self::cdf($d2Upper);

        return round(max(0.0, min(100.0, $prob * 100)), 2);
    }

    /**
     * Calculate Cornish-Fisher expansion Value at Risk (VaR) for fat tails.
     *
     * @param array $returns Array of historical trade returns/PnLs
     * @param float $confidence Confidence level, e.g. 0.95 or 0.99
     * @return array
     */
    public static function cornishFisherVaR(array $returns, float $confidence = 0.95): array
    {
        $n = count($returns);
        if ($n < 5) {
            return [
                'mean'             => 0,
                'median'           => 0,
                'std_dev'          => 0,
                'skewness'         => 0,
                'excess_kurtosis'  => 0,
                'gaussian_var'     => 0,
                'cornish_fisher_var'=> 0,
            ];
        }

        $mean = array_sum($returns) / $n;
        sort($returns);
        $mid = (int) floor($n / 2);
        $median = ($n % 2 === 0) ? ($returns[$mid - 1] + $returns[$mid]) / 2.0 : $returns[$mid];

        // Variance, Skewness, Kurtosis
        $sumSq = 0;
        $sumCube = 0;
        $sumQuad = 0;

        foreach ($returns as $r) {
            $diff = $r - $mean;
            $sumSq   += $diff * $diff;
            $sumCube += $diff * $diff * $diff;
            $sumQuad += $diff * $diff * $diff * $diff;
        }

        $variance = $sumSq / ($n - 1);
        $stdDev   = sqrt($variance);

        if ($stdDev < 0.00001) {
            return [
                'mean'             => round($mean, 2),
                'median'           => round($median, 2),
                'std_dev'          => 0,
                'skewness'         => 0,
                'excess_kurtosis'  => 0,
                'gaussian_var'     => 0,
                'cornish_fisher_var'=> 0,
            ];
        }

        $skewness = ($sumCube / $n) / pow($stdDev, 3);
        $kurtosis = ($sumQuad / $n) / pow($stdDev, 4);
        $excessKurtosis = $kurtosis - 3.0; // relative to normal (3.0)

        // Gaussian critical value z_alpha (for 95% = 1.64485, 99% = 2.32635)
        $z = ($confidence >= 0.99) ? 2.32635 : 1.64485;

        // Cornish-Fisher expansion
        $zCf = $z +
            (1.0 / 6.0) * ($z * $z - 1.0) * $skewness +
            (1.0 / 24.0) * ($z * $z * $z - 3.0 * $z) * $excessKurtosis -
            (1.0 / 36.0) * (2.0 * $z * $z * $z - 5.0 * $z) * ($skewness * $skewness);

        $gaussianVaR = -($mean - ($z * $stdDev));
        $cfVaR       = -($mean - ($zCf * $stdDev));

        return [
            'mean'              => round($mean, 2),
            'median'            => round($median, 2),
            'std_dev'           => round($stdDev, 2),
            'skewness'          => round($skewness, 3),
            'excess_kurtosis'   => round($excessKurtosis, 3),
            'gaussian_var'      => round(max(0, $gaussianVaR), 2),
            'cornish_fisher_var'=> round(max(0, $cfVaR), 2),
        ];
    }

    /**
     * Monte Carlo resampling of trade outcomes to model drawdown and equity envelope.
     *
     * @param array $pnlList Array of trade PnLs
     * @param int $simulations Number of runs (default 1000)
     * @param int $tradesPerRun Number of trades per simulation (default = count of pnlList)
     * @return array
     */
    public static function monteCarloSimulation(
        array $pnlList,
        int $simulations = 1000,
        int $tradesPerRun = 50
    ): array {
        $n = count($pnlList);
        if ($n < 3) {
            return ['error' => 'Not enough trades for Monte Carlo'];
        }

        $allEndEquities = [];
        $allMaxDrawdowns = [];
        $sampleTrajectories = []; // 20 paths for plotting

        for ($s = 0; $s < $simulations; $s++) {
            $equity = 100000.0; // Start with 1 Lakh base
            $peak = $equity;
            $maxDd = 0.0;
            $trajectory = [$equity];

            for ($t = 0; $t < $tradesPerRun; $t++) {
                $randomTradePnl = $pnlList[random_int(0, $n - 1)];
                $equity += $randomTradePnl;
                if ($equity > $peak) {
                    $peak = $equity;
                }
                $dd = $peak > 0 ? (($peak - $equity) / $peak) * 100.0 : 0.0;
                if ($dd > $maxDd) {
                    $maxDd = $dd;
                }

                if ($s < 15) {
                    $trajectory[] = round($equity, 0);
                }
            }

            $allEndEquities[] = $equity;
            $allMaxDrawdowns[] = $maxDd;

            if ($s < 15) {
                $sampleTrajectories[] = $trajectory;
            }
        }

        sort($allEndEquities);
        sort($allMaxDrawdowns);

        $p5Eq  = $allEndEquities[(int) floor(0.05 * $simulations)];
        $p50Eq = $allEndEquities[(int) floor(0.50 * $simulations)];
        $p95Eq = $allEndEquities[(int) floor(0.95 * $simulations)];

        $p50Dd = $allMaxDrawdowns[(int) floor(0.50 * $simulations)];
        $p95Dd = $allMaxDrawdowns[(int) floor(0.95 * $simulations)];
        $maxDdObserved = end($allMaxDrawdowns);

        // Probability of Ruin (drawdown > 25%)
        $ruinCount = count(array_filter($allMaxDrawdowns, fn($d) => $d >= 25.0));
        $probRuin = round(($ruinCount / $simulations) * 100, 2);

        return [
            'simulations_run'    => $simulations,
            'trades_per_run'     => $tradesPerRun,
            'median_final_equity'=> round($p50Eq, 0),
            'p5_worst_equity'    => round($p5Eq, 0),
            'p95_best_equity'    => round($p95Eq, 0),
            'median_max_drawdown'=> round($p50Dd, 2),
            'p95_worst_drawdown' => round($p95Dd, 2),
            'worst_case_drawdown'=> round($maxDdObserved, 2),
            'prob_ruin_pct'      => $probRuin,
            'sample_trajectories'=> $sampleTrajectories,
        ];
    }
}
