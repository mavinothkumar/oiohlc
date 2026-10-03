<?php

namespace App\Domain\Payoff\DTOs;

class PayoffPoint
{
    public function __construct(
        public readonly float $spot,
        public readonly float $pnlExpiry,
        public readonly float $pnlT0 = 0.0,
        public readonly float $delta = 0.0,
        public readonly float $gamma = 0.0,
        public readonly float $theta = 0.0,
        public readonly float $vega = 0.0
    ) {}

    public function toArray(): array
    {
        return [
            'spot' => round($this->spot, 2),
            'pnl_expiry' => round($this->pnlExpiry, 2),
            'pnl_t0' => round($this->pnlT0, 2),
            'delta' => round($this->delta, 4),
            'gamma' => round($this->gamma, 6),
            'theta' => round($this->theta, 4),
            'vega' => round($this->vega, 4),
        ];
    }
}
