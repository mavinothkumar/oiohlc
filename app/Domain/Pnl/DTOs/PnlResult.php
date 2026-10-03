<?php

namespace App\Domain\Pnl\DTOs;

class PnlResult
{
    public function __construct(
        public readonly float $grossPnl,
        public readonly float $estimatedCosts,
        public readonly float $netPnl,
        public readonly float $realizedPnl = 0.0,
        public readonly float $unrealizedPnl = 0.0,
        public readonly float $currentValue = 0.0,
        public readonly float $initialOutlay = 0.0, // initial credit (negative) or debit (positive)
        public readonly array $legBreakdowns = []
    ) {}

    public function toArray(): array
    {
        return [
            'gross_pnl' => round($this->grossPnl, 2),
            'estimated_costs' => round($this->estimatedCosts, 2),
            'net_pnl' => round($this->netPnl, 2),
            'realized_pnl' => round($this->realizedPnl, 2),
            'unrealized_pnl' => round($this->unrealizedPnl, 2),
            'current_value' => round($this->currentValue, 2),
            'initial_outlay' => round($this->initialOutlay, 2),
            'leg_breakdowns' => $this->legBreakdowns,
        ];
    }
}
