<?php


// app/Services/Backtest/BacktestEngine.php

namespace App\Services\Backtest;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BacktestEngine {
    public function run(
        array $legData,
        string $entryTimestamp,
        string $tradeDate,
        float $target,
        float $stoploss,
        int $qty,
    ): array {
        $effectiveQty = isset( $legData[0]['qty_override'] )
            ? (int) $legData[0]['qty_override']
            : $qty;

        \Log::info( "Engine [{$tradeDate}] effectiveQty={$effectiveQty} stoploss={$stoploss} target={$target}" );

        $instrumentKeys = array_column( $legData, 'instrument_key' );

        // ── Option candles ─────────────────────────────────────────────
        $allCandles = DB::table( 'expired_ohlc' )
                        ->whereIn( 'instrument_key', $instrumentKeys )
                        ->where( 'interval', '5minute' )
                        ->where( 'timestamp', '>=', $entryTimestamp )
                        ->whereDate( 'timestamp', $tradeDate )
                        ->orderBy( 'timestamp' )
                        ->get( [ 'instrument_key', 'timestamp', 'open', 'high', 'low', 'close' ] )
                        ->groupBy( 'instrument_key' );

        // ── Index candles (only needed for reversal SL) ────────────────
        $hasReversalSL = isset( $legData[0]['open_price'] );
        $indexCandles  = collect();

        if ( $hasReversalSL ) {
            $symbol = $this->extractSymbol( $legData[0]['instrument_key'] );

            $indexCandles = DB::table( 'expired_ohlc' )
                              ->where( 'underlying_symbol', $symbol )
                              ->where( 'instrument_type', 'INDEX' )
                              ->where( 'interval', '5minute' )
                              ->where( 'timestamp', '>=', $entryTimestamp )
                              ->whereDate( 'timestamp', $tradeDate )
                              ->orderBy( 'timestamp' )
                              ->get( [ 'timestamp', 'open', 'high', 'low', 'close' ] )
                              ->keyBy( 'timestamp' );
        }

        $timestamps = DB::table( 'expired_ohlc' )
                        ->whereIn( 'instrument_key', $instrumentKeys )
                        ->where( 'interval', '5minute' )
                        ->where( 'timestamp', '>=', $entryTimestamp )
                        ->whereDate( 'timestamp', $tradeDate )
                        ->orderBy( 'timestamp' )
                        ->distinct()
                        ->pluck( 'timestamp' )
                        ->toArray();

        $dayExitTime      = null;
        $dayOutcome       = 'open';
        $exitReason       = '';
        $dayMaxProfit     = null;
        $dayMaxLoss       = null;
        $dayMaxProfitTime = null;
        $dayMaxLossTime   = null;

        // ── Reversal SL state machine vars ─────────────────────────────
        $hasReversalSL    = isset( $legData[0]['open_price'] );
        $openPrice        = $legData[0]['open_price'] ?? null;
        $keyStrike        = $legData[0]['key_strike'] ?? null;
        $direction        = $legData[0]['direction'] ?? null;
        $entryOptionPrice = $legData[0]['entry_price'] ?? null;
        $target50pct      = $entryOptionPrice
            ? $entryOptionPrice * 0.5 * $effectiveQty
            : null;

        $reversalState    = null;
        $brokenCandleHigh = null;
        $brokenCandleLow  = null;

        foreach ( $timestamps as $ts ) {

            // ── Combined PnL using candle CLOSE ───────────────────────
            $combinedPnl = 0;
            foreach ( $legData as $leg ) {
                if ( $leg['exited'] ) {
                    $combinedPnl += ( $leg['entry_price'] - $leg['exit_price'] ) * $effectiveQty;
                    continue;
                }
                $candle = $allCandles->get( $leg['instrument_key'] )
                                     ?->firstWhere( 'timestamp', $ts );
                if ( ! $candle ) {
                    continue;
                }
                $combinedPnl += ( $leg['entry_price'] - (float) $candle->close ) * $effectiveQty;
            }

            // ── Peak tracking ──────────────────────────────────────────
            if ( $dayMaxProfit === null || $combinedPnl > $dayMaxProfit ) {
                $dayMaxProfit     = round( $combinedPnl, 2 );
                $dayMaxProfitTime = $ts;
            }
            if ( $dayMaxLoss === null || $combinedPnl < $dayMaxLoss ) {
                $dayMaxLoss     = round( $combinedPnl, 2 );
                $dayMaxLossTime = $ts;
            }

            // ── Target hit ─────────────────────────────────────────────
            if ( $combinedPnl >= $target ) {
                $legData     = $this->exitAll( $legData, $allCandles, $ts );
                $dayExitTime = $ts;
                $dayOutcome  = 'profit';
                $exitReason  = "Target ₹{$target} hit";
                break;
            }

            // ── 50% premium target (first_candle_breakout only) ────────
            if ( $hasReversalSL && $target50pct !== null && $combinedPnl >= $target50pct ) {
                $legData     = $this->exitAll( $legData, $allCandles, $ts );
                $dayExitTime = $ts;
                $dayOutcome  = 'profit';
                $exitReason  = "50% premium target hit (₹" . round( $target50pct, 0 ) . ")";
                break;
            }

            // ── SL hit ─────────────────────────────────────────────────
            if ( $combinedPnl <= - $stoploss ) {
                $actualLoss = abs( $combinedPnl );

                // Gap overshoot: actual loss > SL → cap final loss at SL + ₹500
                $isOvershoot = $actualLoss > $stoploss;
                $finalLoss   = $isOvershoot ? ( $stoploss + 500 ) : $actualLoss;

                \Log::info(
                    "Engine [{$tradeDate}] SL hit at {$ts} " .
                    "actual=₹{$actualLoss} sl=₹{$stoploss} " .
                    ( $isOvershoot ? "OVERSHOOT→capped=₹{$finalLoss}" : "exact" )
                );

                // Exit at close first (gives us correct exit_price per leg)
                $legData = $this->exitAll( $legData, $allCandles, $ts );

                // If overshoot, override day_total_pnl by bumping one leg's
                // exit_price so the final realised loss = exactly $finalLoss
                if ( $isOvershoot ) {
                    $legData = $this->adjustExitForCappedLoss(
                        $legData, $finalLoss, $effectiveQty
                    );
                }

                $dayExitTime = $ts;
                $dayOutcome  = 'loss';
                $exitReason  = "SL ₹{$stoploss} hit";
                break;
            }

            // ── Reversal SL (first_candle_breakout only) ───────────────
            if ( $hasReversalSL && $openPrice && $keyStrike && $direction ) {
                $idxCandle = $indexCandles->get( $ts );

                if ( $idxCandle ) {
                    $idxClose = (float) $idxCandle->close;
                    $idxHigh  = (float) $idxCandle->high;

                    if ( $reversalState === null ) {
                        $reversed = $direction === 'up'
                            ? ( $idxClose < $openPrice && $idxClose < $keyStrike )
                            : ( $idxClose > $openPrice && $idxClose > $keyStrike );

                        if ( $reversed ) {
                            $reversalState    = 'broken_candle_found';
                            $brokenCandleHigh = (float) $idxCandle->high;
                            $brokenCandleLow  = (float) $idxCandle->low;
                        }

                    } elseif ( $reversalState === 'broken_candle_found' ) {
                        $confirmed = $direction === 'up'
                            ? ( $idxHigh > $brokenCandleHigh )
                            : ( $idxCandle->low < $brokenCandleLow );

                        if ( $confirmed ) {
                            $legData     = $this->exitAll( $legData, $allCandles, $ts );
                            $dayExitTime = $ts;
                            $dayOutcome  = 'loss';
                            $exitReason  = "Reversal SL — broken candle high breached at {$ts}";
                            break;
                        }
                    }
                }
            }
        }

        // ── EOD exit ───────────────────────────────────────────────────
        if ( $dayOutcome === 'open' ) {
            foreach ( $legData as $idx => $leg ) {
                if ( ! $leg['exited'] ) {
                    $lastCandle = $allCandles->get( $leg['instrument_key'] )?->last();
                    if ( $lastCandle ) {
                        $legData[ $idx ]['exit_price'] = (float) $lastCandle->close;
                        $legData[ $idx ]['exit_time']  = $lastCandle->timestamp;
                        $legData[ $idx ]['exited']     = true;
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

    /**
     * Standard exit — all legs at candle close.
     * Used for: target hit, reversal SL, EOD, and base of SL exit.
     */
    private function exitAll( array $legData, $allCandles, string $ts ): array {
        foreach ( $legData as $idx => $leg ) {
            if ( ! $leg['exited'] ) {
                $candle                        = $allCandles->get( $leg['instrument_key'] )
                                                            ?->firstWhere( 'timestamp', $ts );
                $legData[ $idx ]['exit_price'] = $candle
                    ? (float) $candle->close
                    : $leg['entry_price'];
                $legData[ $idx ]['exit_time']  = $ts;
                $legData[ $idx ]['exited']     = true;
            }
        }

        return $legData;
    }

    /**
     * After exitAll(), if the candle-close P&L overshoots SL,
     * adjust the FIRST leg's exit_price so total realised loss = $finalLoss exactly.
     *
     * Formula (SELL): pnl = (entry - exit) * qty
     * To get pnl = -finalLoss:
     *   exit_price_adjustment = entry_price - (target_pnl_per_leg / qty)
     *
     * We only touch leg[0] to keep it simple and auditable.
     */
    private function adjustExitForCappedLoss(
        array $legData,
        float $finalLoss,
        int $effectiveQty,
    ): array {
        // Sum up PnL from legs 1..N (unchanged)
        $otherLegsPnl = 0;
        foreach ( array_slice( $legData, 1 ) as $leg ) {
            $otherLegsPnl += ( $leg['entry_price'] - $leg['exit_price'] ) * $effectiveQty;
        }

        // leg[0] must produce this much PnL to make total = -finalLoss
        $leg0TargetPnl = - $finalLoss - $otherLegsPnl;

        // exit_price for leg[0]: entry - (targetPnl / qty)
        $leg0ExitPrice = $legData[0]['entry_price'] - ( $leg0TargetPnl / $effectiveQty );

        // Safety: exit price must be positive
        $legData[0]['exit_price'] = max( round( $leg0ExitPrice, 2 ), 0.01 );

        return $legData;
    }

    private function extractSymbol( string $instrumentKey ): string {
        if ( preg_match( '/\|([A-Z]+)\d/', $instrumentKey, $m ) ) {
            return $m[1];
        }

        return 'NIFTY';
    }
}
