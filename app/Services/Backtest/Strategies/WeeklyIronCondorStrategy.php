<?php

namespace App\Services\Backtest\Strategies;

use App\Services\Backtest\Contracts\BacktestStrategy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Weekly Iron Condor Strategy
 *
 * Sell structure (2 lots each):
 *   +offset CE  (e.g. ATM + 600)
 *   +offset PE  (e.g. ATM + 600)
 *   -offset CE  (e.g. ATM - 600)
 *   -offset PE  (e.g. ATM - 600)
 *
 * Hedge structure (8 lots, buy):
 *   Buy CE strike closest to ₹10 above sell CE strike
 *   Buy PE strike closest to ₹10 below sell PE strike
 *
 * Hold across full expiry week. Exit on:
 *   (a) Combined P&L >= target (default ₹100,000)
 *   (b) Combined P&L <= -stoploss (default ₹30,000)
 *   (c) Any single SELL leg doubles in value (--leg-double-pct, default 100%)
 *   (d) Trailing lock: once P&L crosses lock-pct of target, SL moves to breakeven
 *   (e) Expiry day 14:30 forced exit
 *
 * Gap handling:
 *   If gap_abs > gap-shift-threshold (default 100), shift both strikes
 *   one step in the gap direction to re-centre the condor.
 *
 * Skip reasons:
 *   already_in_trade    — position from prior week still open (carry-over guard)
 *   no_expiry           — no expiry found for this week
 *   no_index_candle     — INDEX candle missing at entry time
 *   gap_skip            — gap too extreme (> gap-skip-threshold), skip week entirely
 *   not_entry_day       — this date is not the designated entry day of the week
 *   not_enough_legs     — fewer than 4 sell legs resolved
 *   no_hedge_ce         — hedge CE could not be found near ₹10
 *   no_hedge_pe         — hedge PE could not be found near ₹10
 */
class WeeklyIronCondorStrategy extends BacktestStrategy
{
    public function resolveLegs(
        string $symbol,
        float  $indexOpen,
        string $tradeDate,
        string $entryTimestamp,
        array  $options
    ): ?array {
        $offset           = (int)   ($options['strike-offset']      ?? 600);
        $step             = (int)   ($options['step']               ?? 50);
        $sellLots         = (int)   ($options['sell-lots']          ?? 2);
        $hedgeLots        = (int)   ($options['hedge-lots']         ?? 8);
        $hedgeTargetPrice = (float) ($options['hedge-price']        ?? 10.0);
        $hedgeMaxPrice = (float) ($options['hedge-max-price'] ?? 50.0);
        $hedgeSearchSteps = (int)   ($options['hedge-search-steps'] ?? 10);
        $gapShiftThresh   = (float) ($options['gap-shift-threshold'] ?? 100);
        $gapSkipThresh    = (float) ($options['gap-skip-threshold']  ?? 300);

        // ── Step 1 — Gap data ─────────────────────────────────────────────
        $gapRow = DB::table('index_gap')
                    ->where('symbol_name', $symbol)
                    ->whereDate('trading_date', $tradeDate)
                    ->first();

        $gapAbs      = (float) ($gapRow?->gap_abs  ?? 0);
        $gapValue    = (float) ($gapRow?->gap_value ?? 0); // signed: + = gap up, - = gap down
        $gapType     = $gapRow?->gap_type ?? 'Flat';

        // Skip week entirely if gap is catastrophically large
        if ($gapSkipThresh > 0 && $gapAbs >= $gapSkipThresh) {
            Log::info("WeeklyIronCondor SKIP [{$tradeDate}] gap_skip gap_abs={$gapAbs} >= {$gapSkipThresh}");
            return $this->skip('gap_skip');
        }

        // ── Step 2 — Expiry for this week ────────────────────────────────
        $expiry = DB::table('expired_expiries')
                    ->where('underlying_symbol', $symbol)
                    ->where('instrument_type', 'OPT')
                    ->where('expiry_date', '>=', $tradeDate)
                    ->orderBy('expiry_date')
                    ->value('expiry_date');

        if (! $expiry) {
            Log::info("WeeklyIronCondor SKIP [{$tradeDate}] no_expiry");
            return $this->skip('no_expiry');
        }

        /// ── Step 3 — ATM via index_gap.current_open ──────────────────────
// Falls back to indexOpen (passed from engine) if no row found
        $currentOpen = (float) (
            DB::table('index_gap')
              ->where('symbol_name', $symbol)
              ->whereDate('trading_date', $tradeDate)
              ->value('current_open')
            ?? $indexOpen
        );

        $atm = (int) (round($currentOpen / $step) * $step);

        // If significant gap, shift condor center in gap direction
        // so the short leg is not immediately under threat
        $shiftSteps = 0;
        if ($gapShiftThresh > 0 && $gapAbs >= $gapShiftThresh) {
            $shiftSteps = (int) round($gapAbs / ($step * 2)); // shift by half gap in steps
            $shiftSteps = min($shiftSteps, 4);               // cap at 4 steps
            if ($gapValue > 0) {
                // Gap up → shift up (sell CE higher, sell PE higher)
                $atm += $shiftSteps * $step;
            } else {
                // Gap down → shift down
                $atm -= $shiftSteps * $step;
            }
            Log::info(
                "WeeklyIronCondor [{$tradeDate}] gap_shift " .
                "gap_abs={$gapAbs} gap_type={$gapType} " .
                "shiftSteps={$shiftSteps} adjustedAtm={$atm}"
            );
        }

        $upperSellStrike = $atm + $offset;
        $lowerSellStrike = $atm - $offset;

        // ── Step 4 — Resolve the 4 SELL legs ─────────────────────────────
        $sellCandidates = [
            ['strike' => $upperSellStrike, 'type' => 'CE', 'side' => 'SELL', 'role' => 'sell_upper_ce'],
            ['strike' => $upperSellStrike, 'type' => 'PE', 'side' => 'SELL', 'role' => 'sell_upper_pe'],
            ['strike' => $lowerSellStrike, 'type' => 'CE', 'side' => 'SELL', 'role' => 'sell_lower_ce'],
            ['strike' => $lowerSellStrike, 'type' => 'PE', 'side' => 'SELL', 'role' => 'sell_lower_pe'],
        ];

        $sellLegs = $this->fetchLegs($symbol, $expiry, $sellCandidates, $entryTimestamp, $sellLots, $step);

        if (count($sellLegs) < 4) {
            Log::info(
                "WeeklyIronCondor SKIP [{$tradeDate}] not_enough_legs " .
                "found=" . count($sellLegs) . " need=4 " .
                "upper={$upperSellStrike} lower={$lowerSellStrike}"
            );
            return $this->skip('not_enough_legs');
        }

        $totalSellPremium = array_sum(array_column($sellLegs, 'entry_price'));

        // ── Step 5 — Resolve HEDGE legs (BUY, closest to ₹10) ───────────
        $hedgeCeLeg = $this->findHedgeLeg(
            $symbol, $expiry, 'CE', $upperSellStrike,
            $step, $hedgeSearchSteps, $hedgeTargetPrice, $hedgeMaxPrice,
            $entryTimestamp, $hedgeLots, 'up'
        );

        if (! $hedgeCeLeg) {
            Log::info("WeeklyIronCondor SKIP [{$tradeDate}] no_hedge_ce above={$upperSellStrike}");
            return $this->skip('no_hedge_ce');
        }

        $hedgePeLeg = $this->findHedgeLeg(
            $symbol, $expiry, 'PE', $lowerSellStrike,
            $step, $hedgeSearchSteps, $hedgeTargetPrice, $hedgeMaxPrice,
            $entryTimestamp, $hedgeLots, 'down'
        );

        if (! $hedgePeLeg) {
            Log::info("WeeklyIronCondor SKIP [{$tradeDate}] no_hedge_pe below={$lowerSellStrike}");
            return $this->skip('no_hedge_pe');
        }

        // ── Step 6 — Net premium & suggested target ───────────────────────
        $totalHedgeCost  = ($hedgeCeLeg['entry_price'] + $hedgePeLeg['entry_price']) * $hedgeLots;
        $totalSellIncome = $totalSellPremium * $sellLots;
        $netPremiumPts   = $totalSellPremium - ($hedgeCeLeg['entry_price'] + $hedgePeLeg['entry_price']);

        $suggestedTarget = (int) ($options['target'] ?? 100000);

        Log::info(
            "WeeklyIronCondor [{$tradeDate}] TRADE ✓ " .
            "upper={$upperSellStrike} lower={$lowerSellStrike} " .
            "atm={$atm} gap_shift_steps={$shiftSteps} " .
            "sellPremium={$totalSellPremium}pts hedgeCost=₹{$totalHedgeCost} " .
            "netPremiumPts={$netPremiumPts} expiry={$expiry} " .
            "hedgeCE={$hedgeCeLeg['strike']}@{$hedgeCeLeg['entry_price']} " .
            "hedgePE={$hedgePeLeg['strike']}@{$hedgePeLeg['entry_price']}"
        );

        // ── Step 7 — Return all 6 legs ────────────────────────────────────
        // Legs carry extra metadata the WeeklyEngine needs:
        //   weekly_hold  => true  (signals multi-day engine)
        //   leg_double_pct => threshold to trigger full exit
        //   expiry       => so engine can find candles across multiple dates

        $legDoublePct = (float) ($options['leg-double-pct'] ?? 100);

        $meta = [
            'weekly_hold'    => true,
            'expiry'         => $expiry,
            'leg_double_pct' => $legDoublePct,
            'atm_at_entry'   => $atm,
            'gap_abs'        => $gapAbs,
            'gap_type'       => $gapType,
            'suggested_target' => $suggestedTarget,
        ];

        $allLegs = [];

        foreach ($sellLegs as $leg) {
            $allLegs[] = array_merge($leg, $meta);
        }

        // Mark hedge legs as BUY side
        $hedgeCeLeg['side']         = 'BUY';
        $hedgeCeLeg['role']         = 'hedge_ce';
        $hedgeCeLeg['qty_override'] = $hedgeLots;
        $allLegs[]                  = array_merge($hedgeCeLeg, $meta);

        $hedgePeLeg['side']         = 'BUY';
        $hedgePeLeg['role']         = 'hedge_pe';
        $hedgePeLeg['qty_override'] = $hedgeLots;
        $allLegs[]                  = array_merge($hedgePeLeg, $meta);

        return $allLegs;
    }

    public function describe(array $options): string
    {
        $offset    = $options['strike-offset'] ?? 600;
        $sellLots  = $options['sell-lots']     ?? 2;
        $hedgeLots = $options['hedge-lots']    ?? 8;
        $target    = $options['target']        ?? 100000;
        $stoploss  = $options['stoploss']      ?? 30000;

        return "Weekly Iron Condor ±{$offset} | Sell={$sellLots}L Hedge={$hedgeLots}L | T=₹{$target} SL=₹{$stoploss}";
    }

    /**
     * Fetch SELL legs — returns array of leg arrays, one per candidate found.
     */
    private function fetchLegs(
        string $symbol,
        string $expiry,
        array  $candidates,
        string $entryTimestamp,
        int    $lots,
        int    $step
    ): array {
        $legs = [];

        foreach ($candidates as $candidate) {
            $row = $this->fetchNearestStrike(
                $symbol, $expiry,
                $candidate['type'],
                $candidate['strike'],
                $entryTimestamp,
                $step,
                // CE upper → walk inward (down), PE upper → walk inward (down)
                // CE lower → walk inward (up),  PE lower → walk inward (up)
                str_contains($candidate['role'], 'upper') ? 'down' : 'up',
                8  // max walk steps inward
            );

            if (! $row) {
                Log::info(
                    "WeeklyIronCondor fetchLegs MISS " .
                    "strike={$candidate['strike']} type={$candidate['type']} " .
                    "ts={$entryTimestamp} expiry={$expiry}"
                );
                continue;
            }

            $legs[] = [
                'strike'           => $row->strike,   // actual strike found, not target
                'type'             => $candidate['type'],
                'side'             => $candidate['side'],
                'role'             => $candidate['role'],
                'instrument_key'   => $row->instrument_key,
                'entry_price'      => (float) $row->open,
                'exit_price'       => null,
                'exit_time'        => null,
                'exited'           => false,
                'qty_override'     => $lots,
                'suggested_target' => null,
            ];
        }

        return $legs;
    }

    /**
     * Walk strikes away from the sell strike to find one closest to $targetPrice.
     * Direction 'up' → CE hedge (strikes above sell strike)
     * Direction 'down' → PE hedge (strikes below sell strike)
     */
    /**
     * Find hedge leg — cheapest available strike that is:
     *   CE hedge: highest strike with open <= hedgeMaxPrice (most OTM cheap CE)
     *   PE hedge: lowest strike  with open <= hedgeMaxPrice (most OTM cheap PE)
     *
     * This works with real DB data where only liquid strikes are stored.
     */
    private function findHedgeLeg(
        string $symbol,
        string $expiry,
        string $type,
        int    $fromStrike,
        int    $step,
        int    $maxSteps,
        float  $targetPrice,
        float  $maxPrice,
        string $entryTimestamp,
        int    $hedgeLots,
        string $direction
    ): ?array {
        // For CE: find the highest strike with price <= maxPrice (furthest OTM cheap)
        // For PE: find the lowest  strike with price <= maxPrice (furthest OTM cheap)
        $query = DB::table('expired_ohlc')
                   ->where('underlying_symbol', $symbol)
                   ->where('instrument_type', $type)
                   ->where('expiry', $expiry)
                   ->where('interval', '5minute')
                   ->where('timestamp', $entryTimestamp)
                   ->where('open', '>', 0)
                   ->where('open', '<=', $maxPrice);

        // CE hedge must be above the sell strike (OTM call side)
        // PE hedge must be below the sell strike (OTM put side)
        if ($direction === 'up') {
            $query->where('strike', '>', $fromStrike);
            $row = $query->orderBy('strike', 'desc')  // highest strike = cheapest CE
                         ->select('instrument_key', 'open', 'strike')
                         ->first();
        } else {
            $query->where('strike', '<', $fromStrike);
            $row = $query->orderBy('strike', 'asc')   // lowest strike = cheapest PE
                         ->select('instrument_key', 'open', 'strike')
                         ->first();
        }

        // Fallback: if nothing found beyond sell strike,
        // pick the cheapest available on that side regardless of position
        if (! $row) {
            $fallbackQuery = DB::table('expired_ohlc')
                               ->where('underlying_symbol', $symbol)
                               ->where('instrument_type', $type)
                               ->where('expiry', $expiry)
                               ->where('interval', '5minute')
                               ->where('timestamp', $entryTimestamp)
                               ->where('open', '>', 0)
                               ->where('open', '<=', $maxPrice);

            $row = $direction === 'up'
                ? $fallbackQuery->orderBy('strike', 'desc')->select('instrument_key', 'open', 'strike')->first()
                : $fallbackQuery->orderBy('strike', 'asc')->select('instrument_key', 'open', 'strike')->first();

            if ($row) {
                Log::warning(
                    "WeeklyIronCondor findHedgeLeg fallback used " .
                    "type={$type} fromStrike={$fromStrike} " .
                    "foundStrike={$row->strike} price={$row->open}"
                );
            }
        }

        if (! $row) {
            return null;
        }

        Log::info(
            "WeeklyIronCondor hedge found type={$type} " .
            "strike={$row->strike} price={$row->open} " .
            "fromStrike={$fromStrike}"
        );

        return [
            'strike'           => (int) $row->strike,
            'type'             => $type,
            'instrument_key'   => $row->instrument_key,
            'entry_price'      => (float) $row->open,
            'exit_price'       => null,
            'exit_time'        => null,
            'exited'           => false,
            'qty_override'     => $hedgeLots,
            'suggested_target' => null,
        ];
    }

    /**
     * Try the exact target strike first.
     * If missing, walk inward (toward ATM) one step at a time
     * until a live strike is found or maxSteps is exhausted.
     *
     * walkDir 'down' → for upper strikes (CE/PE above ATM): walk toward ATM
     * walkDir 'up'   → for lower strikes (CE/PE below ATM): walk toward ATM
     */
    private function fetchNearestStrike(
        string $symbol,
        string $expiry,
        string $type,
        int    $targetStrike,
        string $entryTimestamp,
        int    $step,
        string $walkDir,
        int    $maxSteps
    ): ?object {
        $current = $targetStrike;

        for ($i = 0; $i <= $maxSteps; $i++) {
            $row = DB::table('expired_ohlc')
                     ->where('underlying_symbol', $symbol)
                     ->where('instrument_type', $type)
                     ->where('expiry', $expiry)
                     ->where('strike', $current)
                     ->where('interval', '5minute')
                     ->where('timestamp', $entryTimestamp)
                     ->select('instrument_key', 'open',
                         DB::raw("{$current} as strike"))
                     ->first();

            if ($row && (float) $row->open > 0) {
                if ($current !== $targetStrike) {
                    Log::info(
                        "WeeklyIronCondor fetchNearestStrike walked " .
                        "target={$targetStrike} found={$current} " .
                        "type={$type} steps={$i}"
                    );
                }
                return $row;
            }

            $current = $walkDir === 'down'
                ? $current - $step
                : $current + $step;
        }

        return null;
    }
}
