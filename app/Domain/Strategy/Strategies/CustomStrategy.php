<?php

namespace App\Domain\Strategy\Strategies;

use App\Domain\Strategy\Contracts\StrategyCharacteristics;
use App\Domain\Strategy\Contracts\StrategyInterface;
use App\Domain\Strategy\Contracts\ValidationResult;

class CustomStrategy implements StrategyInterface
{
    public function key(): string { return 'custom'; }
    public function name(): string { return 'Custom Multi-Leg Strategy'; }
    public function description(): string { return 'User-defined bespoke multi-leg configuration with arbitrary legs, quantities, strikes and sides.'; }
    public function category(): string { return 'CUSTOM'; }

    public function characteristics(): StrategyCharacteristics
    {
        return new StrategyCharacteristics('HYBRID', false, false, 0, 'VARIABLE', 'VARIABLE', ['ANY']);
    }

    public function defaultLegTemplates(float $spot, float $strikeStep = 50.0, float $dte = 3.0, float $iv = 0.14, int $quantity = 65): array
    {
        return [];
    }

    public function validate(array $legs): ValidationResult
    {
        if (empty($legs)) {
            return ValidationResult::invalid(['At least one leg is required for a custom strategy.']);
        }
        return ValidationResult::valid();
    }
}
