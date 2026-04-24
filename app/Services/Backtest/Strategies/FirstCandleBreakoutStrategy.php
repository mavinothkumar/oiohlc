<?php
// app/Services/Backtest/Strategies/FirstCandleBreakoutStrategy.php

namespace App\Services\Backtest\Strategies;

use App\Services\Backtest\Contracts\BacktestStrategy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FirstCandleBreakoutStrategy implements BacktestStrategy
{
    public function resolveLegs(
        string $symbol,
        float  $indexOpen,
        string $tradeDate,
        string $entryTs,
        array  $options,
    ): ?array {
        // ── Key levels from the very first candle (09:15) ─────────────
        $firstCandle = DB::table('expired_ohlc')
                         ->where('underlying_symbol', $symbol)
                         ->where('instrument_type', 'INDEX')
                         ->where('interval', '5minute')
                         ->where('timestamp', $entryTs)  // 09:15:00
                         ->first();

        if (!$firstCandle) {
            \Log::info("FCB SKIP [{$tradeDate}] — No INDEX candle at {$entryTs}");
            return null;
        }

        $openPrice = (float) $firstCandle->open;
        $keyStrike = (int) (round($openPrice / 50) * 50);
        \Log::info("FCB [{$tradeDate}] Open={$openPrice} → KeyStrike={$keyStrike}");
        // ── Walk every candle from 09:15 onward until breakout ────────
        $allIndexCandles = DB::table('expired_ohlc')
                             ->where('underlying_symbol', $symbol)
                             ->where('instrument_type', 'INDEX')
                             ->where('interval', '5minute')
                             ->where('timestamp', '>=', $entryTs)
                             ->whereDate('timestamp', $tradeDate)
                             ->where('timestamp', '<=', "{$tradeDate} 15:00:00") // stop looking after 15:00
                             ->orderBy('timestamp')
                             ->get(['timestamp', 'open', 'high', 'low', 'close']);

        $signalTs   = null;
        $optionType = null;

        foreach ($allIndexCandles as $candle) {
            $close = (float) $candle->close;

            if ($close > $openPrice && $close > $keyStrike) {
                // Breakout UP → sell PE
                $optionType = 'PE';
                $signalTs   = $candle->timestamp;
                break;
            }

            if ($close < $openPrice && $close < $keyStrike) {
                // Breakout DOWN → sell CE
                $optionType = 'CE';
                $signalTs   = $candle->timestamp;
                break;
            }
        }

        if (!$optionType || !$signalTs) {
            \Log::info("FCB SKIP [{$tradeDate}] — No breakout signal found all day. Open={$openPrice} Strike={$keyStrike}");
            return null;
        }

        // ── Entry = NEXT candle after signal candle ────────────────────
        $entryAfterTs = Carbon::parse($signalTs)->addMinutes(5)->toDateTimeString();

        \Log::info("FCB [{$tradeDate}] Signal={$optionType} at {$signalTs} → Entry candle at {$entryAfterTs} | Open={$openPrice} Strike={$keyStrike}");

        $row = DB::table('expired_ohlc')
                 ->where('underlying_symbol', $symbol)
                 ->where('instrument_type', $optionType)
                 ->where('strike', $keyStrike)
                 ->where('interval', '5minute')
                 ->where('timestamp', $entryAfterTs)
                 ->select('instrument_key', 'open', 'strike')
                 ->first();

        if (!$row || (float) $row->open <= 0) {
            \Log::info("FCB [{$tradeDate}] No data at {$keyStrike}, searching nearest strike within ±100");

            $row = DB::table('expired_ohlc')
                     ->where('underlying_symbol', $symbol)
                     ->where('instrument_type', $optionType)
                     ->where('interval', '5minute')
                     ->where('timestamp', $entryAfterTs)
                     ->whereBetween('strike', [$keyStrike - 100, $keyStrike + 100])
                     ->whereRaw('open > 0')
                     ->orderByRaw('ABS(strike - ?)', [$keyStrike])
                     ->select('instrument_key', 'open', 'strike')
                     ->first();
        }

        if (!$row || (float) $row->open <= 0) {
            \Log::info("FCB SKIP [{$tradeDate}] — No option data near {$keyStrike} for {$optionType}");
            return null;
        }

        \Log::info("FCB [{$tradeDate}] Entry: Sell {$optionType} strike={$row->strike} price={$row->open} at {$entryAfterTs}");

        $qty = (int) ($options['lot'] ?? 130);

        return [[
            'strike'         => $keyStrike,
            'type'           => $optionType,
            'instrument_key' => $row->instrument_key,
            'entry_price'    => (float) $row->open,
            'exit_price'     => null,
            'exit_time'      => null,
            'exited'         => false,
            'open_price'     => $openPrice,
            'key_strike'     => $keyStrike,
            'direction'      => $optionType === 'PE' ? 'up' : 'down',
            'qty_override'   => $qty,
            'signal_time'    => $signalTs,      // when breakout confirmed
            'actual_entry_ts'=> $entryAfterTs,  // actual entry candle
        ]];
    }

    public function describe(array $options): string
    {
        return 'First Candle Breakout — waits for breakout, single leg, reversal SL';
    }
}
