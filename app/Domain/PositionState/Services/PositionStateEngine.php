<?php

namespace App\Domain\PositionState\Services;

use App\Domain\Breakeven\DTOs\BreakevenResult;
use App\Domain\Greeks\DTOs\GreekSet;
use App\Domain\Risk\DTOs\RiskStateResult;

class PositionStateEngine
{
    /**
     * Evaluate position risk state and generate transparent human-readable explanations.
     *
     * @param GreekSet $greeks
     * @param BreakevenResult $breakevens
     * @param float $spot Current underlying price
     * @param float $entrySpot Underlying price at entry
     * @param float $iv Current IV
     * @param float $entryIv Entry IV
     * @param float $dte Days to expiry
     * @param float $expectedMove 1-sigma expected move
     * @param float $unrealizedPnl Current unrealized P&L
     * @param float|null $maxLoss Maximum loss (if defined)
     * @param string $status Position status (OPEN, CLOSED, etc.)
     * @param array $customThresholds Configurable overrides
     */
    public function evaluate(
        GreekSet $greeks,
        BreakevenResult $breakevens,
        float $spot,
        float $entrySpot,
        float $iv,
        float $entryIv,
        float $dte,
        float $expectedMove,
        float $unrealizedPnl,
        ?float $maxLoss = null,
        string $status = 'OPEN',
        array $customThresholds = []
    ): RiskStateResult {
        if (strtoupper($status) === 'CLOSED') {
            return new RiskStateResult(
                state: 'CLOSED',
                reasons: ['Position has been squared off and closed.'],
                triggerMetrics: [],
                summary: 'Position closed. Historical analytics preserved.'
            );
        }

        $thresholds = array_merge(config('risk.thresholds', []), $customThresholds);

        $deltaWarn = $thresholds['delta']['warning'] ?? 20.0;
        $deltaCrit = $thresholds['delta']['critical'] ?? 45.0;

        $gammaWarn = $thresholds['gamma']['warning'] ?? 0.005;
        $gammaCrit = $thresholds['gamma']['critical'] ?? 0.012;

        $ivChangeWarn = $thresholds['iv_change_pct']['warning'] ?? 15.0;
        $ivChangeCrit = $thresholds['iv_change_pct']['critical'] ?? 30.0;

        $beWarn = $thresholds['breakeven_proximity_pct']['warning'] ?? 60.0;
        $beCrit = $thresholds['breakeven_proximity_pct']['critical'] ?? 85.0;

        $reasons = [];
        $triggerMetrics = [];
        $stressPoints = 0;
        $cautionPoints = 0;

        // 1. Delta expansion check
        $absDelta = abs($greeks->delta);
        if ($absDelta >= $deltaCrit) {
            $stressPoints += 2;
            $reasons[] = sprintf("Directional net delta (%.1f) has expanded beyond critical threshold (±%.0f).", $greeks->delta, $deltaCrit);
            $triggerMetrics['delta_critical'] = $greeks->delta;
        } elseif ($absDelta >= $deltaWarn) {
            $cautionPoints += 1;
            $reasons[] = sprintf("Net delta (%.1f) has crossed directional monitoring warning threshold (±%.0f).", $greeks->delta, $deltaWarn);
            $triggerMetrics['delta_warning'] = $greeks->delta;
        }

        // 2. Gamma spike check
        $absGamma = abs($greeks->gamma);
        if ($absGamma >= $gammaCrit) {
            $stressPoints += 2;
            $reasons[] = sprintf("Position gamma (%.5f) indicates severe curvature sensitivity to underlying moves.", $greeks->gamma);
            $triggerMetrics['gamma_critical'] = $greeks->gamma;
        } elseif ($absGamma >= $gammaWarn) {
            $cautionPoints += 1;
            $reasons[] = sprintf("Position gamma (%.5f) has risen into warning zone.", $greeks->gamma);
            $triggerMetrics['gamma_warning'] = $greeks->gamma;
        }

        // 3. Breakeven proximity & spot drift check
        if (count($breakevens->breakevens) >= 2) {
            $lowerBe = min($breakevens->breakevens);
            $upperBe = max($breakevens->breakevens);
            $beSpan = max(1.0, $upperBe - $lowerBe);

            if ($spot < $lowerBe) {
                $stressPoints += 3;
                $reasons[] = sprintf("Spot (%.2f) has breached lower breakeven (%.2f) by %.2f points.", $spot, $lowerBe, $lowerBe - $spot);
                $triggerMetrics['breached_lower_be'] = $lowerBe - $spot;
            } elseif ($spot > $upperBe) {
                $stressPoints += 3;
                $reasons[] = sprintf("Spot (%.2f) has breached upper breakeven (%.2f) by %.2f points.", $spot, $upperBe, $spot - $upperBe);
                $triggerMetrics['breached_upper_be'] = $spot - $upperBe;
            } else {
                // Inside BEs: how close to edges?
                $distToLower = $spot - $lowerBe;
                $distToUpper = $upperBe - $spot;
                $minDistToEdge = min($distToLower, $distToUpper);
                $edgeProximityPct = 100.0 - (($minDistToEdge / ($beSpan / 2.0)) * 100.0);

                if ($edgeProximityPct >= $beCrit) {
                    $stressPoints += 2;
                    $reasons[] = sprintf("Spot is %.1f%% of the way toward nearest breakeven boundary.", $edgeProximityPct);
                    $triggerMetrics['be_proximity_pct'] = $edgeProximityPct;
                } elseif ($edgeProximityPct >= $beWarn) {
                    $cautionPoints += 1;
                    $reasons[] = sprintf("Spot has travelled %.1f%% toward outer breakeven cushion.", $edgeProximityPct);
                    $triggerMetrics['be_proximity_pct'] = $edgeProximityPct;
                }
            }
        } elseif (count($breakevens->breakevens) === 1) {
            $be = $breakevens->breakevens[0];
            $dist = abs($spot - $be);
            if ($dist < 40) {
                $cautionPoints += 1;
                $reasons[] = sprintf("Spot (%.2f) is currently trading within %.1f points of solitary breakeven (%.2f).", $spot, $dist, $be);
            }
        }

        // 4. Expected move comparison
        $spotDisplacement = abs($spot - $entrySpot);
        if ($expectedMove > 0 && $spotDisplacement > $expectedMove) {
            $stressPoints += 2;
            $reasons[] = sprintf("Underlying move (%.1f pts) has exceeded 1-sigma expected move (%.1f pts) established at entry.", $spotDisplacement, $expectedMove);
            $triggerMetrics['expected_move_breached'] = $spotDisplacement - $expectedMove;
        } elseif ($expectedMove > 0 && $spotDisplacement > ($expectedMove * 0.75)) {
            $cautionPoints += 1;
            $reasons[] = sprintf("Underlying move (%.1f pts) has consumed over 75%% of anticipated 1-sigma move.", $spotDisplacement);
        }

        // 5. Implied volatility expansion/crush
        if ($entryIv > 0.001) {
            $ivChangePct = (($iv - $entryIv) / $entryIv) * 100.0;
            if (abs($ivChangePct) >= $ivChangeCrit) {
                $stressPoints += 2;
                $reasons[] = sprintf("Implied volatility has shifted by %.1f%% relative to entry (%.1f%% -> %.1f%%).", $ivChangePct, $entryIv * 100, $iv * 100);
                $triggerMetrics['iv_change_critical'] = $ivChangePct;
            } elseif (abs($ivChangePct) >= $ivChangeWarn) {
                $cautionPoints += 1;
                $reasons[] = sprintf("Implied volatility moved %.1f%% away from entry assumptions.", $ivChangePct);
                $triggerMetrics['iv_change_warning'] = $ivChangePct;
            }
        }

        // 6. Expiry DTE pin / gamma acceleration
        if ($dte <= 0.5) {
            $cautionPoints += 1;
            $reasons[] = "Expiry is within 0.5 days; heightened gamma risk and rapid theta decay active.";
        }

        // 7. Max loss drawdown check
        if ($maxLoss !== null && $maxLoss < -1.0) {
            $lossConsumedPct = ($unrealizedPnl < 0) ? (abs($unrealizedPnl) / abs($maxLoss)) * 100.0 : 0.0;
            if ($lossConsumedPct >= 50.0) {
                $stressPoints += 3;
                $reasons[] = sprintf("Unrealized drawdown has reached %.1f%% of maximum defined risk.", $lossConsumedPct);
            }
        }

        // Determine Final State
        if ($stressPoints >= 4 || ($stressPoints >= 2 && $cautionPoints >= 2)) {
            $state = 'RISK_REVIEW';
            $summary = 'Multiple risk dimensions have moved outside the original position assumptions.';
        } elseif ($stressPoints >= 2) {
            $state = 'STRESS';
            $summary = 'Gamma, breakeven proximity, or directional exposure have increased significantly.';
        } elseif ($cautionPoints >= 1) {
            $state = 'CAUTION';
            $summary = 'Directional exposure or volatility drift is increasing beyond standard baseline.';
        } else {
            $state = 'NORMAL';
            $summary = 'Position remains comfortably within configured monitoring boundaries.';
            $reasons = [
                'Greeks and delta exposure remain well within risk tolerance.',
                'Spot price remains safely bounded within breakevens.',
                'No adverse volatility shock detected since position inception.',
            ];
        }

        return new RiskStateResult(
            state: $state,
            reasons: $reasons,
            triggerMetrics: $triggerMetrics,
            summary: $summary
        );
    }
}
