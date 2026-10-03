<?php

namespace App\Domain\Pnl\Services;

use App\Domain\Pnl\DTOs\PnlResult;

class PnlEngine
{
    /**
     * Calculate intrinsic value at expiry.
     */
    public function calculateIntrinsic(string $optionType, float $spot, float $strike): float
    {
        return strtoupper($optionType) === 'CE'
            ? max(0.0, $spot - $strike)
            : max(0.0, $strike - $spot);
    }

    /**
     * Calculate expiry payoff for a single leg.
     */
    public function calculateLegExpiryPayoff(
        string $side,
        string $optionType,
        float $strike,
        float $entryPrice,
        int $quantity,
        float $spotAtExpiry
    ): float {
        $intrinsic = $this->calculateIntrinsic($optionType, $spotAtExpiry, $strike);
        $qty = abs($quantity);

        if (strtoupper($side) === 'BUY') {
            return ($intrinsic - $entryPrice) * $qty;
        } else {
            return ($entryPrice - $intrinsic) * $qty;
        }
    }

    /**
     * Calculate current P&L for a single leg.
     */
    public function calculateLegPnl(
        string $side,
        float $entryPrice,
        float $currentPrice,
        int $quantity,
        string $status = 'OPEN',
        ?float $exitPrice = null
    ): array {
        $qty = abs($quantity);
        $isBuy = strtoupper($side) === 'BUY';
        $isClosed = strtoupper($status) === 'CLOSED';

        if ($isClosed) {
            $effectiveExit = $exitPrice ?? $currentPrice;
            $realizedPnl = $isBuy
                ? ($effectiveExit - $entryPrice) * $qty
                : ($entryPrice - $effectiveExit) * $qty;
            $unrealizedPnl = 0.0;
            $currentVal = 0.0;
        } else {
            $realizedPnl = 0.0;
            $unrealizedPnl = $isBuy
                ? ($currentPrice - $entryPrice) * $qty
                : ($entryPrice - $currentPrice) * $qty;
            $currentVal = $isBuy ? ($currentPrice * $qty) : (-$currentPrice * $qty);
        }

        $grossPnl = $realizedPnl + $unrealizedPnl;
        $costs = $this->estimateTransactionCosts($qty, $entryPrice, $currentPrice, $side);

        return [
            'side' => $side,
            'quantity' => $qty,
            'entry_price' => $entryPrice,
            'current_price' => $currentPrice,
            'gross_pnl' => $grossPnl,
            'realized_pnl' => $realizedPnl,
            'unrealized_pnl' => $unrealizedPnl,
            'current_value' => $currentVal,
            'estimated_costs' => $costs,
            'net_pnl' => $grossPnl - $costs,
        ];
    }

    /**
     * Calculate full Position P&L across all legs.
     */
    public function calculatePositionPnl(array $legs): PnlResult
    {
        $totalGross = 0.0;
        $totalRealized = 0.0;
        $totalUnrealized = 0.0;
        $totalCosts = 0.0;
        $totalCurrentValue = 0.0;
        $totalInitialOutlay = 0.0;
        $breakdowns = [];

        foreach ($legs as $idx => $leg) {
            $side = strtoupper($leg['side'] ?? 'BUY');
            $entryPrice = (float)($leg['entry_price'] ?? 0.0);
            $currentPrice = (float)($leg['current_price'] ?? $entryPrice);
            $quantity = (int)($leg['quantity'] ?? 1);
            $status = strtoupper($leg['status'] ?? 'OPEN');
            $exitPrice = isset($leg['exit_price']) ? (float)$leg['exit_price'] : null;

            $legPnl = $this->calculateLegPnl($side, $entryPrice, $currentPrice, $quantity, $status, $exitPrice);

            $outlay = ($side === 'BUY') ? ($entryPrice * $quantity) : (-$entryPrice * $quantity);
            $totalInitialOutlay += $outlay;

            $totalGross += $legPnl['gross_pnl'];
            $totalRealized += $legPnl['realized_pnl'];
            $totalUnrealized += $legPnl['unrealized_pnl'];
            $totalCosts += $legPnl['estimated_costs'];
            $totalCurrentValue += $legPnl['current_value'];

            $breakdowns[] = array_merge(['index' => $idx], $legPnl);
        }

        $netPnl = $totalGross - $totalCosts;

        return new PnlResult(
            grossPnl: $totalGross,
            estimatedCosts: $totalCosts,
            netPnl: $netPnl,
            realizedPnl: $totalRealized,
            unrealizedPnl: $totalUnrealized,
            currentValue: $totalCurrentValue,
            initialOutlay: $totalInitialOutlay,
            legBreakdowns: $breakdowns
        );
    }

    /**
     * Realistic transaction cost model for Indian options:
     * Brokerage (flat 20/order) + STT (0.0625% on sell) + Exchange turnover + GST.
     */
    public function estimateTransactionCosts(int $quantity, float $entryPrice, float $currentPrice, string $side): float
    {
        $brokerage = config('options.cost_per_order', 20.0);
        $entryTurnover = $quantity * $entryPrice;
        $exitTurnover = $quantity * $currentPrice;

        // STT applies only on sell side in options
        $sttRate = config('options.stt_sell_rate', 0.000625);
        $stt = (strtoupper($side) === 'SELL') ? ($entryTurnover * $sttRate) : ($exitTurnover * $sttRate);

        // Exchange turnover
        $turnoverRate = config('options.exchange_turnover_rate', 0.0005);
        $exchangeCharges = ($entryTurnover + $exitTurnover) * $turnoverRate;

        // GST on (brokerage + exchange charges)
        $gstRate = config('options.gst_rate', 0.18);
        $gst = ($brokerage * 2 + $exchangeCharges) * $gstRate;

        // Stamp duty on buy
        $stampDutyRate = config('options.stamp_duty_buy_rate', 0.00003);
        $stampDuty = (strtoupper($side) === 'BUY') ? ($entryTurnover * $stampDutyRate) : ($exitTurnover * $stampDutyRate);

        return round($brokerage * 2 + $stt + $exchangeCharges + $gst + $stampDuty, 2);
    }
}
