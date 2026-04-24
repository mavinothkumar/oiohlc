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
        // ── Fetch first 5-min candle (09:15) ──────────────────────────
        $firstCandle = DB::table('expired_ohlc')
                         ->where('underlying_symbol', $symbol)
                         ->where('instrument_type', 'INDEX')
                         ->where('interval', '5minute')
                         ->where('timestamp', $entryTs)
                         ->first();

        if (!$firstCandle) {
            \Log::info("FCB SKIP [{$tradeDate}] — No INDEX candle at {$entryTs}");
            return null;
        }

        $openPrice  = (float) $firstCandle->open;
        $closePrice = (float) $firstCandle->close;
        $fcHigh     = (float) $firstCandle->high;   // first candle high
        $fcLow      = (float) $firstCandle->low;    // first candle low
        $keyStrike  = (int) (round($openPrice / 50) * 50);

        \Log::info("FCB [{$tradeDate}] Open={$openPrice} KeyStrike={$keyStrike} FC_High={$fcHigh} FC_Low={$fcLow}");

        // ── Skip if first candle body > 90 points ─────────────────────
        $firstCandleBody = abs($fcHigh - $fcLow);
        if ($firstCandleBody > 90) {
            \Log::info("FCB SKIP [{$tradeDate}] — First candle body too wide: {$firstCandleBody} pts");
            return null;
        }

        // ── Walk every candle from 09:15 onward ───────────────────────
        // Three conditions must ALL be met before entry:
        //
        // UPSIDE (Sell PE):
        //   [1] candle close > open_price
        //   [2] candle close > key_strike
        //   [3] candle close > first candle HIGH
        //
        // DOWNSIDE (Sell CE):
        //   [1] candle close < open_price
        //   [2] candle close < key_strike
        //   [3] candle close < first candle LOW

        $allIndexCandles = DB::table('expired_ohlc')
                             ->where('underlying_symbol', $symbol)
                             ->where('instrument_type', 'INDEX')
                             ->where('interval', '5minute')
                             ->where('timestamp', '>=', $entryTs)
                             ->whereDate('timestamp', $tradeDate)
                             ->where('timestamp', '<=', "{$tradeDate} 15:00:00")
                             ->orderBy('timestamp')
                             ->get(['timestamp', 'open', 'high', 'low', 'close']);

        $signalTs   = null;
        $optionType = null;

        foreach ($allIndexCandles as $candle) {
            $close = (float) $candle->close;

            // ── Upside: all 3 conditions ───────────────────────────────
            if (
                $close > $openPrice   &&   // [1] above open price
                $close > $keyStrike   &&   // [2] above key strike
                $close > $fcHigh           // [3] above first candle high
            ) {
                $optionType = 'PE';
                $signalTs   = $candle->timestamp;
                \Log::info("FCB [{$tradeDate}] UPSIDE signal at {$signalTs} — close={$close} > open={$openPrice}, strike={$keyStrike}, FC_High={$fcHigh}");
                break;
            }

            // ── Downside: all 3 conditions ─────────────────────────────
            if (
                $close < $openPrice   &&   // [1] below open price
                $close < $keyStrike   &&   // [2] below key strike
                $close < $fcLow            // [3] below first candle low
            ) {
                $optionType = 'CE';
                $signalTs   = $candle->timestamp;
                \Log::info("FCB [{$tradeDate}] DOWNSIDE signal at {$signalTs} — close={$close} < open={$openPrice}, strike={$keyStrike}, FC_Low={$fcLow}");
                break;
            }
        }

        if (!$optionType || !$signalTs) {
            \Log::info("FCB SKIP [{$tradeDate}] — No valid 3-condition breakout found. open={$openPrice} strike={$keyStrike} FC_High={$fcHigh} FC_Low={$fcLow}");
            return null;
        }

        // ── Entry = next candle after signal ──────────────────────────
        $entryAfterTs = Carbon::parse($signalTs)->addMinutes(5)->toDateTimeString();

        $row = DB::table('expired_ohlc')
                 ->where('underlying_symbol', $symbol)
                 ->where('instrument_type', $optionType)
                 ->where('strike', $keyStrike)
                 ->where('interval', '5minute')
                 ->where('timestamp', $entryAfterTs)
                 ->select('instrument_key', 'open', 'strike')
                 ->first();

        // Fallback: nearest available strike within ±100
        if (!$row || (float) $row->open <= 0) {
            \Log::info("FCB [{$tradeDate}] No data at {$keyStrike}, searching nearest within ±100");
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
            \Log::info("FCB SKIP [{$tradeDate}] — No option data near {$keyStrike} for {$optionType} at {$entryAfterTs}");
            return null;
        }

        \Log::info("FCB [{$tradeDate}] Entry: Sell {$optionType} strike={$row->strike} price={$row->open} at {$entryAfterTs}");

        $qty = (int) ($options['lot'] ?? 130);

        return [[
            'strike'          => (int) $row->strike,
            'type'            => $optionType,
            'instrument_key'  => $row->instrument_key,
            'entry_price'     => (float) $row->open,
            'exit_price'      => null,
            'exit_time'       => null,
            'exited'          => false,
            'open_price'      => $openPrice,
            'key_strike'      => $keyStrike,
            'fc_high'         => $fcHigh,     // stored for reference
            'fc_low'          => $fcLow,      // stored for reference
            'direction'       => $optionType === 'PE' ? 'up' : 'down',
            'qty_override'    => $qty,
            'signal_time'     => $signalTs,
            'actual_entry_ts' => $entryAfterTs,
        ]];
    }

    public function describe(array $options): string
    {
        return 'First Candle Breakout — 3-condition entry, single leg, reversal SL';
    }
}
