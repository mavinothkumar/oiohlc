<?php

// app/Services/Backtest/Contracts/BacktestStrategy.php

namespace App\Services\Backtest\Contracts;

interface BacktestStrategy
{
    /**
     * Resolve the 4 legs for a single trading day.
     *
     * @param  string $symbol
     * @param  float  $indexOpen     Index price at entry candle open
     * @param  string $tradeDate     Y-m-d
     * @param  string $entryTs       Full datetime Y-m-d H:i:s
     * @param  array  $options       All CLI options passed through
     * @return array|null            Array of legs, or null to skip the day
     *
     * Each leg:
     * [
     *   'strike'         => int,
     *   'type'           => 'CE'|'PE',
     *   'instrument_key' => string,
     *   'entry_price'    => float,
     *   'exit_price'     => null,
     *   'exit_time'      => null,
     *   'exited'         => false,
     * ]
     */
    public function resolveLegs(
        string $symbol,
        float  $indexOpen,
        string $tradeDate,
        string $entryTs,
        array  $options,
    ): ?array;

    /**
     * Human-readable description shown in the run header.
     */
    public function describe(array $options): string;
}
