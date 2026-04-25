<?php

// app/Services/Backtest/Strategies/AtmStraddleStrategy.php

namespace App\Services\Backtest\Strategies;

use App\Services\Backtest\Contracts\BacktestStrategy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AtmStraddleStrategy implements BacktestStrategy
{
    public function resolveLegs(
        string $symbol,
        float  $indexOpen,
        string $tradeDate,
        string $entryTs,
        array  $options,
    ): ?array {
        // ATM = nearest 100 to index open
        $atm = (int) (round($indexOpen / 100) * 100);

        // Entry candle = 09:20 open
        $entryCandle = Carbon::parse($entryTs)->addMinutes(5)->toDateTimeString();

        $qty  = (int) ($options['lot'] ?? 130);
        $legs = [];

        foreach (['CE', 'PE'] as $type) {
            $row = DB::table('expired_ohlc')
                     ->where('underlying_symbol', $symbol)
                     ->where('instrument_type', $type)
                     ->where('strike', $atm)
                     ->where('interval', '5minute')
                     ->where('timestamp', $entryCandle)
                     ->select('instrument_key', 'open', 'strike')
                     ->first();

            if (!$row || (float) $row->open <= 0) {
                \Log::info("ATM_STRADDLE SKIP [{$tradeDate}] — No data for {$type} {$atm} at {$entryCandle}");
                return null;
            }

            $legs[] = [
                'strike'         => $atm,
                'type'           => $type,
                'instrument_key' => $row->instrument_key,
                'entry_price'    => (float) $row->open,
                'exit_price'     => null,
                'exit_time'      => null,
                'exited'         => false,
                'qty_override'   => $qty,
            ];
        }

        \Log::info("ATM_STRADDLE [{$tradeDate}] ATM={$atm} CE={$legs[0]['entry_price']} PE={$legs[1]['entry_price']} qty={$qty}");

        return $legs;
    }

    public function describe(array $options): string
    {
        $qty = $options['lot'] ?? 130;
        return "ATM Straddle — CE + PE at ATM, {$qty} qty each";
    }
}
