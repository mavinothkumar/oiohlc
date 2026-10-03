<?php

namespace App\Domain\Strategy\Strategies;

use App\Domain\Greeks\Calculators\BlackScholesCalculator;
use App\Domain\Strategy\Contracts\StrategyCharacteristics;
use App\Domain\Strategy\Contracts\StrategyInterface;
use App\Domain\Strategy\Contracts\ValidationResult;

class IronFlyStrategy implements StrategyInterface
{
    public function key(): string
    {
        return 'iron_fly';
    }

    public function name(): string
    {
        return 'Iron Fly';
    }

    public function description(): string
    {
        return 'Sell ATM Straddle (Sell ATM CE + Sell ATM PE) and Buy OTM protective wings (Buy OTM CE + Buy OTM PE). Defined-risk neutral high-reward income structure.';
    }

    public function category(): string
    {
        return 'INCOME';
    }

    public function characteristics(): StrategyCharacteristics
    {
        return new StrategyCharacteristics(
            bias: 'DELTA_NEUTRAL',
            isDefinedRisk: true,
            isCredit: true,
            legCount: 4,
            volatilityExposure: 'SHORT_VOL',
            thetaProfile: 'POSITIVE_THETA',
            suitableRegimes: ['RANGE', 'LOW_VOLATILITY']
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
        $wingDistance = $strikeStep * 4;
        $longPutStrike = $atm - $wingDistance;
        $longCallStrike = $atm + $wingDistance;

        $t = max($dte / 365.0, 0.001);

        $pLongPut = BlackScholesCalculator::calculate('PE', $spot, $longPutStrike, $t, $iv)->theoreticalPrice;
        $pAtmPut  = BlackScholesCalculator::calculate('PE', $spot, $atm, $t, $iv)->theoreticalPrice;
        $pAtmCall = BlackScholesCalculator::calculate('CE', $spot, $atm, $t, $iv)->theoreticalPrice;
        $pLongCall= BlackScholesCalculator::calculate('CE', $spot, $longCallStrike, $t, $iv)->theoreticalPrice;

        return [
            [
                'side' => 'BUY',
                'option_type' => 'PE',
                'strike' => (float)$longPutStrike,
                'entry_price' => round($pLongPut, 2),
                'current_price' => round($pLongPut, 2),
                'quantity' => $quantity,
                'dte' => $dte,
                'iv' => $iv,
                'status' => 'OPEN',
            ],
            [
                'side' => 'SELL',
                'option_type' => 'PE',
                'strike' => (float)$atm,
                'entry_price' => round($pAtmPut, 2),
                'current_price' => round($pAtmPut, 2),
                'quantity' => $quantity,
                'dte' => $dte,
                'iv' => $iv,
                'status' => 'OPEN',
            ],
            [
                'side' => 'SELL',
                'option_type' => 'CE',
                'strike' => (float)$atm,
                'entry_price' => round($pAtmCall, 2),
                'current_price' => round($pAtmCall, 2),
                'quantity' => $quantity,
                'dte' => $dte,
                'iv' => $iv,
                'status' => 'OPEN',
            ],
            [
                'side' => 'BUY',
                'option_type' => 'CE',
                'strike' => (float)$longCallStrike,
                'entry_price' => round($pLongCall, 2),
                'current_price' => round($pLongCall, 2),
                'quantity' => $quantity,
                'dte' => $dte,
                'iv' => $iv,
                'status' => 'OPEN',
            ],
        ];
    }

    public function validate(array $legs): ValidationResult
    {
        if (count($legs) !== 4) {
            return ValidationResult::invalid(['Iron Fly requires exactly 4 legs.']);
        }
        return ValidationResult::valid();
    }
}
