<?php

namespace App\Domain\Payoff\DTOs;

use App\Domain\Breakeven\DTOs\BreakevenResult;

class PayoffResult
{
    /**
     * @param array<int, PayoffPoint> $points
     * @param BreakevenResult $breakevenResult
     * @param float $currentSpot
     * @param float $currentPnl
     */
    public function __construct(
        public readonly array $points,
        public readonly BreakevenResult $breakevenResult,
        public readonly float $currentSpot,
        public readonly float $currentPnl = 0.0,
        public readonly array $strikes = []
    ) {}

    public function toArray(): array
    {
        return [
            'points' => array_map(fn($p) => $p->toArray(), $this->points),
            'breakevens' => $this->breakevenResult->toArray(),
            'current_spot' => round($this->currentSpot, 2),
            'current_pnl' => round($this->currentPnl, 2),
            'strikes' => $this->strikes,
        ];
    }
}
