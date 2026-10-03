<?php

namespace App\Domain\ExpectedMove\Contracts;

interface ExpectedMoveCalculatorInterface
{
    public function key(): string;

    public function name(): string;

    public function calculate(float $spot, float $dte, array $context = []): array;
}
