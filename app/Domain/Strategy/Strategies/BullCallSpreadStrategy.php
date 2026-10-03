<?php

namespace App\Domain\Strategy\Strategies;

use App\Domain\Greeks\Calculators\BlackScholesCalculator;
use App\Domain\Strategy\Contracts\StrategyCharacteristics;
use App\Domain\Strategy\Contracts\StrategyInterface;
use App\Domain\Strategy\Contracts\ValidationResult;

class BullCallSpreadStrategy implements StrategyInterface
{
    public function key(): string
    {
        return 'bull_call_spread';
    }

    public function name(): string
    {
        return 'Bull Call Spread';
    }

    public function description(): string
    {
        return 'Buy lower strike Call and sell higher strike Call. Defined-risk bullish directional play with lower upfront debit and capped profit.';
    }

    public function category(): string
    {
        return 'BULLISH';
    }

    public function characteristics(): StrategyCharacteristics
    {
        return new StrategyCharacteristics(
            bias: 'BULLISH',
            isDefinedRisk: true,
            isCredit: false,
            legCount: 2,
            volatilityExposure: 'BALANCED',
            thetaProfile: 'BALANCED',
            suitableRegimes: ['TREND']
        );
    }

    public function defaultLegTemplates(
        float $spot,
        float $strikeStep = 50.0,
        float $dte = 3.0,
        float $iv = 0.14,
        int $quantity = 65
    ): array {
        $atm = round($spot / $strikeStep) * $strikeStep;
        $longStrike = $atm;
        $shortStrike = $atm + ($strikeStep * 2);
        $t = max($dte / 365.0, 0.001);

        $longPrice = BlackScholesCalculator::calculate('CE', $spot, $longStrike, $t, $iv)->theoreticalPrice;
        $shortPrice = BlackScholesCalculator::calculate('CE', $spot, $shortStrike, $t, $iv)->theoreticalPrice;

        return [
            [
                'side' => 'BUY',
                'option_type' => 'CE',
                'strike' => (float)$longStrike,
                'entry_price' => round($longPrice, 2),
                'current_price' => round($longPrice, 2),
                'quantity' => $quantity,
                'dte' => $dte,
                'iv' => $iv,
                'status' => 'OPEN',
            ],
            [
                'side' => 'SELL',
                'option_type' => 'CE',
                'strike' => (float)$shortStrike,
                'entry_price' => round($shortPrice, 2),
                'current_price' => round($shortPrice, 2),
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
            return ValidationResult::invalid(['Bull Call Spread requires exactly 2 Call legs.']);
        }
        foreach ($legs as $leg) {
            if (strtoupper($leg['option_type'] ?? '') !== 'CE') {
                return ValidationResult::invalid(['Both legs must be Call options (CE).']);
            }
        }
        return ValidationResult::valid();
    }
}
