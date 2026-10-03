<?php

namespace App\Domain\Scenario\DTOs;

class ScenarioMatrix
{
    /**
     * @param array<int, float> $spotChanges [-200, -100, -50, 0, 50, 100, 200]
     * @param array<int, float> $ivChanges [-0.05, -0.02, 0.0, 0.02, 0.05]
     * @param int $daysPassed
     * @param array<int, array<int, ScenarioCell>> $matrix rows indexed by spotChange, cols indexed by ivChange
     */
    public function __construct(
        public readonly array $spotChanges,
        public readonly array $ivChanges,
        public readonly int $daysPassed,
        public readonly array $matrix
    ) {}

    public function toArray(): array
    {
        $grid = [];
        foreach ($this->matrix as $sKey => $row) {
            $grid[$sKey] = [];
            foreach ($row as $ivKey => $cell) {
                $grid[$sKey][$ivKey] = $cell instanceof ScenarioCell ? $cell->toArray() : $cell;
            }
        }

        return [
            'spot_changes' => $this->spotChanges,
            'iv_changes' => array_map(fn($iv) => round($iv * 100, 1) . '%', $this->ivChanges),
            'days_passed' => $this->daysPassed,
            'matrix' => $grid,
        ];
    }
}
