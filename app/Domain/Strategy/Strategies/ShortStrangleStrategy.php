<?php

namespace App\Domain\Strategy\Strategies;

use App\Domain\Greeks\Calculators\BlackScholesCalculator;
use App\Domain\Strategy\Contracts\StrategyCharacteristics;
use App\Domain\Strategy\Contracts\StrategyInterface;
use App\Domain\Strategy\Contracts\ValidationResult;

class ShortStrangleStrategy implements StrategyInterface
{
    public function key(): string
    {
        return 'short_strangle';
    }

    public function name(): string
    {
        return 'Short Strangle';
    }

    public function description(): string
    {
        return 'Sell OTM Put and OTM Call with wider breakevens than a straddle. Generates premium in sideways/low-volatility markets.';
    }

    public function category(): string
    {
        return 'INCOME';
    }

    public function characteristics(): StrategyCharacteristics
    {
        return new StrategyCharacteristics(
            bias: 'DELTA_NEUTRAL',
            isDefinedRisk: false,
            isCredit: true,
            legCount: 2,
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
        $peStrike = $atm - ($strikeStep * 2);
        $ceStrike = $atm + ($strikeStep * 2);
        $t = max($dte / 365.0, 0.001);

        $pePrice = BlackScholesCalculator::calculate('PE', $spot, $peStrike, $t, $iv)->theoreticalPrice;
        $cePrice = BlackScholesCalculator::calculate('CE', $spot, $ceStrike, $t, $iv)->theoreticalPrice;

        return [
            [
                'side' => 'SELL',
                'option_type' => 'PE',
                'strike' => (float)$peStrike,
                'entry_price' => round($pePrice, 2),
                'current_price' => round($pePrice, 2),
                'quantity' => $quantity,
                'dte' => $dte,
                'iv' => $iv,
                'status' => 'OPEN',
            ],
            [
                'side' => 'SELL',
                'option_type' => 'CE',
                'strike' => (float)$ceStrike,
                'entry_price' => round($cePrice, 2),
                'current_price' => round($cePrice, 2),
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
            return ValidationResult::invalid(['Short Strangle requires exactly 2 legs (1 OTM PE, 1 OTM CE).']);
        }
        return ValidationResult::valid();
    }
}
