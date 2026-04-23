<?php

// app/Services/Backtest/BacktestEngine.php

namespace App\Services\Backtest;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BacktestEngine
{
    /**
     * Walk 5-min candles for the given legs and return exit data.
     *
     * @param  array  $legData        Resolved legs from strategy
     * @param  string $entryTimestamp Full datetime Y-m-d H:i:s
     * @param  string $tradeDate      Y-m-d
     * @param  float  $target         Combined P&L target (positive)
     * @param  float  $stoploss       Combined P&L stop loss (positive)
     * @param  int    $qty            Qty per leg
     * @return array  [legData, dayExitTime, dayOutcome, exitReason,
     *                 dayMaxProfit, dayMaxProfitTime,
     *                 dayMaxLoss, dayMaxLossTime]
     */
    public function run(
        array  $legData,
        string $entryTimestamp,
        string $tradeDate,
        float  $target,
        float  $stoploss,
        int    $qty,
    ): array {
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

        $dayExitTime      = null;
        $dayOutcome       = 'open';
        $exitReason       = '';
        $dayMaxProfit     = null;
        $dayMaxLoss       = null;
        $dayMaxProfitTime = null;
        $dayMaxLossTime   = null;

        foreach ($timestamps as $ts) {
            $combinedPnl = 0;

            foreach ($legData as $idx => $leg) {
                if ($leg['exited']) {
                    $combinedPnl += ($leg['entry_price'] - $leg['exit_price']) * $qty;
                    continue;
                }
                $candle = $allCandles->get($leg['instrument_key'])
                                     ?->firstWhere('timestamp', $ts);
                if (!$candle) {
                    continue;
                }
                $combinedPnl += ($leg['entry_price'] - (float) $candle->close) * $qty;
            }

            // Track peak and trough
            if ($dayMaxProfit === null || $combinedPnl > $dayMaxProfit) {
                $dayMaxProfit     = round($combinedPnl, 2);
                $dayMaxProfitTime = $ts;
            }
            if ($dayMaxLoss === null || $combinedPnl < $dayMaxLoss) {
                $dayMaxLoss     = round($combinedPnl, 2);
                $dayMaxLossTime = $ts;
            }

            // Target hit
            if ($combinedPnl >= $target) {
                $legData     = $this->exitAll($legData, $allCandles, $ts);
                $dayExitTime = $ts;
                $dayOutcome  = 'profit';
                $exitReason  = "Target ₹{$target} hit";
                break;
            }

            // Stoploss hit
            if ($combinedPnl <= -$stoploss) {
                $legData     = $this->exitAll($legData, $allCandles, $ts);
                $dayExitTime = $ts;
                $dayOutcome  = 'loss';
                $exitReason  = "SL ₹{$stoploss} hit";
                break;
            }
        }

        // EOD exit for any remaining open legs
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
}
