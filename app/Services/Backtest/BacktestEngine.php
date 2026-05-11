<?php

namespace App\Services\Backtest;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BacktestEngine
{
    /**
     * Walk every 5-minute candle from entry → EOD and apply all exit checks.
     *
     * Exit priority (checked in order every candle):
     *  1. Target hit                          (combined P&L >= target)
     *  2. 50% premium target                  (first_candle_breakout only)
     *  3. Individual leg loss %               (--leg-sl-pct, default 60%) [NEW]
     *  4. Combined premium breach %           (--combined-sl-pct, default 40%) [NEW]
     *  5. Combined day SL                     (--stoploss)
     *  6. Reversal SL                         (first_candle_breakout only)
     *  7. EOD exit                            (15:30)
     *
     * Guards 3 & 4 are enabled by default. Pass --no-leg-sl or --no-combined-sl
     * on the CLI to disable either one independently.
     */
    public function run(
        array  $legData,
        string $entryTimestamp,
        string $tradeDate,
        float  $target,
        float  $stoploss,
        int    $qty,
        array  $engineOptions = []
    ): array {
        $effectiveQty = $legData[0]['qty_override'] ?? $qty;

        // ── Guard flags (passed from RunStrangleBacktest via $engineOptions) ──
        $useLegSl      = (bool) ($engineOptions['use-leg-sl']      ?? true);
        $useCombinedSl = (bool) ($engineOptions['use-combined-sl'] ?? true);
        $legSlPct      = (float) ($engineOptions['leg-sl-pct']      ?? 60.0);   // % above entry
        $combinedSlPct = (float) ($engineOptions['combined-sl-pct'] ?? 40.0);   // % above combined entry

        // ── Pre-compute combined entry premium for the combined-SL guard ──────
        $combinedEntryPremium = array_sum(array_column($legData, 'entry_price'));

        // ── Load all option candles from entry onward ──────────────────────
        $instrumentKeys = array_column($legData, 'instrument_key');

        $allCandles = DB::table('expired_ohlc')
                        ->whereIn('instrument_key', $instrumentKeys)
                        ->where('interval', '5minute')
                        ->where('timestamp', '>=', $entryTimestamp)
                        ->whereDate('timestamp', $tradeDate)
                        ->orderBy('timestamp')
                        ->get(['instrument_key', 'timestamp', 'open', 'high', 'low', 'close'])
                        ->groupBy('instrument_key');

        // ── Index candles (only needed for reversal SL) ────────────────────
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

        // ── Reversal SL state machine vars ─────────────────────────────────
        $openPrice        = $legData[0]['open_price']  ?? null;
        $keyStrike        = $legData[0]['key_strike']  ?? null;
        $direction        = $legData[0]['direction']   ?? null;
        $entryOptionPrice = $legData[0]['entry_price'] ?? null;
        $target50pct      = $entryOptionPrice
            ? $entryOptionPrice * 0.5 * $effectiveQty
            : null;

        $reversalState    = null;
        $brokenCandleHigh = null;
        $brokenCandleLow  = null;

        foreach ($timestamps as $ts) {

            // ── Combined P&L using candle CLOSE ───────────────────────────
            $combinedPnl          = 0;
            $combinedCurrentPrice = 0; // sum of all live leg current prices

            foreach ($legData as $leg) {
                if ($leg['exited']) {
                    $combinedPnl += ($leg['entry_price'] - $leg['exit_price']) * $effectiveQty;
                    // exited legs contribute their locked exit price to the running premium sum
                    $combinedCurrentPrice += $leg['exit_price'];
                    continue;
                }

                $candle = $allCandles->get($leg['instrument_key'])
                                     ?->firstWhere('timestamp', $ts);

                if (! $candle) {
                    continue;
                }

                $currentPrice          = (float) $candle->close;
                $combinedPnl          += ($leg['entry_price'] - $currentPrice) * $effectiveQty;
                $combinedCurrentPrice += $currentPrice;
            }

            // ── Peak tracking ──────────────────────────────────────────────
            if ($dayMaxProfit === null || $combinedPnl > $dayMaxProfit) {
                $dayMaxProfit     = round($combinedPnl, 2);
                $dayMaxProfitTime = $ts;
            }

            if ($dayMaxLoss === null || $combinedPnl < $dayMaxLoss) {
                $dayMaxLoss     = round($combinedPnl, 2);
                $dayMaxLossTime = $ts;
            }

            // ── 1. Target hit ──────────────────────────────────────────────
            if ($combinedPnl >= $target) {
                $legData     = $this->exitAll($legData, $allCandles, $ts);
                $dayExitTime = $ts;
                $dayOutcome  = 'profit';
                $exitReason  = "Target ₹{$target} hit";
                break;
            }

            // ── 2. 50% premium target (first_candle_breakout only) ─────────
            if ($hasReversalSL && $target50pct !== null && $combinedPnl >= $target50pct) {
                $legData     = $this->exitAll($legData, $allCandles, $ts);
                $dayExitTime = $ts;
                $dayOutcome  = 'profit';
                $exitReason  = "50% premium target hit (₹" . round($target50pct, 0) . ")";
                break;
            }

            // ── 3. Individual leg loss % guard ─────────────────────────────
            // Exit whole trade when ANY single un-exited leg current price
            // has risen by >= leg-sl-pct% above its entry price.
            // e.g. entry=100, leg-sl-pct=60 → exit when current >= 160
            if ($useLegSl) {
                $legBreached    = false;
                $legBreachLabel = '';

                foreach ($legData as $leg) {
                    if ($leg['exited']) {
                        continue;
                    }

                    $candle = $allCandles->get($leg['instrument_key'])
                                         ?->firstWhere('timestamp', $ts);

                    if (! $candle) {
                        continue;
                    }

                    $currentPrice  = (float) $candle->close;
                    $entryPrice    = (float) $leg['entry_price'];
                    $lossPct       = $entryPrice > 0
                        ? (($currentPrice - $entryPrice) / $entryPrice) * 100
                        : 0;

                    if ($lossPct >= $legSlPct) {
                        $legBreached    = true;
                        $legBreachLabel = sprintf(
                            '%s@%d (entry=%.2f current=%.2f loss=%.1f%%)',
                            $leg['type'],
                            $leg['strike'],
                            $entryPrice,
                            $currentPrice,
                            $lossPct
                        );
                        break;
                    }
                }

                if ($legBreached) {
                    Log::info(
                        "Engine [{$tradeDate}] LEG-SL at {$ts} — {$legBreachLabel} " .
                        "threshold={$legSlPct}%"
                    );

                    $legData     = $this->exitAll($legData, $allCandles, $ts);
                    $dayExitTime = $ts;
                    $dayOutcome  = 'loss';
                    $exitReason  = "Leg SL {$legSlPct}% hit — {$legBreachLabel}";
                    break;
                }
            }

            // ── 4. Combined premium breach % guard ────────────────────────
            // Exit whole trade when combined current premium has risen by
            // >= combined-sl-pct% above combined entry premium.
            // e.g. combined entry=200, combined-sl-pct=40 → exit when current >= 280
            if ($useCombinedSl && $combinedEntryPremium > 0) {
                $combinedBreachPct = (($combinedCurrentPrice - $combinedEntryPremium) / $combinedEntryPremium) * 100;

                if ($combinedBreachPct >= $combinedSlPct) {
                    Log::info(
                        "Engine [{$tradeDate}] COMBINED-SL at {$ts} — " .
                        "entry={$combinedEntryPremium} current={$combinedCurrentPrice} " .
                        "breach={$combinedBreachPct}% threshold={$combinedSlPct}%"
                    );

                    $legData     = $this->exitAll($legData, $allCandles, $ts);
                    $dayExitTime = $ts;
                    $dayOutcome  = 'loss';
                    $exitReason  = sprintf(
                        'Combined premium SL %.1f%% hit (entry=%.0f current=%.0f)',
                        $combinedSlPct,
                        $combinedEntryPremium,
                        $combinedCurrentPrice
                    );
                    break;
                }
            }

            // ── 5. Combined day SL ─────────────────────────────────────────
            if ($combinedPnl <= -$stoploss) {
                $actualLoss = abs($combinedPnl);

                $isOvershoot = $actualLoss > $stoploss;
                $finalLoss   = $isOvershoot ? ($stoploss + 500) : $actualLoss;

                Log::info(
                    "Engine [{$tradeDate}] SL hit at {$ts} " .
                    "actual=₹{$actualLoss} sl=₹{$stoploss} " .
                    ($isOvershoot ? "OVERSHOOT→capped=₹{$finalLoss}" : "exact")
                );

                $legData = $this->exitAll($legData, $allCandles, $ts);

                if ($isOvershoot) {
                    $legData = $this->adjustExitForCappedLoss($legData, $finalLoss, $effectiveQty);
                }

                $dayExitTime = $ts;
                $dayOutcome  = 'loss';
                $exitReason  = "SL ₹{$stoploss} hit";
                break;
            }

            // ── 6. Reversal SL (first_candle_breakout only) ────────────────
            if ($hasReversalSL && $openPrice && $keyStrike && $direction) {
                $idxCandle = $indexCandles->get($ts);

                if ($idxCandle) {
                    $idxClose = (float) $idxCandle->close;
                    $idxHigh  = (float) $idxCandle->high;

                    if ($reversalState === null) {
                        $reversed = $direction === 'up'
                            ? ($idxClose < $openPrice && $idxClose < $keyStrike)
                            : ($idxClose > $openPrice && $idxClose > $keyStrike);

                        if ($reversed) {
                            $reversalState    = 'broken_candle_found';
                            $brokenCandleHigh = (float) $idxCandle->high;
                            $brokenCandleLow  = (float) $idxCandle->low;
                        }
                    } elseif ($reversalState === 'broken_candle_found') {
                        $confirmed = $direction === 'up'
                            ? ($idxHigh > $brokenCandleHigh)
                            : ($idxCandle->low < $brokenCandleLow);

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

        // ── 7. EOD exit ────────────────────────────────────────────────────
        if ($dayOutcome === 'open') {
            $marketCloseTs = "{$tradeDate} 15:30:00";

            foreach ($legData as $idx => $leg) {
                if (! $leg['exited']) {
                    $lastCandle = $allCandles->get($leg['instrument_key'])?->last();

                    $legData[$idx]['exit_price'] = $lastCandle
                        ? (float) $lastCandle->close
                        : (float) $leg['entry_price'];

                    $legData[$idx]['exit_time'] = $lastCandle
                        ? $lastCandle->timestamp
                        : $marketCloseTs;

                    $legData[$idx]['exited'] = true;
                }
            }

            $dayExitTime = collect($legData)->pluck('exit_time')->filter()->max();
            $exitReason  = 'EOD exit';
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

    /**
     * Standard exit — all legs at candle close.
     */
    private function exitAll(array $legData, $allCandles, string $ts): array
    {
        foreach ($legData as $idx => $leg) {
            if (! $leg['exited']) {
                $candle = $allCandles->get($leg['instrument_key'])
                                     ?->firstWhere('timestamp', $ts);

                $legData[$idx]['exit_price'] = $candle
                    ? (float) $candle->close
                    : $leg['entry_price'];

                $legData[$idx]['exit_time'] = $ts;
                $legData[$idx]['exited']    = true;
            }
        }

        return $legData;
    }

    /**
     * After exitAll(), if the candle-close P&L overshoots SL,
     * adjust leg[0] exit_price so total realised loss = $finalLoss exactly.
     *
     * Formula (SELL): pnl = (entry - exit) * qty
     * To get pnl = -finalLoss: exit = entry - (target_pnl / qty)
     */
    private function adjustExitForCappedLoss(
        array $legData,
        float $finalLoss,
        int   $effectiveQty
    ): array {
        $otherLegsPnl = 0;
        foreach (array_slice($legData, 1) as $leg) {
            $otherLegsPnl += ($leg['entry_price'] - $leg['exit_price']) * $effectiveQty;
        }

        $leg0TargetPnl = -$finalLoss - $otherLegsPnl;
        $leg0ExitPrice = $legData[0]['entry_price'] - ($leg0TargetPnl / $effectiveQty);

        $legData[0]['exit_price'] = max(round($leg0ExitPrice, 2), 0.01);

        return $legData;
    }

    private function extractSymbol(string $instrumentKey): string
    {
        if (preg_match('/\|([A-Z]+)\d/', $instrumentKey, $m)) {
            return $m[1];
        }

        return 'NIFTY';
    }
}
