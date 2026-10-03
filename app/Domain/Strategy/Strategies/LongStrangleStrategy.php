<?php

namespace App\Domain\Strategy\Strategies;

use App\Domain\Greeks\Calculators\BlackScholesCalculator;
use App\Domain\Strategy\Contracts\StrategyCharacteristics;
use App\Domain\Strategy\Contracts\StrategyInterface;
use App\Domain\Strategy\Contracts\ValidationResult;

class LongStrangleStrategy implements StrategyInterface
{
    public function key(): string { return 'long_strangle'; }
    public function name(): string { return 'Long Strangle'; }
    public function description(): string { return 'Buy OTM Put and OTM Call. Lower cost volatility explosion strategy with defined risk.'; }
    public function category(): string { return 'VOLATILITY'; }

    public function characteristics(): StrategyCharacteristics
    {
        return new StrategyCharacteristics('DELTA_NEUTRAL', true, false, 2, 'LONG_VOL', 'NEGATIVE_THETA', ['HIGH_VOLATILITY', 'TREND']);
    }

    public function defaultLegTemplates(float $spot, float $strikeStep = 50.0, float $dte = 3.0, float $iv = 0.14, int $quantity = 65): array
    {
        $atm = round($spot / $strikeStep) * $strikeStep;
        $peStrike = $atm - ($strikeStep * 2);
        $ceStrike = $atm + ($strikeStep * 2);
        $t = max($dte / 365.0, 0.001);
        $pePrice = BlackScholesCalculator::calculate('PE', $spot, $peStrike, $t, $iv)->theoreticalPrice;
        $cePrice = BlackScholesCalculator::calculate('CE', $spot, $ceStrike, $t, $iv)->theoreticalPrice;

        return [
            ['side' => 'BUY', 'option_type' => 'PE', 'strike' => (float)$peStrike, 'entry_price' => round($pePrice, 2), 'current_price' => round($pePrice, 2), 'quantity' => $quantity, 'dte' => $dte, 'iv' => $iv, 'status' => 'OPEN'],
            ['side' => 'BUY', 'option_type' => 'CE', 'strike' => (float)$ceStrike, 'entry_price' => round($cePrice, 2), 'current_price' => round($cePrice, 2), 'quantity' => $quantity, 'dte' => $dte, 'iv' => $iv, 'status' => 'OPEN'],
        ];
    }

    public function validate(array $legs): ValidationResult
    {
        if (count($legs) !== 2) return ValidationResult::invalid(['Long Strangle requires exactly 2 BUY legs.']);
        return ValidationResult::valid();
    }
}
