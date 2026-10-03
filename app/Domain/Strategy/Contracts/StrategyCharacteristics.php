<?php

namespace App\Domain\Strategy\Contracts;

class StrategyCharacteristics
{
    public function __construct(
        public readonly string $bias, // DELTA_NEUTRAL, BULLISH, BEARISH, VOLATILITY
        public readonly bool $isDefinedRisk, // true for spreads/condors, false for naked/straddles
        public readonly bool $isCredit, // net credit or debit
        public readonly int $legCount,
        public readonly string $volatilityExposure, // LONG_VOL, SHORT_VOL, BALANCED
        public readonly string $thetaProfile, // POSITIVE_THETA, NEGATIVE_THETA, BALANCED
        public readonly array $suitableRegimes = []
    ) {}

    public function toArray(): array
    {
        return [
            'bias' => $this->bias,
            'is_defined_risk' => $this->isDefinedRisk,
            'is_credit' => $this->isCredit,
            'leg_count' => $this->legCount,
            'volatility_exposure' => $this->volatilityExposure,
            'theta_profile' => $this->thetaProfile,
            'suitable_regimes' => $this->suitableRegimes,
        ];
    }
}
