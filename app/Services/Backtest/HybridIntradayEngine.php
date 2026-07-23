<?php

namespace App\Services\Backtest;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HybridIntradayEngine
{
    public function run(
        array $legData,
        string $entryTimestamp,
        string $tradeDate,
        float $target,
        float $stoploss,
        int $defaultQty,
        array $engineOptions = []
    ): array {
        $useLegSl = (bool) ($engineOptions['use-leg-sl'] ?? true);
        $useCombinedSl = (bool) ($engineOptions['use-combined-sl'] ?? true);
        $legSlPct = (float) ($engineOptions['leg-sl-pct'] ?? 60.0);
        $combinedSlPct = (float) ($engineOptions['combined-sl-pct'] ?? 40.0);

        $instrumentKeys = array_column($legData, 'instrument_key');

        $allCandles = DB::table('expired_ohlc')
                        ->whereIn('instrument_key', $instrumentKeys)
                        ->where('interval', '5minute')
                        ->where('timestamp', '>=', $entryTimestamp)
                        ->whereDate('timestamp', $tradeDate)
                        ->orderBy('timestamp')
                        ->get(['instrument_key', 'timestamp', 'open', 'high', 'low', 'close'])
                        ->groupBy('instrument_key');

        $timestamps = DB::table('expired_ohlc')
                        ->whereIn('instrument_key', $instrumentKeys)
                        ->where('interval', '5minute')
                        ->where('timestamp', '>=', $entryTimestamp)
                        ->whereDate('timestamp', $tradeDate)
                        ->orderBy('timestamp')
                        ->distinct()
                        ->pluck('timestamp')
                        ->toArray();

        $combinedEntryPremium = array_sum(array_map(
            fn ($leg) => (float) $leg['entry_price'],
            $legData
        ));

        $dayExitTime = null;
        $dayOutcome = 'open';
        $exitReason = '';
        $dayMaxProfit = null;
        $dayMaxLoss = null;
        $dayMaxProfitTime = null;
        $dayMaxLossTime = null;

        foreach ($timestamps as $ts) {
            $combinedPnl = $this->calcPnl($legData, $allCandles, $ts, $defaultQty);
            $combinedCurrentPrice = $this->calcCombinedCurrentPrice($legData, $allCandles, $ts);

            if ($dayMaxProfit === null || $combinedPnl > $dayMaxProfit) {
                $dayMaxProfit = round($combinedPnl, 2);
                $dayMaxProfitTime = $ts;
            }

            if ($dayMaxLoss === null || $combinedPnl < $dayMaxLoss) {
                $dayMaxLoss = round($combinedPnl, 2);
                $dayMaxLossTime = $ts;
            }

            if ($combinedPnl >= $target) {
                $legData = $this->exitAll($legData, $allCandles, $ts);
                $dayExitTime = $ts;
                $dayOutcome = 'profit';
                $exitReason = 'Target ₹' . number_format($target, 0) . ' hit';
                break;
            }

            if ($useLegSl) {
                $breach = $this->checkLegStoploss($legData, $allCandles, $ts, $defaultQty, $legSlPct);

                if ($breach !== null) {
                    Log::info(
                        "HybridIntradayEngine [{$tradeDate}] LEG-SL at {$ts} — {$breach['label']} threshold={$legSlPct}%"
                    );

                    $legData = $this->exitAll($legData, $allCandles, $ts);
                    $dayExitTime = $ts;
                    $dayOutcome = 'loss';
                    $exitReason = "Leg SL {$legSlPct}% hit — {$breach['label']}";
                    break;
                }
            }

            if ($useCombinedSl && $combinedEntryPremium > 0) {
                $combinedBreachPct = (($combinedCurrentPrice - $combinedEntryPremium) / $combinedEntryPremium) * 100;

                if ($combinedBreachPct >= $combinedSlPct) {
                    Log::info(
                        "HybridIntradayEngine [{$tradeDate}] COMBINED-SL at {$ts} — " .
                        "entry={$combinedEntryPremium} current={$combinedCurrentPrice} " .
                        "breach={$combinedBreachPct}% threshold={$combinedSlPct}%"
                    );

                    $legData = $this->exitAll($legData, $allCandles, $ts);
                    $dayExitTime = $ts;
                    $dayOutcome = 'loss';
                    $exitReason = sprintf(
                        'Combined premium SL %.1f%% hit (entry=%.0f current=%.0f)',
                        $combinedSlPct,
                        $combinedEntryPremium,
                        $combinedCurrentPrice
                    );
                    break;
                }
            }

            if ($combinedPnl <= -$stoploss) {
                Log::info(
                    "HybridIntradayEngine [{$tradeDate}] SL hit at {$ts} actual=₹" .
                    abs(round($combinedPnl, 2)) . " sl=₹{$stoploss}"
                );

                $legData = $this->exitAll($legData, $allCandles, $ts);
                $dayExitTime = $ts;
                $dayOutcome = 'loss';
                $exitReason = 'SL ₹' . number_format($stoploss, 0) . ' hit';
                break;
            }
        }

        if ($dayOutcome === 'open') {
            $marketCloseTs = "{$tradeDate} 15:30:00";

            foreach ($legData as $idx => $leg) {
                if ($leg['exited']) {
                    continue;
                }

                $lastCandle = $allCandles->get($leg['instrument_key'])?->last();

                $legData[$idx]['exit_price'] = $lastCandle
                    ? (float) $lastCandle->close
                    : (float) $leg['entry_price'];

                $legData[$idx]['exit_time'] = $lastCandle
                    ? $lastCandle->timestamp
                    : $marketCloseTs;

                $legData[$idx]['exited'] = true;
            }

            $dayExitTime = collect($legData)->pluck('exit_time')->filter()->max();
            $exitReason = 'EOD exit';

            $finalPnl = $this->calcPnlFromExited($legData, $defaultQty);
            $dayOutcome = $finalPnl >= 0 ? 'profit' : 'loss';
        }

        return [
            'legData' => $legData,
            'dayExitTime' => $dayExitTime,
            'dayOutcome' => $dayOutcome,
            'exitReason' => $exitReason,
            'dayMaxProfit' => $dayMaxProfit,
            'dayMaxProfitTime' => $dayMaxProfitTime,
            'dayMaxLoss' => $dayMaxLoss,
            'dayMaxLossTime' => $dayMaxLossTime,
            'effectiveQty' => $defaultQty,
        ];
    }

    private function calcPnl(array $legData, Collection $allCandles, string $ts, int $defaultQty): float
    {
        $pnl = 0;

        foreach ($legData as $leg) {
            $qty = (int) ($leg['qty_override'] ?? $defaultQty);
            $side = strtoupper($leg['side'] ?? 'SELL');

            if ($leg['exited']) {
                $exit = (float) ($leg['exit_price'] ?? $leg['entry_price']);
                $pnl += $side === 'BUY'
                    ? ($exit - $leg['entry_price']) * $qty
                    : ($leg['entry_price'] - $exit) * $qty;
                continue;
            }

            $candle = $allCandles->get($leg['instrument_key'])?->firstWhere('timestamp', $ts);

            if (! $candle) {
                continue;
            }

            $current = (float) $candle->close;

            $pnl += $side === 'BUY'
                ? ($current - $leg['entry_price']) * $qty
                : ($leg['entry_price'] - $current) * $qty;
        }

        return round($pnl, 2);
    }

    private function calcPnlFromExited(array $legData, int $defaultQty): float
    {
        $pnl = 0;

        foreach ($legData as $leg) {
            $qty = (int) ($leg['qty_override'] ?? $defaultQty);
            $side = strtoupper($leg['side'] ?? 'SELL');
            $exit = (float) ($leg['exit_price'] ?? $leg['entry_price']);

            $pnl += $side === 'BUY'
                ? ($exit - $leg['entry_price']) * $qty
                : ($leg['entry_price'] - $exit) * $qty;
        }

        return round($pnl, 2);
    }

    private function calcCombinedCurrentPrice(array $legData, Collection $allCandles, string $ts): float
    {
        $total = 0;

        foreach ($legData as $leg) {
            if ($leg['exited']) {
                $total += (float) ($leg['exit_price'] ?? $leg['entry_price']);
                continue;
            }

            $candle = $allCandles->get($leg['instrument_key'])?->firstWhere('timestamp', $ts);

            if (! $candle) {
                continue;
            }

            $total += (float) $candle->close;
        }

        return round($total, 2);
    }

    private function checkLegStoploss(
        array $legData,
        Collection $allCandles,
        string $ts,
        int $defaultQty,
        float $legSlPct
    ): ?array {
        foreach ($legData as $leg) {
            if ($leg['exited']) {
                continue;
            }

            $side = strtoupper($leg['side'] ?? 'SELL');

            if ($side !== 'SELL') {
                continue;
            }

            $candle = $allCandles->get($leg['instrument_key'])?->firstWhere('timestamp', $ts);

            if (! $candle) {
                continue;
            }

            $currentPrice = (float) $candle->close;
            $entryPrice = (float) $leg['entry_price'];

            if ($entryPrice <= 0) {
                continue;
            }

            $changePct = (($currentPrice - $entryPrice) / $entryPrice) * 100;

            if ($changePct >= $legSlPct) {
                return [
                    'label' => sprintf(
                        '%s %s@%d (entry=%.2f current=%.2f rise=%.1f%% qty=%d)',
                        $side,
                        $leg['type'],
                        $leg['strike'],
                        $entryPrice,
                        $currentPrice,
                        $changePct,
                        (int) ($leg['qty_override'] ?? $defaultQty)
                    ),
                ];
            }
        }

        return null;
    }

    private function exitAll(array $legData, Collection $allCandles, string $ts): array
    {
        foreach ($legData as $idx => $leg) {
            if ($leg['exited']) {
                continue;
            }

            $candle = $allCandles->get($leg['instrument_key'])?->firstWhere('timestamp', $ts);

            $legData[$idx]['exit_price'] = $candle
                ? (float) $candle->close
                : (float) $leg['entry_price'];

            $legData[$idx]['exit_time'] = $ts;
            $legData[$idx]['exited'] = true;
        }

        return $legData;
    }
}
