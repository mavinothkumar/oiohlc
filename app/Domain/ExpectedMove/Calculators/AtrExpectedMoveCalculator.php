<?php

namespace App\Domain\ExpectedMove\Calculators;

use App\Domain\ExpectedMove\Contracts\ExpectedMoveCalculatorInterface;

class AtrExpectedMoveCalculator implements ExpectedMoveCalculatorInterface
{
    public function key(): string
    {
        return 'atr_based';
    }

    public function name(): string
    {
        return 'ATR-Based Expected Move';
    }

    public function calculate(float $spot, float $dte, array $context = []): array
    {
        $atr = (float)($context['atr'] ?? ($spot * 0.009)); // default ~0.9% daily ATR
        $expectedMove = $atr * sqrt(max(1.0, $dte));

        return [
            'methodology' => $this->name(),
            'key' => $this->key(),
            'expected_move' => round($expectedMove, 2),
            'expected_move_pct' => round(($expectedMove / $spot) * 100, 2),
            'upper_bound' => round($spot + $expectedMove, 2),
            'lower_bound' => round(max(0.0, $spot - $expectedMove), 2),
            'parameters' => [
                'atr' => round($atr, 2),
                'dte' => $dte,
            ],
        ];
    }
}
