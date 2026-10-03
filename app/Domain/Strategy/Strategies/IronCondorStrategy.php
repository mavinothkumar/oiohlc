<?php

namespace App\Domain\Strategy\Strategies;

use App\Domain\Greeks\Calculators\BlackScholesCalculator;
use App\Domain\Strategy\Contracts\StrategyCharacteristics;
use App\Domain\Strategy\Contracts\StrategyInterface;
use App\Domain\Strategy\Contracts\ValidationResult;

class IronCondorStrategy implements StrategyInterface
{
    public function key(): string
    {
        return 'iron_condor';
    }

    public function name(): string
    {
        return 'Iron Condor';
    }

    public function description(): string
    {
        return '4-leg defined-risk range strategy: Sell OTM Put spread + Sell OTM Call spread. Profits when market expires within the short strikes.';
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
        $shortPutStrike = $atm - ($strikeStep * 3);
        $longPutStrike  = $shortPutStrike - ($strikeStep * 2);
        $shortCallStrike = $atm + ($strikeStep * 3);
        $longCallStrike  = $shortCallStrike + ($strikeStep * 2);

        $t = max($dte / 365.0, 0.001);

        $pLongPut   = BlackScholesCalculator::calculate('PE', $spot, $longPutStrike, $t, $iv)->theoreticalPrice;
        $pShortPut  = BlackScholesCalculator::calculate('PE', $spot, $shortPutStrike, $t, $iv)->theoreticalPrice;
        $pShortCall = BlackScholesCalculator::calculate('CE', $spot, $shortCallStrike, $t, $iv)->theoreticalPrice;
        $pLongCall  = BlackScholesCalculator::calculate('CE', $spot, $longCallStrike, $t, $iv)->theoreticalPrice;

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
                'strike' => (float)$shortPutStrike,
                'entry_price' => round($pShortPut, 2),
                'current_price' => round($pShortPut, 2),
                'quantity' => $quantity,
                'dte' => $dte,
                'iv' => $iv,
                'status' => 'OPEN',
            ],
            [
                'side' => 'SELL',
                'option_type' => 'CE',
                'strike' => (float)$shortCallStrike,
                'entry_price' => round($pShortCall, 2),
                'current_price' => round($pShortCall, 2),
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
            return ValidationResult::invalid(['Iron Condor requires exactly 4 legs.']);
        }
        return ValidationResult::valid();
    }
}
