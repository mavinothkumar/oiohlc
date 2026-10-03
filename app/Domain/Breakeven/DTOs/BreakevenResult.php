<?php

namespace App\Domain\Breakeven\DTOs;

class BreakevenResult
{
    /**
     * @param array<int, float> $breakevens Array of spot prices where P&L == 0 at expiry
     * @param float|null $maxProfit Maximum possible profit at expiry (null if unlimited)
     * @param float|null $maxLoss Maximum possible loss at expiry (negative number, null if unlimited)
     * @param array<int, array{from: float|string, to: float|string}> $profitRegions Range of spots where P&L > 0
     * @param array<int, array{from: float|string, to: float|string}> $lossRegions Range of spots where P&L < 0
     * @param float|null $riskRewardRatio
     */
    public function __construct(
        public readonly array $breakevens,
        public readonly ?float $maxProfit,
        public readonly ?float $maxLoss,
        public readonly array $profitRegions = [],
        public readonly array $lossRegions = [],
        public readonly ?float $riskRewardRatio = null
    ) {}

    public function toArray(): array
    {
        return [
            'breakevens' => array_map(fn($b) => round($b, 2), $this->breakevens),
            'max_profit' => $this->maxProfit !== null ? round($this->maxProfit, 2) : null,
            'max_loss' => $this->maxLoss !== null ? round($this->maxLoss, 2) : null,
            'profit_regions' => $this->profitRegions,
            'loss_regions' => $this->lossRegions,
            'risk_reward_ratio' => $this->riskRewardRatio !== null ? round($this->riskRewardRatio, 2) : null,
        ];
    }
}
