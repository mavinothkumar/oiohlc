<?php

// app/Services/Backtest/Strategies/SmartBalancedStrategy.php

namespace App\Services\Backtest\Strategies;

use App\Services\Backtest\Contracts\BacktestStrategy;
use App\Services\SmartStrikeResolver;
use Illuminate\Support\Facades\DB;

class SmartBalancedStrategy implements BacktestStrategy
{
    private SmartStrikeResolver $resolver;

    public function __construct()
    {
        $this->resolver = new SmartStrikeResolver();
    }

    public function resolveLegs(
        string $symbol,
        float  $indexOpen,
        string $tradeDate,
        string $entryTs,
        array  $options,
    ): ?array {
        $minOffset = (int) ($options['min-offset'] ?? 300);
        $maxOffset = (int) ($options['max-offset'] ?? 600);
        $stepSize  = (int) ($options['step']       ?? 100);

        $upperResolved = $this->resolver->resolve(
            symbol:    $symbol,
            indexOpen: $indexOpen,
            side:      'upper',
            tradeDate: $tradeDate,
            entryTs:   $entryTs,
            minOffset: $minOffset,
            maxOffset: $maxOffset,
            stepSize:  $stepSize,
        );

        $lowerResolved = $this->resolver->resolve(
            symbol:    $symbol,
            indexOpen: $indexOpen,
            side:      'lower',
            tradeDate: $tradeDate,
            entryTs:   $entryTs,
            minOffset: $minOffset,
            maxOffset: $maxOffset,
            stepSize:  $stepSize,
        );

        if (!$upperResolved || !$lowerResolved) {
            \Log::debug("Skipped due to $upperResolved and  $lowerResolved");
            return null;
        }

        $candidates = [
            ['strike' => $upperResolved['ce_strike'], 'type' => 'CE'],
            ['strike' => $upperResolved['pe_strike'], 'type' => 'PE'],
            ['strike' => $lowerResolved['ce_strike'], 'type' => 'CE'],
            ['strike' => $lowerResolved['pe_strike'], 'type' => 'PE'],
        ];

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
                \Log::debug("Missing candle: {$candidate['type']} {$candidate['strike']} on {$entryTs}");
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

    public function describe(array $options): string
    {
        $min = $options['min-offset'] ?? 300;
        $max = $options['max-offset'] ?? 600;
        return "Smart Balanced Premium (offset {$min}–{$max})";
    }
}
