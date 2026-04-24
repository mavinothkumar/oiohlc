<?php

// app/Services/Backtest/BacktestEngine.php

namespace App\Services\Backtest;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BacktestEngine
{
    public function run(
        array  $legData,
        string $entryTimestamp,
        string $tradeDate,
        float  $target,
        float  $stoploss,
        int    $qty,
    ): array {
        $effectiveQty = $legData[0]['qty_override'] ?? $qty;

        $instrumentKeys = array_column($legData, 'instrument_key');

        // ── Option candles ─────────────────────────────────────────────
        $allCandles = DB::table('expired_ohlc')
                        ->whereIn('instrument_key', $instrumentKeys)
                        ->where('interval', '5minute')
                        ->where('timestamp', '>=', $entryTimestamp)
                        ->whereDate('timestamp', $tradeDate)
                        ->orderBy('timestamp')
                        ->get(['instrument_key', 'timestamp', 'open', 'high', 'low', 'close'])
                        ->groupBy('instrument_key');

        // ── Index candles (only needed for reversal SL) ────────────────
        $hasReversalSL = isset($legData[0]['open_price']);
        $indexCandles  = collect();

        if ($hasReversalSL) {
            $symbol = $this->extractSymbol($legData[0]['instrument_key']);

            $indexCandles = DB::table('expired_ohlc')
                              ->where('underlying_symbol', $symbol)
                              ->where('instrument_type', 'INDEX')
                              ->where('interval', '5minute')
                              ->where('timestamp', '>=', $entryTimestamp)
                              ->whereDate('timestamp', $tradeDate)
                              ->orderBy('timestamp')
                              ->get(['timestamp', 'open', 'high', 'low', 'close'])
                              ->keyBy('timestamp');
        }

        $timestamps = DB::table('expired_ohlc')
                        ->whereIn('instrument_key', $instrumentKeys)
                        ->where('interval', '5minute')
                        ->where('timestamp', '>=', $entryTimestamp)
                        ->whereDate('timestamp', $tradeDate)
                        ->orderBy('timestamp')
                        ->distinct()
                        ->pluck('timestamp')
                        ->toArray();

        $dayExitTime      = null;
        $dayOutcome       = 'open';
        $exitReason       = '';
        $dayMaxProfit     = null;
        $dayMaxLoss       = null;
        $dayMaxProfitTime = null;
        $dayMaxLossTime   = null;

        // ── Reversal SL state machine ──────────────────────────────────
        $hasReversalSL    = isset($legData[0]['open_price']);
        $openPrice        = $legData[0]['open_price']  ?? null;
        $keyStrike        = $legData[0]['key_strike']  ?? null;
        $direction        = $legData[0]['direction']   ?? null; // 'up' or 'down'
        $entryOptionPrice = $legData[0]['entry_price'] ?? null;
        $target50pct      = $entryOptionPrice ? $entryOptionPrice * 0.5 * $effectiveQty : null;

        // Reversal SL states: null → 'watching' → 'broken_candle_found' → 'exit'
        $reversalState      = null;
        $brokenCandleHigh   = null;
        $brokenCandleLow    = null;

        foreach ($timestamps as $ts) {
            $combinedPnl = 0;

            foreach ($legData as $idx => $leg) {
                if ($leg['exited']) {
                    $combinedPnl += ($leg['entry_price'] - $leg['exit_price']) * $effectiveQty;
                    continue;
                }
                $candle = $allCandles->get($leg['instrument_key'])
                                     ?->firstWhere('timestamp', $ts);
                if (!$candle) continue;
                // SELL: profit when price goes down
                $combinedPnl += ($leg['entry_price'] - (float) $candle->close) * $effectiveQty;
            }

            // Peak tracking
            if ($dayMaxProfit === null || $combinedPnl > $dayMaxProfit) {
                $dayMaxProfit     = round($combinedPnl, 2);
                $dayMaxProfitTime = $ts;
            }
            if ($dayMaxLoss === null || $combinedPnl < $dayMaxLoss) {
                $dayMaxLoss     = round($combinedPnl, 2);
                $dayMaxLossTime = $ts;
            }

            // ── Fixed target (₹ amount) ────────────────────────────────
            if ($combinedPnl >= $target) {
                $legData     = $this->exitAll($legData, $allCandles, $ts);
                $dayExitTime = $ts;
                $dayOutcome  = 'profit';
                $exitReason  = "Target ₹{$target} hit";
                break;
            }

            // ── 50% premium target (first_candle_breakout only) ────────
            if ($hasReversalSL && $target50pct !== null && $combinedPnl >= $target50pct) {
                $legData     = $this->exitAll($legData, $allCandles, $ts);
                $dayExitTime = $ts;
                $dayOutcome  = 'profit';
                $exitReason  = "50% premium target hit (₹" . round($target50pct, 0) . ")";
                break;
            }

            // ── Fixed SL ───────────────────────────────────────────────
            if ($combinedPnl <= -$stoploss) {
                $legData     = $this->exitAll($legData, $allCandles, $ts);
                $dayExitTime = $ts;
                $dayOutcome  = 'loss';
                $exitReason  = "SL ₹{$stoploss} hit";
                break;
            }

            // ── Reversal SL (first_candle_breakout only) ───────────────
            if ($hasReversalSL && $openPrice && $keyStrike && $direction) {
                $idxCandle = $indexCandles->get($ts);

                if ($idxCandle) {
                    $idxClose = (float) $idxCandle->close;
                    $idxHigh  = (float) $idxCandle->high;

                    if ($reversalState === null) {
                        // Watch for index to break back through BOTH lines
                        $reversed = $direction === 'up'
                            ? ($idxClose < $openPrice && $idxClose < $keyStrike)
                            : ($idxClose > $openPrice && $idxClose > $keyStrike);

                        if ($reversed) {
                            $reversalState    = 'broken_candle_found';
                            $brokenCandleHigh = (float) $idxCandle->high;
                            $brokenCandleLow  = (float) $idxCandle->low;
                        }

                    } elseif ($reversalState === 'broken_candle_found') {
                        // Wait for a candle to close BEYOND broken candle's high/low
                        $confirmed = $direction === 'up'
                            ? ($idxHigh > $brokenCandleHigh)   // for sell PE, price went back up confirming reversal
                            : ($idxCandle->low < $brokenCandleLow); // for sell CE

                        if ($confirmed) {
                            $legData     = $this->exitAll($legData, $allCandles, $ts);
                            $dayExitTime = $ts;
                            $dayOutcome  = 'loss';
                            $exitReason  = "Reversal SL — broken candle high breached at {$ts}";
                            break;
                        }
                    }
                }
            }
        }

        // EOD exit
        if ($dayOutcome === 'open') {
            foreach ($legData as $idx => $leg) {
                if (!$leg['exited']) {
                    $lastCandle = $allCandles->get($leg['instrument_key'])?->last();
                    if ($lastCandle) {
                        $legData[$idx]['exit_price'] = (float) $lastCandle->close;
                        $legData[$idx]['exit_time']  = $lastCandle->timestamp;
                        $legData[$idx]['exited']     = true;
                    }
                }
            }
            $dayExitTime = $legData[0]['exit_time'] ?? null;
        }

        return [
            'legData'          => $legData,
            'dayExitTime'      => $dayExitTime,
            'dayOutcome'       => $dayOutcome,
            'exitReason'       => $exitReason,
            'dayMaxProfit'     => $dayMaxProfit,
            'dayMaxProfitTime' => $dayMaxProfitTime,
            'dayMaxLoss'       => $dayMaxLoss,
            'dayMaxLossTime'   => $dayMaxLossTime,
            'effectiveQty'     => $effectiveQty,
        ];
    }

    private function exitAll(array $legData, $allCandles, string $ts): array
    {
        foreach ($legData as $idx => $leg) {
            if (!$leg['exited']) {
                $candle = $allCandles->get($leg['instrument_key'])
                                     ?->firstWhere('timestamp', $ts);
                $legData[$idx]['exit_price'] = $candle
                    ? (float) $candle->close
                    : $leg['entry_price'];
                $legData[$idx]['exit_time']  = $ts;
                $legData[$idx]['exited']     = true;
            }
        }
        return $legData;
    }

    private function extractSymbol(string $instrumentKey): string
    {
        // e.g. NSE_FO|NIFTY25JAN02CE23000 → NIFTY
        if (preg_match('/\|([A-Z]+)\d/', $instrumentKey, $m)) {
            return $m[1];
        }
        return 'NIFTY';
    }
}
