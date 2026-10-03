<?php

namespace App\Domain\ExpectedMove\Calculators;

use App\Domain\ExpectedMove\Contracts\ExpectedMoveCalculatorInterface;

class HistoricalVolatilityCalculator implements ExpectedMoveCalculatorInterface
{
    public function key(): string
    {
        return 'realized_volatility';
    }

    public function name(): string
    {
        return 'Historical Realized Volatility Move';
    }

    public function calculate(float $spot, float $dte, array $context = []): array
    {
        $hv = (float)($context['hv'] ?? 0.12); // default 12% realized vol
        $t = max($dte / 252.0, 0.001); // 252 trading day basis
        $expectedMove = $spot * $hv * sqrt($t);

        return [
            'methodology' => $this->name(),
            'key' => $this->key(),
            'expected_move' => round($expectedMove, 2),
            'expected_move_pct' => round(($expectedMove / $spot) * 100, 2),
            'upper_bound' => round($spot + $expectedMove, 2),
            'lower_bound' => round(max(0.0, $spot - $expectedMove), 2),
            'parameters' => [
                'realized_volatility' => round($hv * 100, 2) . '%',
                'dte' => $dte,
            ],
        ];
    }
}
