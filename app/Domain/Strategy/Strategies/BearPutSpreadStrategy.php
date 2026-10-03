<?php

namespace App\Domain\Strategy\Strategies;

use App\Domain\Greeks\Calculators\BlackScholesCalculator;
use App\Domain\Strategy\Contracts\StrategyCharacteristics;
use App\Domain\Strategy\Contracts\StrategyInterface;
use App\Domain\Strategy\Contracts\ValidationResult;

class BearPutSpreadStrategy implements StrategyInterface
{
    public function key(): string { return 'bear_put_spread'; }
    public function name(): string { return 'Bear Put Spread'; }
    public function description(): string { return 'Buy higher strike Put and Sell lower strike Put. Defined-risk bearish debit play.'; }
    public function category(): string { return 'BEARISH'; }

    public function characteristics(): StrategyCharacteristics
    {
        return new StrategyCharacteristics('BEARISH', true, false, 2, 'BALANCED', 'BALANCED', ['TREND']);
    }

    public function defaultLegTemplates(float $spot, float $strikeStep = 50.0, float $dte = 3.0, float $iv = 0.14, int $quantity = 65): array
    {
        $atm = round($spot / $strikeStep) * $strikeStep;
        $longStrike = $atm;
        $shortStrike = $atm - ($strikeStep * 2);
        $t = max($dte / 365.0, 0.001);
        $pLong = BlackScholesCalculator::calculate('PE', $spot, $longStrike, $t, $iv)->theoreticalPrice;
        $pShort = BlackScholesCalculator::calculate('PE', $spot, $shortStrike, $t, $iv)->theoreticalPrice;

        return [
            ['side' => 'BUY', 'option_type' => 'PE', 'strike' => (float)$longStrike, 'entry_price' => round($pLong, 2), 'current_price' => round($pLong, 2), 'quantity' => $quantity, 'dte' => $dte, 'iv' => $iv, 'status' => 'OPEN'],
            ['side' => 'SELL', 'option_type' => 'PE', 'strike' => (float)$shortStrike, 'entry_price' => round($pShort, 2), 'current_price' => round($pShort, 2), 'quantity' => $quantity, 'dte' => $dte, 'iv' => $iv, 'status' => 'OPEN'],
        ];
    }

    public function validate(array $legs): ValidationResult
    {
        if (count($legs) !== 2) return ValidationResult::invalid(['Bear Put Spread requires 2 Put legs.']);
        return ValidationResult::valid();
    }
}
