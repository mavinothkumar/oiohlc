<?php

namespace App\Domain\ActionSimulator\Services;

use App\Domain\ActionSimulator\DTOs\ActionSimulationResult;
use App\Domain\Breakeven\Services\BreakevenEngine;
use App\Domain\Greeks\Services\GreeksEngine;
use App\Domain\Pnl\Services\PnlEngine;

class ActionSimulatorEngine
{
    public function __construct(
        protected GreeksEngine $greeksEngine = new GreeksEngine(),
        protected BreakevenEngine $breakevenEngine = new BreakevenEngine(),
        protected PnlEngine $pnlEngine = new PnlEngine()
    ) {}

    /**
     * Simulate a hypothetical action and return Before/After/Change analysis with explanations.
     *
     * @param array $currentLegs Existing position legs
     * @param array{
     *     type: string, // 'roll_strike' | 'add_hedge' | 'close_leg' | 'change_quantity' | 'add_leg' | 'close_position'
     *     leg_index?: int,
     *     new_strike?: float,
     *     new_price?: float,
     *     quantity?: int,
     *     hedge_side?: string,
     *     hedge_type?: string,
     *     hedge_strike?: float,
     *     hedge_price?: float,
     *     new_leg?: array
     * } $action
     * @param float $spot Current underlying price
     * @param float $rate Risk-free rate
     */
    public function simulate(array $currentLegs, array $action, float $spot, float $rate = 0.07): ActionSimulationResult
    {
        $actionType = $action['type'] ?? 'none';
        $modifiedLegs = $currentLegs;
        $description = 'Simulated Adjustment';

        switch ($actionType) {
            case 'roll_strike':
                $idx = (int)($action['leg_index'] ?? 0);
                $newStrike = (float)($action['new_strike'] ?? $spot);
                $newPrice = (float)($action['new_price'] ?? ($modifiedLegs[$idx]['current_price'] ?? 100.0));
                if (isset($modifiedLegs[$idx])) {
                    $description = sprintf("Roll Leg #%d from Strike %.0f to Strike %.0f", $idx + 1, $modifiedLegs[$idx]['strike'], $newStrike);
                    $modifiedLegs[$idx]['strike'] = $newStrike;
                    $modifiedLegs[$idx]['entry_price'] = $newPrice;
                    $modifiedLegs[$idx]['current_price'] = $newPrice;
                }
                break;

            case 'add_hedge':
                $hedgeSide = strtoupper($action['hedge_side'] ?? 'BUY');
                $hedgeType = strtoupper($action['hedge_type'] ?? 'PE');
                $hedgeStrike = (float)($action['hedge_strike'] ?? ($hedgeType === 'PE' ? $spot - 250 : $spot + 250));
                $hedgePrice = (float)($action['hedge_price'] ?? 35.0);
                $hedgeQty = (int)($action['quantity'] ?? ($currentLegs[0]['quantity'] ?? 65));
                $description = sprintf("Add Protective Hedge: %s %s %.0f @ %.2f (Qty: %d)", $hedgeSide, $hedgeType, $hedgeStrike, $hedgePrice, $hedgeQty);

                $modifiedLegs[] = [
                    'side' => $hedgeSide,
                    'option_type' => $hedgeType,
                    'strike' => $hedgeStrike,
                    'entry_price' => $hedgePrice,
                    'current_price' => $hedgePrice,
                    'quantity' => $hedgeQty,
                    'dte' => $currentLegs[0]['dte'] ?? 3.0,
                    'iv' => $currentLegs[0]['iv'] ?? 0.14,
                    'status' => 'OPEN',
                ];
                break;

            case 'close_leg':
                $idx = (int)($action['leg_index'] ?? 0);
                if (isset($modifiedLegs[$idx])) {
                    $description = sprintf("Square off Leg #%d (%s %s %.0f)", $idx + 1, $modifiedLegs[$idx]['side'], $modifiedLegs[$idx]['option_type'], $modifiedLegs[$idx]['strike']);
                    unset($modifiedLegs[$idx]);
                    $modifiedLegs = array_values($modifiedLegs);
                }
                break;

            case 'change_quantity':
                $idx = (int)($action['leg_index'] ?? 0);
                $newQty = (int)($action['quantity'] ?? 1);
                if (isset($modifiedLegs[$idx])) {
                    $description = sprintf("Adjust Leg #%d Quantity from %d to %d", $idx + 1, $modifiedLegs[$idx]['quantity'], $newQty);
                    $modifiedLegs[$idx]['quantity'] = $newQty;
                }
                break;

            case 'add_leg':
                $newLeg = $action['new_leg'] ?? [];
                $description = sprintf("Add New Leg: %s %s %.0f", $newLeg['side'] ?? 'BUY', $newLeg['option_type'] ?? 'CE', $newLeg['strike'] ?? $spot);
                $modifiedLegs[] = $newLeg;
                break;

            case 'close_position':
                $description = "Close Entire Position (Realize current P&L and eliminate all Greek risk)";
                $modifiedLegs = [];
                break;
        }

        // Metrics BEFORE
        $beforeGreeks = $this->greeksEngine->calculatePositionGreeks($currentLegs, $spot, $rate);
        $beforeBE = $this->breakevenEngine->calculate($currentLegs, $spot);
        $beforePnl = $this->pnlEngine->calculatePositionPnl($currentLegs);

        // Metrics AFTER
        $afterGreeks = $this->greeksEngine->calculatePositionGreeks($modifiedLegs, $spot, $rate);
        $afterBE = $this->breakevenEngine->calculate($modifiedLegs, $spot);
        $afterPnl = $this->pnlEngine->calculatePositionPnl($modifiedLegs);

        $beforeData = [
            'delta' => round($beforeGreeks['total']->delta, 2),
            'gamma' => round($beforeGreeks['total']->gamma, 5),
            'theta' => round($beforeGreeks['total']->theta, 2),
            'vega' => round($beforeGreeks['total']->vega, 2),
            'breakevens' => $beforeBE->breakevens,
            'max_profit' => $beforeBE->maxProfit,
            'max_loss' => $beforeBE->maxLoss,
            'net_pnl' => $beforePnl->netPnl,
        ];

        $afterData = [
            'delta' => round($afterGreeks['total']->delta, 2),
            'gamma' => round($afterGreeks['total']->gamma, 5),
            'theta' => round($afterGreeks['total']->theta, 2),
            'vega' => round($afterGreeks['total']->vega, 2),
            'breakevens' => $afterBE->breakevens,
            'max_profit' => $afterBE->maxProfit,
            'max_loss' => $afterBE->maxLoss,
            'net_pnl' => $afterPnl->netPnl,
        ];

        $deltaData = [
            'delta_change' => round($afterData['delta'] - $beforeData['delta'], 2),
            'gamma_change' => round($afterData['gamma'] - $beforeData['gamma'], 5),
            'theta_change' => round($afterData['theta'] - $beforeData['theta'], 2),
            'vega_change' => round($afterData['vega'] - $beforeData['vega'], 2),
            'net_pnl_change' => round($afterData['net_pnl'] - $beforeData['net_pnl'], 2),
        ];

        // Explanations: Improved, Increased Risks, Structural Changes
        $improved = [];
        $increasedRisks = [];
        $structuralChanges = [];

        // Delta improvement check
        if (abs($afterData['delta']) < abs($beforeData['delta'])) {
            $improved[] = sprintf("Directional risk reduced: Net Delta reduced from %.1f to %.1f (%.1f change).", $beforeData['delta'], $afterData['delta'], $deltaData['delta_change']);
        } elseif (abs($afterData['delta']) > abs($beforeData['delta']) + 2.0) {
            $increasedRisks[] = sprintf("Directional exposure expanded: Net Delta shifted from %.1f to %.1f.", $beforeData['delta'], $afterData['delta']);
        }

        // Gamma check
        if (abs($afterData['gamma']) < abs($beforeData['gamma'])) {
            $improved[] = sprintf("Gamma risk compressed: Curvature sensitivity dropped from %.5f to %.5f.", $beforeData['gamma'], $afterData['gamma']);
        } elseif (abs($afterData['gamma']) > abs($beforeData['gamma'])) {
            $increasedRisks[] = sprintf("Gamma increased: Position is more sensitive to sharp underlying moves.", $afterData['gamma']);
        }

        // Theta check
        if ($afterData['theta'] > $beforeData['theta']) {
            $improved[] = sprintf("Daily theta decay increased by +%.2f / day.", $afterData['theta'] - $beforeData['theta']);
        } elseif ($afterData['theta'] < $beforeData['theta']) {
            $increasedRisks[] = sprintf("Theta decay reduced by %.2f / day.", $beforeData['theta'] - $afterData['theta']);
        }

        // Max Loss check
        if ($beforeData['max_loss'] === null && $afterData['max_loss'] !== null) {
            $improved[] = sprintf("Unlimited risk eliminated: Strategy is now defined-risk with Max Loss capped at %.2f.", $afterData['max_loss']);
        } elseif ($beforeData['max_loss'] !== null && $afterData['max_loss'] === null) {
            $increasedRisks[] = "Defined-risk protection removed: Strategy now carries theoretically unlimited downside.";
        }

        // Breakevens shift
        $structuralChanges[] = sprintf(
            "Breakevens shifted from [%s] to [%s].",
            implode(', ', $beforeData['breakevens']),
            implode(', ', $afterData['breakevens'])
        );

        return new ActionSimulationResult(
            actionDescription: $description,
            beforeMetrics: $beforeData,
            afterMetrics: $afterData,
            deltaMetrics: $deltaData,
            improvements: $improved,
            increasedRisks: $increasedRisks,
            structuralChanges: $structuralChanges
        );
    }
}
