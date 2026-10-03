<?php

namespace App\Domain\ExpectedMove\Calculators;

use App\Domain\ExpectedMove\Contracts\ExpectedMoveCalculatorInterface;

class IvExpectedMoveCalculator implements ExpectedMoveCalculatorInterface
{
    public function key(): string
    {
        return 'iv_based';
    }

    public function name(): string
    {
        return 'IV-Based Expected Move (1-Sigma)';
    }

    public function calculate(float $spot, float $dte, array $context = []): array
    {
        $iv = (float)($context['iv'] ?? 0.14);
        $t = max($dte / 365.0, 0.001);

        // 1-standard-deviation move = Spot * IV * sqrt(t)
        $expectedMove = $spot * $iv * sqrt($t);

        // Also straddle approximation if ATM straddle premium given
        $straddlePremium = isset($context['atm_straddle_premium'])
            ? (float)$context['atm_straddle_premium'] * 0.85
            : null;

        return [
            'methodology' => $this->name(),
            'key' => $this->key(),
            'expected_move' => round($expectedMove, 2),
            'expected_move_pct' => round(($expectedMove / $spot) * 100, 2),
            'upper_bound' => round($spot + $expectedMove, 2),
            'lower_bound' => round(max(0.0, $spot - $expectedMove), 2),
            'parameters' => [
                'iv' => round($iv * 100, 2) . '%',
                'dte' => $dte,
                'straddle_approx' => $straddlePremium ? round($straddlePremium, 2) : null,
            ],
        ];
    }
}
