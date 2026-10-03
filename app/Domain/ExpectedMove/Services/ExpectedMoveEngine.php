<?php

namespace App\Domain\ExpectedMove\Services;

use App\Domain\ExpectedMove\Calculators\AtrExpectedMoveCalculator;
use App\Domain\ExpectedMove\Calculators\HistoricalVolatilityCalculator;
use App\Domain\ExpectedMove\Calculators\IvExpectedMoveCalculator;
use App\Domain\ExpectedMove\Contracts\ExpectedMoveCalculatorInterface;

class ExpectedMoveEngine
{
    /** @var array<string, ExpectedMoveCalculatorInterface> */
    protected array $calculators = [];

    public function __construct()
    {
        $this->register(new IvExpectedMoveCalculator());
        $this->register(new AtrExpectedMoveCalculator());
        $this->register(new HistoricalVolatilityCalculator());
    }

    public function register(ExpectedMoveCalculatorInterface $calculator): void
    {
        $this->calculators[$calculator->key()] = $calculator;
    }

    /**
     * Calculate comparison across all expected move methodologies.
     */
    public function calculateAll(float $spot, float $dte, array $context = []): array
    {
        $results = [];
        foreach ($this->calculators as $key => $calc) {
            $results[$key] = $calc->calculate($spot, $dte, $context);
        }

        // Selected primary is IV-based
        $primary = $results['iv_based'] ?? reset($results);

        return [
            'primary' => $primary,
            'all' => $results,
        ];
    }
}
