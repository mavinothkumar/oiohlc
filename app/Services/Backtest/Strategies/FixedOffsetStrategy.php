<?php

// app/Services/Backtest/Strategies/FixedOffsetStrategy.php

namespace App\Services\Backtest\Strategies;

use App\Services\Backtest\Contracts\BacktestStrategy;
use Illuminate\Support\Facades\DB;

class FixedOffsetStrategy implements BacktestStrategy
{
    public function resolveLegs(
        string $symbol,
        float  $indexOpen,
        string $tradeDate,
        string $entryTs,
        array  $options,
    ): ?array {
        $offset      = (int) ($options['strike-offset'] ?? 300);
        $atm         = (int) (round($indexOpen / 100) * 100);
        $upperStrike = $atm + $offset;
        $lowerStrike = $atm - $offset;

        $candidates = [
            ['strike' => $upperStrike, 'type' => 'CE'],
            ['strike' => $upperStrike, 'type' => 'PE'],
            ['strike' => $lowerStrike, 'type' => 'CE'],
            ['strike' => $lowerStrike, 'type' => 'PE'],
        ];

        return $this->fetchLegs($symbol, $candidates, $entryTs);
    }

    public function describe(array $options): string
    {
        $offset = $options['strike-offset'] ?? 300;
        return "Fixed Offset ± {$offset}";
    }

    protected function fetchLegs(string $symbol, array $candidates, string $entryTs): ?array
    {
        $legs = [];

        foreach ($candidates as $candidate) {
            $row = DB::table('expired_ohlc')
                     ->where('underlying_symbol', $symbol)
                     ->where('instrument_type', $candidate['type'])
                     ->where('strike', $candidate['strike'])
                     ->where('interval', '5minute')
                     ->where('timestamp', $entryTs)
                     ->select('instrument_key', 'open')
                     ->first();

            if (!$row || (float) $row->open <= 0) {
                continue;
            }

            $legs[] = [
                'strike'         => $candidate['strike'],
                'type'           => $candidate['type'],
                'instrument_key' => $row->instrument_key,
                'entry_price'    => (float) $row->open,
                'exit_price'     => null,
                'exit_time'      => null,
                'exited'         => false,
            ];
        }

        return count($legs) >= 2 ? $legs : null;
    }
}
