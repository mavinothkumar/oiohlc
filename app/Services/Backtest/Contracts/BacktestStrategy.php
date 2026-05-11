<?php

namespace App\Services\Backtest\Contracts;

abstract class BacktestStrategy
{
    /**
     * Return values:
     *   array of legs          → trade is valid, proceed
     *   ['__skip_reason'=>...] → strategy filtered this day
     *   null                   → legacy skip (treated as "unknown_filter")
     */
    abstract public function resolveLegs(
        string $symbol,
        float  $indexOpen,
        string $tradeDate,
        string $entryTimestamp,
        array  $options
    ): ?array;

    abstract public function describe(array $options): string;

    protected function skip(string $reason): array
    {
        return ['__skip_reason' => $reason];
    }

    public static function isSkip(?array $result): bool
    {
        return $result !== null && isset($result['__skip_reason']);
    }

    public static function skipReason(?array $result): string
    {
        return $result['__skip_reason'] ?? 'unknown_filter';
    }
}
