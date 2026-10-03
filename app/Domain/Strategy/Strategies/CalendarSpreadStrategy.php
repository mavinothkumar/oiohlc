<?php

namespace App\Domain\Strategy\Strategies;

use App\Domain\Greeks\Calculators\BlackScholesCalculator;
use App\Domain\Strategy\Contracts\StrategyCharacteristics;
use App\Domain\Strategy\Contracts\StrategyInterface;
use App\Domain\Strategy\Contracts\ValidationResult;

class CalendarSpreadStrategy implements StrategyInterface
{
    public function key(): string { return 'calendar_spread'; }
    public function name(): string { return 'Calendar Spread'; }
    public function description(): string { return 'Sell near-term expiry option and buy longer-term expiry option at the same strike. Exploits faster near-term time decay.'; }
    public function category(): string { return 'INCOME'; }

    public function characteristics(): StrategyCharacteristics
    {
        return new StrategyCharacteristics('DELTA_NEUTRAL', true, false, 2, 'LONG_VOL', 'POSITIVE_THETA', ['RANGE', 'LOW_VOLATILITY']);
    }

    public function defaultLegTemplates(float $spot, float $strikeStep = 50.0, float $dte = 3.0, float $iv = 0.14, int $quantity = 65): array
    {
        $atm = round($spot / $strikeStep) * $strikeStep;
        $tNear = max($dte / 365.0, 0.001);
        $tFar = max(($dte + 7.0) / 365.0, 0.001);
        $pNear = BlackScholesCalculator::calculate('CE', $spot, $atm, $tNear, $iv)->theoreticalPrice;
        $pFar = BlackScholesCalculator::calculate('CE', $spot, $atm, $tFar, $iv)->theoreticalPrice;

        return [
            ['side' => 'SELL', 'option_type' => 'CE', 'strike' => (float)$atm, 'entry_price' => round($pNear, 2), 'current_price' => round($pNear, 2), 'quantity' => $quantity, 'dte' => $dte, 'iv' => $iv, 'status' => 'OPEN'],
            ['side' => 'BUY', 'option_type' => 'CE', 'strike' => (float)$atm, 'entry_price' => round($pFar, 2), 'current_price' => round($pFar, 2), 'quantity' => $quantity, 'dte' => $dte + 7.0, 'iv' => $iv, 'status' => 'OPEN'],
        ];
    }

    public function validate(array $legs): ValidationResult
    {
        if (count($legs) < 2) return ValidationResult::invalid(['Calendar spread requires near and far legs.']);
        return ValidationResult::valid();
    }
}
