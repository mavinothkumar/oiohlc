<?php

// app/Services/Backtest/Strategies/NearStraddleStrategy.php

namespace App\Services\Backtest\Strategies;

use App\Services\Backtest\Contracts\BacktestStrategy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class NearStraddleStrategy implements BacktestStrategy
{
    public function resolveLegs(
        string $symbol,
        float  $indexOpen,
        string $tradeDate,
        string $entryTs,
        array  $options,
    ): ?array {
        // ATM = nearest 100
        $atm         = (int) (round($indexOpen / 100) * 100);
        $entryCandle = Carbon::parse($entryTs)->addMinutes(5)->toDateTimeString();
        $qty         = (int) ($options['lot'] ?? 65);

        // 4 strikes: ATM±100 CE/PE and ATM-100 CE/PE
        $candidates = [
            ['strike' => $atm + 100, 'type' => 'CE'],
            ['strike' => $atm + 100, 'type' => 'PE'],
            ['strike' => $atm - 100, 'type' => 'CE'],
            ['strike' => $atm - 100, 'type' => 'PE'],
        ];

        $legs = [];

        foreach ($candidates as $candidate) {
            $row = DB::table('expired_ohlc')
                     ->where('underlying_symbol', $symbol)
                     ->where('instrument_type', $candidate['type'])
                     ->where('strike', $candidate['strike'])
                     ->where('interval', '5minute')
                     ->where('timestamp', $entryCandle)
                     ->select('instrument_key', 'open', 'strike')
                     ->first();

            if (!$row || (float) $row->open <= 0) {
                \Log::info("NEAR_STRADDLE SKIP [{$tradeDate}] — No data for {$candidate['type']} {$candidate['strike']} at {$entryCandle}");
                return null;
            }

            $legs[] = [
                'strike'         => $candidate['strike'],
                'type'           => $candidate['type'],
                'instrument_key' => $row->instrument_key,
                'entry_price'    => (float) $row->open,
                'exit_price'     => null,
                'exit_time'      => null,
                'exited'         => false,
                'qty_override'   => $qty,
            ];
        }

        \Log::info("NEAR_STRADDLE [{$tradeDate}] ATM={$atm} Upper=" . ($atm + 100) . " Lower=" . ($atm - 100) . " qty={$qty}");

        return $legs;
    }

    public function describe(array $options): string
    {
        $qty = $options['lot'] ?? 65;
        return "Near Straddle — ATM±100 CE+PE, {$qty} qty each (4 legs)";
    }
}
