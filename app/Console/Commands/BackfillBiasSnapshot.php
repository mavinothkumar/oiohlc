<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\BiasSnapshot;

class BackfillBiasSnapshot extends Command
{
    protected $signature = 'bias:backfill
                            {symbol=NIFTY}
                            {--strikes=3}
                            {--date= : Date to backfill (Y-m-d), defaults to today}
                            {--from=09:15 : Start time (H:i)}
                            {--to=15:30 : End time (H:i)}
                            {--interval=5 : Interval in minutes between snapshots}
                            {--force : Overwrite existing snapshots for the same minute}';

    protected $description = 'Backfill bias snapshots for a given time range (replays missed intervals)';

    public function handle(): void
    {
        $symbol   = strtoupper($this->argument('symbol'));
        $strikes  = (int) $this->option('strikes');
        $date     = $this->option('date') ?? Carbon::today()->toDateString();
        $interval = (int) $this->option('interval');
        $force    = $this->option('force');

        // ── Parse from / to ───────────────────────────────────────────
        try {
            $from = Carbon::createFromFormat('Y-m-d H:i', "$date {$this->option('from')}");
            $to   = Carbon::createFromFormat('Y-m-d H:i', "$date {$this->option('to')}");
        } catch (\Exception $e) {
            $this->error("Invalid --from or --to time format. Use H:i (e.g. 09:20)");
            return;
        }

        if ($from->gt($to)) {
            $this->error("--from must be earlier than --to.");
            return;
        }

        // ── Get expiry ────────────────────────────────────────────────
        $expiry = DB::table('nse_expiries')
                    ->where('trading_symbol', $symbol)
                    ->where('instrument_type', 'OPT')
                    ->where('is_current', 1)
                    ->first();

        if (! $expiry) {
            $this->warn("No active expiry found for $symbol. Skipping.");
            return;
        }

        $expiryDate = $expiry->expiry_date;

        // ── Build strike list (we'll recompute per-slot using spot at that time) ──
        $slots = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $slots[] = $cursor->copy();
            $cursor->addMinutes($interval);
        }

        $this->info("📅 Backfilling $symbol | $date | {$from->format('H:i')} → {$to->format('H:i')} | " . count($slots) . " slots");
        $bar = $this->output->createProgressBar(count($slots));
        $bar->start();

        $saved   = 0;
        $skipped = 0;

        foreach ($slots as $slotTime) {
            $slotStr = $slotTime->toDateTimeString(); // e.g. 2026-03-25 09:20:00

            // ── Skip if snapshot already exists for this minute ───────
            if (! $force) {
                $exists = BiasSnapshot::where('trading_symbol', $symbol)
                                      ->where('date', $date)
                                      ->whereBetween('captured_at', [
                                          $slotTime->copy()->subSeconds(30)->toDateTimeString(),
                                          $slotTime->copy()->addSeconds(30)->toDateTimeString(),
                                      ])
                                      ->exists();

                if ($exists) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }
            }

            // ── Get spot price at or before this slot ─────────────────
            $latest = DB::table('option_chains')
                        ->where('trading_symbol', $symbol)
                        ->where('expiry', $expiryDate)
                        ->where('captured_at', '<=', $slotStr)
                        ->orderByDesc('captured_at')
                        ->first(['underlying_spot_price']);

            if (! $latest) {
                $bar->advance();
                continue;
            }

            $spotPrice     = $latest->underlying_spot_price;
            $nearestStrike = round($spotPrice / 50) * 50;

            // ── Build strike list ─────────────────────────────────────
            $strikeList = [];
            for ($i = -$strikes; $i <= $strikes; $i++) {
                $strikeList[] = $nearestStrike + ($i * 50);
            }

            // ── Fetch rows up to this slot time ───────────────────────
            $dayStart = $date . ' 09:15:00';

            $rows = DB::table('option_chains')
                      ->where('trading_symbol', $symbol)
                      ->where('expiry', $expiryDate)
                      ->whereIn('strike_price', $strikeList)
                      ->whereBetween('captured_at', [$dayStart, $slotStr])
                      ->orderBy('captured_at')
                      ->get(['strike_price', 'option_type', 'diff_oi', 'diff_volume', 'diff_ltp', 'build_up', 'captured_at']);

            if ($rows->isEmpty()) {
                $bar->advance();
                continue;
            }

            // ── Compute buildUpTotals ─────────────────────────────────
            $buildUpTotals = [
                'CE' => ['Long Build' => ['oi' => 0, 'volume' => 0], 'Short Build' => ['oi' => 0, 'volume' => 0],
                         'Short Cover' => ['oi' => 0, 'volume' => 0], 'Long Unwind' => ['oi' => 0, 'volume' => 0]],
                'PE' => ['Long Build' => ['oi' => 0, 'volume' => 0], 'Short Build' => ['oi' => 0, 'volume' => 0],
                         'Short Cover' => ['oi' => 0, 'volume' => 0], 'Long Unwind' => ['oi' => 0, 'volume' => 0]],
            ];

            foreach ($rows as $row) {
                $diffOi  = $row->diff_oi     ?? 0;
                $diffLtp = $row->diff_ltp    ?? 0;
                $diffVol = $row->diff_volume ?? 0;
                $type    = $row->option_type;

                $buildUp = $row->build_up ?? $this->classifyBuildUp($diffOi, $diffLtp);

                if ($buildUp && isset($buildUpTotals[$type][$buildUp])) {
                    $buildUpTotals[$type][$buildUp]['oi']     += abs($diffOi);
                    $buildUpTotals[$type][$buildUp]['volume'] += abs($diffVol);
                }
            }

            // ── Compute bias score ────────────────────────────────────
            $bullishOI =
                ($buildUpTotals['CE']['Long Build']['oi']  * 2) +
                ($buildUpTotals['CE']['Short Cover']['oi'] * 1) +
                ($buildUpTotals['PE']['Short Build']['oi'] * 2) +
                ($buildUpTotals['PE']['Long Unwind']['oi'] * 1);

            $bearishOI =
                ($buildUpTotals['CE']['Short Build']['oi'] * 2) +
                ($buildUpTotals['CE']['Long Unwind']['oi'] * 1) +
                ($buildUpTotals['PE']['Long Build']['oi']  * 2) +
                ($buildUpTotals['PE']['Short Cover']['oi'] * 1);

            $totalWeightedOI = $bullishOI + $bearishOI;

            $biasScore = $totalWeightedOI > 0
                ? round((($bullishOI - $bearishOI) / $totalWeightedOI) * 100)
                : 0;

            $bias = match (true) {
                $biasScore > 20  => 'Bullish',
                $biasScore < -20 => 'Bearish',
                default          => 'Sideways',
            };

            $biasStrength = match (true) {
                abs($biasScore) >= 60 => 'Strong',
                abs($biasScore) >= 35 => 'Moderate',
                default               => 'Weak',
            };

            $totalVolume = array_sum(array_column($buildUpTotals['CE'], 'volume'))
                           + array_sum(array_column($buildUpTotals['PE'], 'volume'));

            // ── Save snapshot ─────────────────────────────────────────
            BiasSnapshot::create([
                'trading_symbol'     => $symbol,
                'date'               => $date,
                'expiry_date'        => $expiryDate,
                'spot_price'         => $spotPrice,
                'atm_strike'         => $nearestStrike,
                'strikes_range'      => $strikes,
                'bias_score'         => $biasScore,
                'bias'               => $bias,
                'bias_strength'      => $biasStrength,

                'ce_long_build_oi'   => $buildUpTotals['CE']['Long Build']['oi'],
                'ce_short_build_oi'  => $buildUpTotals['CE']['Short Build']['oi'],
                'ce_short_cover_oi'  => $buildUpTotals['CE']['Short Cover']['oi'],
                'ce_long_unwind_oi'  => $buildUpTotals['CE']['Long Unwind']['oi'],

                'ce_long_build_vol'  => $buildUpTotals['CE']['Long Build']['volume'],
                'ce_short_build_vol' => $buildUpTotals['CE']['Short Build']['volume'],
                'ce_short_cover_vol' => $buildUpTotals['CE']['Short Cover']['volume'],
                'ce_long_unwind_vol' => $buildUpTotals['CE']['Long Unwind']['volume'],

                'pe_long_build_oi'   => $buildUpTotals['PE']['Long Build']['oi'],
                'pe_short_build_oi'  => $buildUpTotals['PE']['Short Build']['oi'],
                'pe_short_cover_oi'  => $buildUpTotals['PE']['Short Cover']['oi'],
                'pe_long_unwind_oi'  => $buildUpTotals['PE']['Long Unwind']['oi'],

                'pe_long_build_vol'  => $buildUpTotals['PE']['Long Build']['volume'],
                'pe_short_build_vol' => $buildUpTotals['PE']['Short Build']['volume'],
                'pe_short_cover_vol' => $buildUpTotals['PE']['Short Cover']['volume'],
                'pe_long_unwind_vol' => $buildUpTotals['PE']['Long Unwind']['volume'],

                'bullish_oi'         => $bullishOI,
                'bearish_oi'         => $bearishOI,
                'total_volume'       => $totalVolume,
                'captured_at'        => $slotTime,  // ← use the historical slot time, not now()
            ]);

            $saved++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("✅ Done! Saved: $saved | Skipped (already exist): $skipped");
    }

    private function classifyBuildUp(int|float $diffOi, int|float $diffLtp): ?string
    {
        if ($diffOi == 0 || $diffLtp == 0) return null;
        if ($diffOi > 0 && $diffLtp > 0)   return 'Long Build';
        if ($diffOi > 0 && $diffLtp < 0)   return 'Short Build';
        if ($diffOi < 0 && $diffLtp < 0)   return 'Long Unwind';
        if ($diffOi < 0 && $diffLtp > 0)   return 'Short Cover';
        return null;
    }
}
