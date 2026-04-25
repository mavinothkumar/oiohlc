<?php

// app/Services/Backtest/Strategies/FifteenMinBreakoutStrategy.php

namespace App\Services\Backtest\Strategies;

use App\Services\Backtest\Contracts\BacktestStrategy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FifteenMinBreakoutStrategy implements BacktestStrategy
{
    // OTM offset from breakout ATM
    private const OTM_OFFSET = 100;

    public function resolveLegs(
        string $symbol,
        float  $indexOpen,
        string $tradeDate,
        string $entryTs,
        array  $options,
    ): ?array {
        $qty = (int) ($options['lot'] ?? 130);

        // ── Step 1: 15min reference candle from 3×5min candles ───────────────
        // ── Step 1: 15min ref from 3×5min INDEX candles ──────────────────
        $refTimestamps = [
            Carbon::parse($tradeDate)->setTimeFromTimeString('09:15:00')->toDateTimeString(),
            Carbon::parse($tradeDate)->setTimeFromTimeString('09:20:00')->toDateTimeString(),
            Carbon::parse($tradeDate)->setTimeFromTimeString('09:25:00')->toDateTimeString(),
        ];

        $refCandle = DB::table('expired_ohlc')
                       ->where('underlying_symbol', $symbol)
                       ->where('instrument_type', 'INDEX')       // ← INDEX candles
                       ->where('interval', '5minute')
                       ->whereIn('timestamp', $refTimestamps)
                       ->selectRaw('MAX(high) AS high, MIN(low) AS low, COUNT(*) AS candle_count')
                       ->first();

        if (!$refCandle || (int) $refCandle->candle_count < 3) {
            Log::info("15M_BREAKOUT SKIP [{$tradeDate}] — Need 3 INDEX candles, got {$refCandle?->candle_count}");
            return null;
        }

        $refHigh = (float) $refCandle->high;
        $refLow  = (float) $refCandle->low;

        Log::info("15M_BREAKOUT [{$tradeDate}] High={$refHigh} Low={$refLow}");

        // ── Step 2: Scan 5min candles from 09:30 for first breakout close ─────
        $candles = DB::table('expired_ohlc')
                     ->where('underlying_symbol', $symbol)
                     ->where('instrument_type', 'INDEX')
                     ->where('interval', '5minute')
                     ->whereBetween('timestamp', [
                         Carbon::parse($tradeDate)->setTimeFromTimeString('09:30:00')->toDateTimeString(), // ← NOT 09:15
                         Carbon::parse($tradeDate)->setTimeFromTimeString('15:20:00')->toDateTimeString(),
                     ])
                     ->orderBy('timestamp')
                     ->select('timestamp', 'close')
                     ->get();

        $breakoutCandle    = null;
        $breakoutDirection = null;

        foreach ($candles as $candle) {
            $close = (float) $candle->close;

            if ($close > $refHigh) {
                $breakoutCandle    = $candle;
                $breakoutDirection = 'high'; // bullish → sell PE
                break;
            }

            if ($close < $refLow) {
                $breakoutCandle    = $candle;
                $breakoutDirection = 'low';  // bearish → sell CE
                break;
            }
        }

        if (!$breakoutCandle) {
            Log::info("15M_BREAKOUT SKIP [{$tradeDate}] — No breakout detected");
            return null;
        }

        Log::info("15M_BREAKOUT [{$tradeDate}] Direction={$breakoutDirection} signal={$breakoutCandle->timestamp} close={$breakoutCandle->close}");

        // ── Step 3: Resolve strike ─────────────────────────────────────────────
        $atm  = (int) (round((float) $breakoutCandle->close / 100) * 100);

        // Bearish break → CE is going ITM, sell OTM CE = ATM + 100
        // Bullish break → PE is going ITM, sell OTM PE = ATM - 100
        [$strike, $instrumentType] = $breakoutDirection === 'low'
            ? [$atm + self::OTM_OFFSET, 'CE']
            : [$atm - self::OTM_OFFSET, 'PE'];

        // Entry on OPEN of the NEXT 5min candle after breakout signal
        $entryCandle = Carbon::parse($breakoutCandle->timestamp)
                             ->addMinutes(5)
                             ->toDateTimeString();

        // ── Step 4: Fetch option entry price ──────────────────────────────────
        $optionRow = DB::table('expired_ohlc')
                       ->where('underlying_symbol', $symbol)
                       ->where('instrument_type', $instrumentType)
                       ->where('strike', $strike)
                       ->where('interval', '5minute')
                       ->where('timestamp', $entryCandle)
                       ->select('instrument_key', 'open')
                       ->first();

        if (!$optionRow || (float) $optionRow->open <= 0) {
            Log::info("15M_BREAKOUT SKIP [{$tradeDate}] — No option data for {$instrumentType} {$strike} at {$entryCandle}");
            return null;
        }

        Log::info("15M_BREAKOUT [{$tradeDate}] {$instrumentType} {$strike} entry={(float)$optionRow->open} at {$entryCandle}");

        return [[
            'strike'         => $strike,
            'type'           => $instrumentType,
            'instrument_key' => $optionRow->instrument_key,
            'entry_price'    => (float) $optionRow->open,
            'entry_time'     => $entryCandle,
            'signal_time'    => $breakoutCandle->timestamp,
            'exit_price'     => null,
            'exit_time'      => null,
            'exited'         => false,
            'qty_override'   => $qty,
        ]];
    }

    public function describe(array $options): string
    {
        $qty = $options['lot'] ?? 130;
        return "15min Candle Breakout — sell CE/PE OTM+100 on 09:15 candle break, {$qty} qty";
    }
}
