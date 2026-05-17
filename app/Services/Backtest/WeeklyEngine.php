<?php

namespace App\Services\Backtest;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WeeklyEngine
 *
 * Walks candles across ALL trading days from entryTimestamp to expiry (or forced exit).
 * Handles:
 *   - Combined P&L target / stoploss across full week
 *   - Any single SELL leg doubling in value (leg_double_pct)
 *   - Trailing profit lock: once P&L >= lock-pct × target → SL moves to breakeven
 *   - Forced exit at 14:30 on expiry day
 *   - Correct BUY leg P&L direction (BUY legs profit when price rises)
 */
class WeeklyEngine
{
    public function run(
        array  $legData,
        string $entryTimestamp,
        string $entryDate,
        float  $target,
        float  $stoploss,
        int    $defaultQty,
        array  $options = []
    ): array {
        $expiry          = $legData[0]['expiry'];
        $legDoublePct    = (float) ($legData[0]['leg_double_pct'] ?? 100);
        $trailingLockPct = (float) ($options['trailing-lock-pct'] ?? 60); // % of target
        $trailingLockAmt = $target * ($trailingLockPct / 100);

        $instrumentKeys = array_column($legData, 'instrument_key');

        // ── Fetch all candles from entry → expiry across all dates ─────────
        $allCandles = DB::table('expired_ohlc')
                        ->whereIn('instrument_key', $instrumentKeys)
                        ->where('interval', '5minute')
                        ->where('timestamp', '>=', $entryTimestamp)
                        ->where('timestamp', '<=', "{$expiry} 15:30:00")
                        ->orderBy('timestamp')
                        ->get(['instrument_key', 'timestamp', 'open', 'high', 'low', 'close'])
                        ->groupBy('instrument_key');

        $timestamps = DB::table('expired_ohlc')
                        ->whereIn('instrument_key', $instrumentKeys)
                        ->where('interval', '5minute')
                        ->where('timestamp', '>=', $entryTimestamp)
                        ->where('timestamp', '<=', "{$expiry} 15:30:00")
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
        $trailingLocked   = false;   // once true, SL moves to 0 (breakeven)
        $effectiveStoploss = $stoploss;

        foreach ($timestamps as $ts) {
            $tsDate = substr($ts, 0, 10);

            // Forced exit at 14:30 on expiry day (avoid expiry wildness)
            if ($tsDate === $expiry && $ts >= "{$expiry} 14:30:00") {
                $legData    = $this->exitAll($legData, $allCandles, $ts);
                $dayExitTime = $ts;
                $dayOutcome = 'open'; // resolved by P&L sign below
                $exitReason = "Forced exit — expiry day 14:30";
                break;
            }

            // ── Combined P&L ──────────────────────────────────────────────
            $combinedPnl = $this->calcPnl($legData, $allCandles, $ts, $defaultQty);

            // ── Peak tracking ──────────────────────────────────────────────
            if ($dayMaxProfit === null || $combinedPnl > $dayMaxProfit) {
                $dayMaxProfit     = round($combinedPnl, 2);
                $dayMaxProfitTime = $ts;
            }
            if ($dayMaxLoss === null || $combinedPnl < $dayMaxLoss) {
                $dayMaxLoss     = round($combinedPnl, 2);
                $dayMaxLossTime = $ts;
            }

            // ── Trailing lock: once target × lock% hit, SL → 0 ───────────
            if (! $trailingLocked && $combinedPnl >= $trailingLockAmt) {
                $trailingLocked    = true;
                $effectiveStoploss = 0; // SL is now breakeven
                Log::info(
                    "WeeklyEngine [{$ts}] trailing_lock activated " .
                    "pnl=₹{$combinedPnl} lockAmt=₹{$trailingLockAmt} SL→breakeven"
                );
            }

            // ── Target hit ────────────────────────────────────────────────
            if ($combinedPnl >= $target) {
                $legData    = $this->exitAll($legData, $allCandles, $ts);
                $dayExitTime = $ts;
                $dayOutcome = 'profit';
                $exitReason = "Target ₹" . number_format($target, 0) . " hit";
                break;
            }

            // ── Stoploss hit (or trailing breakeven breach) ───────────────
            $slBreach = $trailingLocked
                ? ($combinedPnl < 0)           // below breakeven after lock
                : ($combinedPnl <= -$effectiveStoploss);

            if ($slBreach) {
                $legData    = $this->exitAll($legData, $allCandles, $ts);
                $dayExitTime = $ts;
                $dayOutcome = 'loss';
                $exitReason = $trailingLocked
                    ? "Trailing lock breached — SL at breakeven"
                    : "SL ₹" . number_format($stoploss, 0) . " hit";
                break;
            }

            // ── Any single SELL leg doubles in price ──────────────────────
            foreach ($legData as $idx => $leg) {
                if ($leg['exited'] || $leg['side'] !== 'SELL') continue;

                $candle = $allCandles->get($leg['instrument_key'])
                                     ?->firstWhere('timestamp', $ts);
                if (! $candle) continue;

                $currentLtp  = (float) $candle->close;
                $changePct   = $leg['entry_price'] > 0
                    ? (($currentLtp - $leg['entry_price']) / $leg['entry_price']) * 100
                    : 0;

                if ($changePct >= $legDoublePct) {
                    Log::info(
                        "WeeklyEngine [{$ts}] leg_double_exit " .
                        "leg={$leg['role']} strike={$leg['strike']} " .
                        "entry={$leg['entry_price']} current={$currentLtp} " .
                        "change={$changePct}%"
                    );
                    $legData    = $this->exitAll($legData, $allCandles, $ts);
                    $dayExitTime = $ts;
                    $dayOutcome = 'loss';
                    $exitReason = "Leg double exit — {$leg['role']} +{$legDoublePct}% at {$ts}";
                    break 2;
                }
            }
        }

        // ── EOW exit — expiry day last candle if still open ───────────────
        if ($dayOutcome === 'open') {
            foreach ($legData as $idx => $leg) {
                if (! $leg['exited']) {
                    $lastCandle = $allCandles->get($leg['instrument_key'])?->last();
                    $legData[$idx]['exit_price'] = $lastCandle
                        ? (float) $lastCandle->close
                        : (float) $leg['entry_price'];
                    $legData[$idx]['exit_time']  = $lastCandle?->timestamp ?? "{$expiry} 15:30:00";
                    $legData[$idx]['exited']      = true;
                }
            }
            $dayExitTime = collect($legData)->pluck('exit_time')->filter()->max();
            $exitReason  = 'EOW exit (expiry)';
        }

        // Resolve final outcome by sign if still 'open'
        if ($dayOutcome === 'open') {
            $finalPnl   = $this->calcPnlFromExited($legData, $defaultQty);
            $dayOutcome = $finalPnl >= 0 ? 'profit' : 'loss';
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
            'effectiveQty'     => $defaultQty,
        ];
    }

    /**
     * Live P&L using candle close.
     * SELL: profit when price falls  → (entry - current) * qty
     * BUY:  profit when price rises  → (current - entry) * qty
     */
    private function calcPnl(array $legData, $allCandles, string $ts, int $defaultQty): float
    {
        $pnl = 0;

        foreach ($legData as $leg) {
            $qty = $leg['qty_override'] ?? $defaultQty;

            if ($leg['exited']) {
                $legPnl = $leg['side'] === 'BUY'
                    ? ($leg['exit_price']  - $leg['entry_price']) * $qty
                    : ($leg['entry_price'] - $leg['exit_price'])  * $qty;
                $pnl += $legPnl;
                continue;
            }

            $candle = $allCandles->get($leg['instrument_key'])
                                 ?->firstWhere('timestamp', $ts);
            if (! $candle) continue;

            $current = (float) $candle->close;
            $legPnl  = $leg['side'] === 'BUY'
                ? ($current            - $leg['entry_price']) * $qty
                : ($leg['entry_price'] - $current)            * $qty;
            $pnl += $legPnl;
        }

        return round($pnl, 2);
    }

    private function calcPnlFromExited(array $legData, int $defaultQty): float
    {
        $pnl = 0;
        foreach ($legData as $leg) {
            $qty    = $leg['qty_override'] ?? $defaultQty;
            $legPnl = $leg['side'] === 'BUY'
                ? (($leg['exit_price'] ?? $leg['entry_price']) - $leg['entry_price']) * $qty
                : ($leg['entry_price'] - ($leg['exit_price']  ?? $leg['entry_price'])) * $qty;
            $pnl += $legPnl;
        }
        return round($pnl, 2);
    }

    private function exitAll(array $legData, $allCandles, string $ts): array
    {
        foreach ($legData as $idx => $leg) {
            if (! $leg['exited']) {
                $candle = $allCandles->get($leg['instrument_key'])
                                     ?->firstWhere('timestamp', $ts);
                $legData[$idx]['exit_price'] = $candle
                    ? (float) $candle->close
                    : $leg['entry_price'];
                $legData[$idx]['exit_time']  = $ts;
                $legData[$idx]['exited']      = true;
            }
        }
        return $legData;
    }
}
