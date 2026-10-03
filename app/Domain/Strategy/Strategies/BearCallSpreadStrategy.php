<?php

namespace App\Domain\Strategy\Strategies;

use App\Domain\Greeks\Calculators\BlackScholesCalculator;
use App\Domain\Strategy\Contracts\StrategyCharacteristics;
use App\Domain\Strategy\Contracts\StrategyInterface;
use App\Domain\Strategy\Contracts\ValidationResult;

class BearCallSpreadStrategy implements StrategyInterface
{
    public function key(): string { return 'bear_call_spread'; }
    public function name(): string { return 'Bear Call Spread'; }
    public function description(): string { return 'Sell lower strike Call and Buy higher strike Call. Defined-risk bearish credit strategy.'; }
    public function category(): string { return 'BEARISH'; }

    public function characteristics(): StrategyCharacteristics
    {
        return new StrategyCharacteristics('BEARISH', true, true, 2, 'SHORT_VOL', 'POSITIVE_THETA', ['TREND', 'RANGE']);
    }

    public function defaultLegTemplates(float $spot, float $strikeStep = 50.0, float $dte = 3.0, float $iv = 0.14, int $quantity = 65): array
    {
        $atm = round($spot / $strikeStep) * $strikeStep;
        $shortStrike = $atm + $strikeStep;
        $longStrike = $shortStrike + ($strikeStep * 2);
        $t = max($dte / 365.0, 0.001);
        $pShort = BlackScholesCalculator::calculate('CE', $spot, $shortStrike, $t, $iv)->theoreticalPrice;
        $pLong = BlackScholesCalculator::calculate('CE', $spot, $longStrike, $t, $iv)->theoreticalPrice;

        return [
            ['side' => 'SELL', 'option_type' => 'CE', 'strike' => (float)$shortStrike, 'entry_price' => round($pShort, 2), 'current_price' => round($pShort, 2), 'quantity' => $quantity, 'dte' => $dte, 'iv' => $iv, 'status' => 'OPEN'],
            ['side' => 'BUY', 'option_type' => 'CE', 'strike' => (float)$longStrike, 'entry_price' => round($pLong, 2), 'current_price' => round($pLong, 2), 'quantity' => $quantity, 'dte' => $dte, 'iv' => $iv, 'status' => 'OPEN'],
        ];
    }

    public function validate(array $legs): ValidationResult
    {
        if (count($legs) !== 2) return ValidationResult::invalid(['Bear Call Spread requires 2 Call legs.']);
        return ValidationResult::valid();
    }
}
