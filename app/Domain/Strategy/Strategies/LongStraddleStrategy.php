<?php

namespace App\Domain\Strategy\Strategies;

use App\Domain\Greeks\Calculators\BlackScholesCalculator;
use App\Domain\Strategy\Contracts\StrategyCharacteristics;
use App\Domain\Strategy\Contracts\StrategyInterface;
use App\Domain\Strategy\Contracts\ValidationResult;

class LongStraddleStrategy implements StrategyInterface
{
    public function key(): string
    {
        return 'long_straddle';
    }

    public function name(): string
    {
        return 'Long Straddle';
    }

    public function description(): string
    {
        return 'Buy ATM Call and ATM Put of the same strike and expiry. Profitable when underlying moves sharply in either direction or volatility expands.';
    }

    public function category(): string
    {
        return 'VOLATILITY';
    }

    public function characteristics(): StrategyCharacteristics
    {
        return new StrategyCharacteristics(
            bias: 'DELTA_NEUTRAL',
            isDefinedRisk: true,
            isCredit: false,
            legCount: 2,
            volatilityExposure: 'LONG_VOL',
            thetaProfile: 'NEGATIVE_THETA',
            suitableRegimes: ['HIGH_VOLATILITY', 'TREND']
        );
    }

    public function defaultLegTemplates(
        float $spot,
        float $strikeStep = 50.0,
        float $dte = 3.0,
        float $iv = 0.14,
        int $quantity = 65
    ): array {
        $atmStrike = round($spot / $strikeStep) * $strikeStep;
        $t = max($dte / 365.0, 0.001);

        $cePrice = BlackScholesCalculator::calculate('CE', $spot, $atmStrike, $t, $iv)->theoreticalPrice;
        $pePrice = BlackScholesCalculator::calculate('PE', $spot, $atmStrike, $t, $iv)->theoreticalPrice;

        return [
            [
                'side' => 'BUY',
                'option_type' => 'CE',
                'strike' => (float)$atmStrike,
                'entry_price' => round($cePrice, 2),
                'current_price' => round($cePrice, 2),
                'quantity' => $quantity,
                'dte' => $dte,
                'iv' => $iv,
                'status' => 'OPEN',
            ],
            [
                'side' => 'BUY',
                'option_type' => 'PE',
                'strike' => (float)$atmStrike,
                'entry_price' => round($pePrice, 2),
                'current_price' => round($pePrice, 2),
                'quantity' => $quantity,
                'dte' => $dte,
                'iv' => $iv,
                'status' => 'OPEN',
            ],
        ];
    }

    public function validate(array $legs): ValidationResult
    {
        if (count($legs) !== 2) {
            return ValidationResult::invalid(['Long Straddle requires exactly 2 legs (1 CE, 1 PE).']);
        }
        foreach ($legs as $leg) {
            if (strtoupper($leg['side'] ?? '') !== 'BUY') {
                return ValidationResult::invalid(['Both legs of a Long Straddle must have side BUY.']);
            }
        }
        return ValidationResult::valid();
    }
}
