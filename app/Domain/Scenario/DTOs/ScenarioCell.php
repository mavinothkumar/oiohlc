<?php

namespace App\Domain\Scenario\DTOs;

class ScenarioCell
{
    public function __construct(
        public readonly float $spotChange,
        public readonly float $simulatedSpot,
        public readonly float $ivChangePct, // e.g. -0.05 for -5%
        public readonly int $daysPassed,
        public readonly float $pnl,
        public readonly float $positionValue,
        public readonly float $delta,
        public readonly float $gamma,
        public readonly float $theta,
        public readonly float $vega
    ) {}

    public function toArray(): array
    {
        return [
            'spot_change' => $this->spotChange,
            'simulated_spot' => round($this->simulatedSpot, 2),
            'iv_change_pct' => round($this->ivChangePct * 100, 1),
            'days_passed' => $this->daysPassed,
            'pnl' => round($this->pnl, 2),
            'position_value' => round($this->positionValue, 2),
            'delta' => round($this->delta, 2),
            'gamma' => round($this->gamma, 5),
            'theta' => round($this->theta, 2),
            'vega' => round($this->vega, 2),
        ];
    }
}
