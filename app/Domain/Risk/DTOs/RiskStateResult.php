<?php

namespace App\Domain\Risk\DTOs;

class RiskStateResult
{
    /**
     * @param string $state 'NORMAL' | 'CAUTION' | 'STRESS' | 'RISK_REVIEW' | 'CLOSED'
     * @param array<int, string> $reasons Explicit human-readable reasons explaining the state
     * @param array<string, mixed> $triggerMetrics Numerical values triggering warnings
     * @param string $summary Description of current risk posture
     */
    public function __construct(
        public readonly string $state,
        public readonly array $reasons,
        public readonly array $triggerMetrics = [],
        public readonly string $summary = ''
    ) {}

    public function toArray(): array
    {
        return [
            'state' => $this->state,
            'reasons' => $this->reasons,
            'trigger_metrics' => $this->triggerMetrics,
            'summary' => $this->summary,
        ];
    }
}
