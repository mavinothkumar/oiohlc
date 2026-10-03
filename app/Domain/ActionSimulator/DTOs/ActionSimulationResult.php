<?php

namespace App\Domain\ActionSimulator\DTOs;

class ActionSimulationResult
{
    public function __construct(
        public readonly string $actionDescription,
        public readonly array $beforeMetrics,
        public readonly array $afterMetrics,
        public readonly array $deltaMetrics,
        public readonly array $improvements = [],
        public readonly array $increasedRisks = [],
        public readonly array $structuralChanges = []
    ) {}

    public function toArray(): array
    {
        return [
            'action' => $this->actionDescription,
            'before' => $this->beforeMetrics,
            'after' => $this->afterMetrics,
            'change' => $this->deltaMetrics,
            'improved' => $this->improvements,
            'increased_risks' => $this->increasedRisks,
            'structural_changes' => $this->structuralChanges,
        ];
    }
}
