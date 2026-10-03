<?php

namespace App\Domain\Strategy\Strategies;

use App\Domain\Greeks\Calculators\BlackScholesCalculator;
use App\Domain\Strategy\Contracts\StrategyCharacteristics;
use App\Domain\Strategy\Contracts\StrategyInterface;
use App\Domain\Strategy\Contracts\ValidationResult;

class ShortStraddleStrategy implements StrategyInterface
{
    public function key(): string
    {
        return 'short_straddle';
    }

    public function name(): string
    {
        return 'Short Straddle';
    }

    public function description(): string
    {
        return 'Sell ATM Call and ATM Put of the same strike and expiry. Profitable when underlying remains within breakevens as premium decays.';
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
        $atmStrike = round($spot / $strikeStep) * $strikeStep;
        $t = max($dte / 365.0, 0.001);

        $cePrice = BlackScholesCalculator::calculate('CE', $spot, $atmStrike, $t, $iv)->theoreticalPrice;
        $pePrice = BlackScholesCalculator::calculate('PE', $spot, $atmStrike, $t, $iv)->theoreticalPrice;

        return [
            [
                'side' => 'SELL',
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
                'side' => 'SELL',
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
            return ValidationResult::invalid(['Short Straddle requires exactly 2 legs (1 CE, 1 PE).']);
        }

        $ceCount = 0;
        $peCount = 0;
        $strikes = [];
        $sides = [];

        foreach ($legs as $leg) {
            $opt = strtoupper($leg['option_type'] ?? '');
            $side = strtoupper($leg['side'] ?? '');
            $strikes[] = $leg['strike'] ?? null;
            $sides[] = $side;

            if ($opt === 'CE') $ceCount++;
            if ($opt === 'PE') $peCount++;
        }

        if ($ceCount !== 1 || $peCount !== 1) {
            return ValidationResult::invalid(['Short Straddle must consist of 1 CE and 1 PE leg.']);
        }

        if ($sides[0] !== 'SELL' || $sides[1] !== 'SELL') {
            return ValidationResult::invalid(['Both legs of a Short Straddle must have side SELL.']);
        }

        if ($strikes[0] != $strikes[1]) {
            return ValidationResult::invalid(['Both legs of a Short Straddle must share the same ATM strike.']);
        }

        return ValidationResult::valid();
    }
}
