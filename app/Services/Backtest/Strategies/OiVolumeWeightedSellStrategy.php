<?php

namespace App\Services\Backtest\Strategies;


use App\Services\Backtest\Contracts\BacktestStrategy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OiVolumeWeightedSellStrategy extends BacktestStrategy
{
    public function resolveLegs(
        string $symbol,
        float  $indexOpen,
        string $tradeDate,
        string $entryTimestamp,
        array  $options
    ): ?array {
        $minPremium   = (float) ($options['min-premium']   ?? 70);
        $maxImbalance = (float) ($options['max-imbalance'] ?? 100);
        $minGap       = (float) ($options['min-gap']       ?? 50);
        $step         = (int)   ($options['step']          ?? 50);
        $maxWalk      = 6;

        // ── Step 1 — Gap filter ───────────────────────────────────────────
        $gapRow = DB::table('index_gap')
                    ->where('symbol_name', $symbol)
                    ->whereDate('trading_date', $tradeDate)
                    ->first();

        $gapAbs          = (float) ($gapRow?->gap_abs            ?? 0);
        $gapPctPrevClose = (float) ($gapRow?->gap_pct_prev_close ?? 0);
        $gapPctPrevRange = (float) ($gapRow?->gap_pct_prev_range ?? 0);
        $prevDayRange    = (float) ($gapRow?->previous_day_range ?? 0);
        $gapType         = $gapRow?->gap_type ?? 'Flat';

        $minGap     = (float) ($options['min-gap']      ?? 0);
        $minGapPct  = (float) ($options['min-gap-pct']  ?? 0);   // % of prev close
        $minRangePct = (float) ($options['min-range-pct'] ?? 0); // % of prev day range
        $gapMode    = strtolower($options['gap-mode']   ?? 'abs'); // abs | pct | range | any | all

        $gapFail = match ($gapMode) {
            // Traditional: gap_abs must be >= threshold
            'abs'   => $minGap > 0   && $gapAbs < $minGap,

            // Gap as % of previous close must be >= threshold (e.g. 0.3 = 0.3%)
            'pct'   => $minGapPct > 0 && abs($gapPctPrevClose) < $minGapPct,

            // Gap as % of previous day range must be >= threshold (e.g. 20 = 20%)
            'range' => $minRangePct > 0 && abs($gapPctPrevRange) < $minRangePct,

            // Pass if ANY one of the active thresholds is met
            'any'   => !(
                ($minGap      <= 0 || $gapAbs              >= $minGap)      ||
                ($minGapPct   <= 0 || abs($gapPctPrevClose) >= $minGapPct)  ||
                ($minRangePct <= 0 || abs($gapPctPrevRange)  >= $minRangePct)
            ),

            // Pass only if ALL active thresholds are met
            'all'   => (
                ($minGap      > 0 && $gapAbs              < $minGap)      ||
                ($minGapPct   > 0 && abs($gapPctPrevClose) < $minGapPct)  ||
                ($minRangePct > 0 && abs($gapPctPrevRange)  < $minRangePct)
            ),

            default => false,
        };

        if ($gapFail) {
            Log::info(
                "OiVolumeWeighted SKIP [{$tradeDate}] gap_too_small " .
                "mode={$gapMode} gap_abs={$gapAbs} " .
                "gap_pct_prev_close={$gapPctPrevClose}% " .
                "gap_pct_prev_range={$gapPctPrevRange}% " .
                "prev_range={$prevDayRange} gap_type={$gapType}"
            );
            return $this->skip('gap_too_small');
        }

        // ── Step 2 — Expiry ───────────────────────────────────────────────
        $expiry = DB::table('expired_expiries')
                    ->where('underlying_symbol', $symbol)
                    ->where('instrument_type', 'OPT')
                    ->where('expiry_date', '>=', $tradeDate)
                    ->orderBy('expiry_date')
                    ->value('expiry_date');

        if (! $expiry) {
            Log::info("OiVolumeWeighted SKIP [{$tradeDate}] no_expiry");
            return $this->skip('no_expiry');
        }

        // ── Step 3 — ATM ──────────────────────────────────────────────────
        $atm = (int) (round($indexOpen / $step) * $step);

        // ── Step 4 — OI+Volume score (09:15–09:45 window) ─────────────────
        $scores = DB::table('expired_ohlc')
                    ->where('underlying_symbol', $symbol)
                    ->whereIn('instrument_type', ['CE', 'PE'])
                    ->where('expiry', $expiry)
                    ->where('interval', '5minute')
                    ->whereBetween('timestamp', ["{$tradeDate} 09:15:00", "{$tradeDate} 09:45:00"])
                    ->whereNotNull('diff_oi')
                    ->whereNotNull('diff_volume')
                    ->selectRaw('strike, instrument_type,
                          SUM(diff_oi)     AS sum_oi,
                          SUM(diff_volume) AS sum_vol,
                          SUM(diff_oi) + SUM(diff_volume) AS total_score')
                    ->groupBy('strike', 'instrument_type')
                    ->get();

        if ($scores->isEmpty()) {
            Log::info("OiVolumeWeighted SKIP [{$tradeDate}] no_oi_data expiry={$expiry}");
            return $this->skip('no_oi_data');
        }

        // ── Step 5 — Best CE strike (>= ATM, highest score) ───────────────
        $ceBest = $scores
            ->where('instrument_type', 'CE')
            ->where('strike', '>=', $atm)
            ->sortByDesc('total_score')
            ->first();

        if (! $ceBest) {
            Log::info("OiVolumeWeighted SKIP [{$tradeDate}] no_ce_strike atm={$atm}");
            return $this->skip('no_ce_strike');
        }

        // ── Step 6 — Best PE strike (<= ATM, highest score) ───────────────
        $peBest = $scores
            ->where('instrument_type', 'PE')
            ->where('strike', '<=', $atm)
            ->sortByDesc('total_score')
            ->first();

        if (! $peBest) {
            Log::info("OiVolumeWeighted SKIP [{$tradeDate}] no_pe_strike atm={$atm}");
            return $this->skip('no_pe_strike');
        }

        // ── Step 7 — Divergence context (log only, never skips) ───────────
        $ceScore  = (float) $ceBest->total_score;
        $peScore  = (float) $peBest->total_score;
        $maxScore = max($ceScore, $peScore);
        $divPct   = $maxScore > 0 ? abs($ceScore - $peScore) / $maxScore * 100 : 0;

        $context = match (true) {
            $divPct > 30 && $ceScore > $peScore => 'CE_DOMINANT (bearish pressure)',
            $divPct > 30 && $peScore > $ceScore => 'PE_DOMINANT (bullish pressure)',
            default                             => 'BALANCED',
        };

        Log::info(
            "OiVolumeWeighted [{$tradeDate}] context={$context} " .
            "CE_strike={$ceBest->strike} score={$ceScore} | " .
            "PE_strike={$peBest->strike} score={$peScore} | " .
            "div={$divPct}% | gap_abs={$gapAbs} " .
            "gap_pct_prev_close={$gapPctPrevClose}% " .
            "gap_pct_prev_range={$gapPctPrevRange}% prev_range={$prevDayRange}"
        );

        // ── Step 8 — LTP at entry with premium walk ────────────────────────
        [$ceStrike, $ceLtp] = $this->resolveStrikeWithPremium(
            $symbol, $expiry, (int) $ceBest->strike, 'CE',
            $atm, $step, $maxWalk, $entryTimestamp, $minPremium, 'up'
        );

        if ($ceStrike === null) {
            Log::info("OiVolumeWeighted SKIP [{$tradeDate}] below_min_premium_ce after {$maxWalk} walk steps");
            return $this->skip('below_min_premium_ce');
        }

        [$peStrike, $peLtp] = $this->resolveStrikeWithPremium(
            $symbol, $expiry, (int) $peBest->strike, 'PE',
            $atm, $step, $maxWalk, $entryTimestamp, $minPremium, 'down'
        );

        if ($peStrike === null) {
            Log::info("OiVolumeWeighted SKIP [{$tradeDate}] below_min_premium_pe after {$maxWalk} walk steps");
            return $this->skip('below_min_premium_pe');
        }

        // ── Step 9 — Imbalance check ───────────────────────────────────────
        $ceDistance = abs($ceStrike - $atm);
        $peDistance = abs($atm - $peStrike);
        $imbalance  = abs($ceDistance - $peDistance);

        if ($imbalance > $maxImbalance) {
            Log::info(
                "OiVolumeWeighted SKIP [{$tradeDate}] imbalance={$imbalance} > {$maxImbalance} " .
                "CE={$ceStrike}(+{$ceDistance}) PE={$peStrike}(-{$peDistance})"
            );
            return $this->skip('imbalance');
        }

        // ── Step 10 — Fetch instrument keys ───────────────────────────────
        $ceKey = DB::table('expired_ohlc')
                   ->where('underlying_symbol', $symbol)
                   ->where('instrument_type', 'CE')
                   ->where('expiry', $expiry)
                   ->where('strike', $ceStrike)
                   ->where('interval', '5minute')
                   ->where('timestamp', $entryTimestamp)
                   ->value('instrument_key');

        $peKey = DB::table('expired_ohlc')
                   ->where('underlying_symbol', $symbol)
                   ->where('instrument_type', 'PE')
                   ->where('expiry', $expiry)
                   ->where('strike', $peStrike)
                   ->where('interval', '5minute')
                   ->where('timestamp', $entryTimestamp)
                   ->value('instrument_key');

        if (! $ceKey || ! $peKey) {
            Log::info(
                "OiVolumeWeighted SKIP [{$tradeDate}] no_entry_candle " .
                "CE_key=" . ($ceKey ?? 'null') . " PE_key=" . ($peKey ?? 'null')
            );
            return $this->skip('no_entry_candle');
        }

        // ── Step 11 — Dynamic target ──────────────────────────────────────
        $totalPremium    = $ceLtp + $peLtp;
        $suggestedTarget = match (true) {
            $totalPremium > 350 => 18000,
            $totalPremium > 270 => 16000,
            $totalPremium > 200 => 14000,
            default             => 13000,
        };

        Log::info(
            "OiVolumeWeighted [{$tradeDate}] TRADE ✓ " .
            "CE={$ceStrike}@{$ceLtp} PE={$peStrike}@{$peLtp} " .
            "totalPremium={$totalPremium} suggestedTarget=₹{$suggestedTarget} " .
            "atm={$atm} gap_abs={$gapAbs}"
        );

        return [
            [
                'strike'           => $ceStrike,
                'type'             => 'CE',
                'instrument_key'   => $ceKey,
                'entry_price'      => $ceLtp,
                'exit_price'       => null,
                'exit_time'        => null,
                'exited'           => false,
                'suggested_target' => $suggestedTarget,
                'qty_override'     => null,
            ],
            [
                'strike'           => $peStrike,
                'type'             => 'PE',
                'instrument_key'   => $peKey,
                'entry_price'      => $peLtp,
                'exit_price'       => null,
                'exit_time'        => null,
                'exited'           => false,
                'suggested_target' => $suggestedTarget,
                'qty_override'     => null,
            ],
        ];
    }

    public function describe(array $options): string
    {
        $minPremium    = $options['min-premium']   ?? 70;
        $maxImbalance  = $options['max-imbalance'] ?? 100;
        $minGap        = $options['min-gap']       ?? 0;
        $minGapPct     = $options['min-gap-pct']   ?? 0;
        $minRangePct   = $options['min-range-pct'] ?? 0;
        $gapMode       = $options['gap-mode']      ?? 'abs';

        $gapDesc = match ($gapMode) {
            'pct'   => "GapFilter≥{$minGapPct}% of prev_close",
            'range' => "GapFilter≥{$minRangePct}% of prev_range",
            'any'   => "GapFilter ANY(abs≥{$minGap} OR pct≥{$minGapPct}% OR range≥{$minRangePct}%)",
            'all'   => "GapFilter ALL(abs≥{$minGap} AND pct≥{$minGapPct}% AND range≥{$minRangePct}%)",
            default => $minGap > 0 ? "GapFilter≥{$minGap}" : "GapFilter=OFF",
        };

        return "OI+Volume Weighted Sell | Window 09:15-09:45 | MinPremium={$minPremium} | {$gapDesc} | MaxImbalance={$maxImbalance}pts";
    }

    private function resolveStrikeWithPremium(
        string $symbol,
        string $expiry,
        int    $strike,
        string $type,
        int    $atm,
        int    $step,
        int    $maxWalk,
        string $entryTimestamp,
        float  $minPremium,
        string $walkDir
    ): array {
        $current = $strike;

        for ($i = 0; $i <= $maxWalk; $i++) {
            if ($walkDir === 'up'   && $current < $atm) break;
            if ($walkDir === 'down' && $current > $atm) break;

            $ltp = (float) DB::table('expired_ohlc')
                             ->where('underlying_symbol', $symbol)
                             ->where('instrument_type', $type)
                             ->where('expiry', $expiry)
                             ->where('strike', $current)
                             ->where('interval', '5minute')
                             ->where('timestamp', $entryTimestamp)
                             ->value('open');

            if ($ltp >= $minPremium) {
                return [$current, $ltp];
            }

            $current = $walkDir === 'up'
                ? $current - $step
                : $current + $step;
        }

        return [null, null];
    }
}
