<?php

namespace App\Domain\Strategy\Contracts;

interface StrategyInterface
{
    public function key(): string;

    public function name(): string;

    public function description(): string;

    public function category(): string;

    public function characteristics(): StrategyCharacteristics;

    /**
     * Generate canonical default legs template for this strategy around a given spot.
     */
    public function defaultLegTemplates(
        float $spot,
        float $strikeStep = 50.0,
        float $dte = 3.0,
        float $iv = 0.14,
        int $quantity = 65
    ): array;

    /**
     * Validate whether a collection of legs conforms to this strategy's rules.
     */
    public function validate(array $legs): ValidationResult;
}
