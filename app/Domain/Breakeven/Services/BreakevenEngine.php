<?php

namespace App\Domain\Breakeven\Services;

use App\Domain\Breakeven\DTOs\BreakevenResult;
use App\Domain\Pnl\Services\PnlEngine;

class BreakevenEngine
{
    public function __construct(
        protected PnlEngine $pnlEngine = new PnlEngine()
    ) {}

    /**
     * Compute breakevens, max profit, max loss, and regions for any collection of legs.
     *
     * @param array<int, array{
     *     side: string,
     *     option_type: string,
     *     strike: float,
     *     entry_price: float,
     *     quantity: int
     * }> $legs
     * @param float $centerSpot Approximate current spot for range scaling
     * @return BreakevenResult
     */
    public function calculate(array $legs, float $centerSpot): BreakevenResult
    {
        if (empty($legs)) {
            return new BreakevenResult([], 0.0, 0.0, [], [], 0.0);
        }

        // Collect strikes
        $strikes = [];
        $netSlopeFarAbove = 0.0; // as spot -> infinity
        $netSlopeFarBelow = 0.0; // as spot -> 0

        foreach ($legs as $leg) {
            $strikes[] = (float)$leg['strike'];
            $side = strtoupper($leg['side']);
            $opt = strtoupper($leg['option_type']);
            $qty = abs((int)$leg['quantity']);
            $sign = $side === 'BUY' ? 1.0 : -1.0;

            if ($opt === 'CE') {
                $netSlopeFarAbove += ($sign * $qty);
            } elseif ($opt === 'PE') {
                $netSlopeFarBelow += (-$sign * $qty); // As spot drops, put payoff increases
            }
        }

        $minStrike = min($strikes);
        $maxStrike = max($strikes);

        $rangeMin = max(1.0, min($minStrike * 0.7, $centerSpot * 0.7));
        $rangeMax = max($maxStrike * 1.3, $centerSpot * 1.3);

        // Discretize points, ensuring all strikes are explicitly sampled
        $samplePoints = [];
        $step = max(5.0, ($rangeMax - $rangeMin) / 400.0);
        for ($s = $rangeMin; $s <= $rangeMax; $s += $step) {
            $samplePoints[] = $s;
        }
        foreach ($strikes as $strk) {
            $samplePoints[] = $strk;
            $samplePoints[] = $strk - 0.01;
            $samplePoints[] = $strk + 0.01;
        }
        $samplePoints = array_unique($samplePoints);
        sort($samplePoints);

        // Evaluate payoff at all points
        $payoffs = [];
        $globalMinPnl = PHP_FLOAT_MAX;
        $globalMaxPnl = -PHP_FLOAT_MAX;

        foreach ($samplePoints as $spot) {
            $pnl = $this->evaluateExpiryPnl($legs, $spot);
            $payoffs[] = ['spot' => $spot, 'pnl' => $pnl];
            if ($pnl < $globalMinPnl) $globalMinPnl = $pnl;
            if ($pnl > $globalMaxPnl) $globalMaxPnl = $pnl;
        }

        // Find zero crossings (breakevens)
        $breakevens = [];
        $count = count($payoffs);
        for ($i = 0; $i < $count - 1; $i++) {
            $p1 = $payoffs[$i];
            $p2 = $payoffs[$i + 1];

            if (abs($p1['pnl']) < 1e-4) {
                $breakevens[] = $p1['spot'];
                continue;
            }

            if (($p1['pnl'] > 0 && $p2['pnl'] < 0) || ($p1['pnl'] < 0 && $p2['pnl'] > 0)) {
                // Linear root interpolation
                $root = $p1['spot'] - $p1['pnl'] * ($p2['spot'] - $p1['spot']) / ($p2['pnl'] - $p1['pnl']);
                $breakevens[] = $root;
            }
        }

        // Deduplicate close roots (within 1 point)
        $cleanBreakevens = [];
        foreach ($breakevens as $be) {
            $exists = false;
            foreach ($cleanBreakevens as $cbe) {
                if (abs($be - $cbe) < 1.0) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $cleanBreakevens[] = round($be, 2);
            }
        }
        sort($cleanBreakevens);

        // Determine if max profit or max loss is theoretically unlimited (null)
        $hasUnlimitedUpside = ($netSlopeFarAbove > 0.01) || ($netSlopeFarBelow > 0.01);
        $hasUnlimitedDownside = ($netSlopeFarAbove < -0.01) || ($netSlopeFarBelow < -0.01);

        $maxProfit = $hasUnlimitedUpside ? null : $globalMaxPnl;
        $maxLoss = $hasUnlimitedDownside ? null : $globalMinPnl;

        // Profit & Loss Regions
        $profitRegions = [];
        $lossRegions = [];
        if (count($cleanBreakevens) === 2) {
            $midBe = ($cleanBreakevens[0] + $cleanBreakevens[1]) / 2.0;
            $midPnl = $this->evaluateExpiryPnl($legs, $midBe);
            if ($midPnl > 0) {
                $profitRegions[] = ['from' => $cleanBreakevens[0], 'to' => $cleanBreakevens[1]];
                $lossRegions[] = ['from' => '< ' . $cleanBreakevens[0], 'to' => '> ' . $cleanBreakevens[1]];
            } else {
                $lossRegions[] = ['from' => $cleanBreakevens[0], 'to' => $cleanBreakevens[1]];
                $profitRegions[] = ['from' => '< ' . $cleanBreakevens[0], 'to' => '> ' . $cleanBreakevens[1]];
            }
        } elseif (count($cleanBreakevens) === 1) {
            $aboveBe = $cleanBreakevens[0] + 50;
            $abovePnl = $this->evaluateExpiryPnl($legs, $aboveBe);
            if ($abovePnl > 0) {
                $profitRegions[] = ['from' => $cleanBreakevens[0], 'to' => 'Infinity'];
                $lossRegions[] = ['from' => '0', 'to' => $cleanBreakevens[0]];
            } else {
                $lossRegions[] = ['from' => $cleanBreakevens[0], 'to' => 'Infinity'];
                $profitRegions[] = ['from' => '0', 'to' => $cleanBreakevens[0]];
            }
        }

        $riskRewardRatio = null;
        if ($maxProfit !== null && $maxLoss !== null && abs($maxLoss) > 0.01) {
            $riskRewardRatio = abs($maxProfit / $maxLoss);
        }

        return new BreakevenResult(
            breakevens: $cleanBreakevens,
            maxProfit: $maxProfit,
            maxLoss: $maxLoss,
            profitRegions: $profitRegions,
            lossRegions: $lossRegions,
            riskRewardRatio: $riskRewardRatio
        );
    }

    /**
     * Compute total expiry P&L for all legs at given spot.
     */
    public function evaluateExpiryPnl(array $legs, float $spot): float
    {
        $total = 0.0;
        foreach ($legs as $leg) {
            $total += $this->pnlEngine->calculateLegExpiryPayoff(
                side: $leg['side'],
                optionType: $leg['option_type'],
                strike: (float)$leg['strike'],
                entryPrice: (float)$leg['entry_price'],
                quantity: (int)$leg['quantity'],
                spotAtExpiry: $spot
            );
        }
        return $total;
    }
}
