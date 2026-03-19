<?php
// app/Console/Commands/UpdateBuildUp.php php artisan ohlc:update-buildup 2025-04-23 2026-03-10

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UpdateBuildUp extends Command
{
    protected $signature = 'ohlc:update-buildup
                            {from   : Start date in YYYY-MM-DD format}
                            {to     : End date in YYYY-MM-DD format}
                            {--interval=5minute : Candle interval — 5minute (default) or 3minute}';

    protected $description = 'Update build_up, diff_oi, diff_volume, diff_ltp on expired_ohlc table';

    /**
     * Allowed intervals and their first candle time (IST)
     */
    private const ALLOWED_INTERVALS = ['5minute', '3minute'];
    private const FIRST_CANDLE_TIME = '09:15:00';

    /**
     * Bulk update batch size — tune based on memory vs. speed trade-off
     */
    private const BATCH_SIZE = 20000;

    public function handle(): int
    {
        $interval = $this->option('interval');

        if (! in_array($interval, self::ALLOWED_INTERVALS, true)) {
            $this->error('Invalid interval. Allowed: ' . implode(', ', self::ALLOWED_INTERVALS));
            return Command::FAILURE;
        }

        try {
            $fromDate = Carbon::parse($this->argument('from'))->startOfDay();
            $toDate   = Carbon::parse($this->argument('to'))->endOfDay();
        } catch (\Exception $e) {
            $this->error('Invalid date format. Use YYYY-MM-DD.');
            return Command::FAILURE;
        }

        if ($fromDate->gt($toDate)) {
            $this->error('"from" date must be before or equal to "to" date.');
            return Command::FAILURE;
        }

        $this->info("▶ Interval  : {$interval}");
        $this->info("▶ From      : {$fromDate->toDateString()}");
        $this->info("▶ To        : {$toDate->toDateString()}");

        // Fetch distinct trading dates within range for the given interval
        $tradingDates = DB::table('expired_ohlc')
                          ->whereBetween('timestamp', [$fromDate, $toDate])
                          ->where('interval', $interval)
                          ->selectRaw('DATE(timestamp) as trade_date')
                          ->distinct()
                          ->orderBy('trade_date')
                          ->pluck('trade_date');

        if ($tradingDates->isEmpty()) {
            $this->warn('No records found for the given date range and interval.');
            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($tradingDates->count());
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $bar->start();

        $totalUpdated = 0;

        foreach ($tradingDates as $date) {
            $bar->setMessage("Processing {$date}");
            $totalUpdated += $this->processDate($date, $interval);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✅ Done! Total records updated: {$totalUpdated}");

        return Command::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // Process all instruments for a single trading date
    // -------------------------------------------------------------------------
    private function processDate(string $date, string $interval): int
    {
        $updated = 0;

        // Stream instrument keys lazily to avoid loading all into memory at once
        DB::table('expired_ohlc')
          ->whereDate('timestamp', $date)
          ->where('interval', $interval)
          ->distinct()
          ->orderBy('instrument_key')
          ->select('instrument_key')
          ->lazy(20000)
          ->each(function ($row) use ($date, $interval, &$updated) {
              $updated += $this->processInstrument($date, $row->instrument_key, $interval);
          });

        return $updated;
    }

    // -------------------------------------------------------------------------
    // Process candles for one instrument on one date
    // -------------------------------------------------------------------------
    private function processInstrument(string $date, string $instrumentKey, string $interval): int
    {
        // Max ~75 rows for 5min, ~125 for 3min — safe to load all at once
        $candles = DB::table('expired_ohlc')
                     ->whereDate('timestamp', $date)
                     ->where('instrument_key', $instrumentKey)
                     ->where('interval', $interval)
                     ->orderBy('timestamp')
                     ->get(['id', 'timestamp', 'close', 'open_interest', 'volume']);

        if ($candles->isEmpty()) {
            return 0;
        }

        $updates     = [];
        $prevCandle  = null;

        foreach ($candles as $candle) {
            $isFirstCandle = $prevCandle === null;

            if ($isFirstCandle) {
                // First candle of the day — no previous, so no diffs
                $updates[] = [
                    'id'          => $candle->id,
                    'build_up'    => null,
                    'diff_oi'     => null,
                    'diff_volume' => null,
                    'diff_ltp'    => null,
                ];
            } else {
                $diffLtp    = round($candle->close - $prevCandle->close, 2);
                $diffOi     = (int) ($candle->open_interest - $prevCandle->open_interest);
                $diffVolume = (int) ($candle->volume - $prevCandle->volume);
                $buildUp    = $this->determineBuildUp($diffLtp, $diffOi);

                $updates[] = [
                    'id'          => $candle->id,
                    'build_up'    => $buildUp,
                    'diff_oi'     => $diffOi,
                    'diff_volume' => $diffVolume,
                    'diff_ltp'    => $diffLtp,
                ];
            }

            $prevCandle = $candle;
        }

        // Flush in batches for memory efficiency
        foreach (array_chunk($updates, self::BATCH_SIZE) as $batch) {
            $this->bulkUpdate($batch);
        }

        return count($updates);
    }

    // -------------------------------------------------------------------------
    // Determine build-up type from LTP diff and OI diff
    //
    //  LTP ↑  OI ↑  → Long Build-up   (fresh longs entering)
    //  LTP ↓  OI ↑  → Short Build-up  (fresh shorts entering)
    //  LTP ↓  OI ↓  → Long Unwinding  (longs exiting)
    //  LTP ↑  OI ↓  → Short Covering  (shorts exiting)
    // -------------------------------------------------------------------------
    private function determineBuildUp(float $diffLtp, int $diffOi): string
    {
        if ($diffLtp > 0 && $diffOi > 0) {
            return 'Long Build';
        }

        if ($diffLtp < 0 && $diffOi > 0) {
            return 'Short Build';
        }

        if ($diffLtp < 0 && $diffOi < 0) {
            return 'Long Unwind';
        }

        if ($diffLtp > 0 && $diffOi < 0) {
            return 'Short Cover';
        }

        // No change in either direction (flat candle)
        return 'neutral';
    }

    // -------------------------------------------------------------------------
    // Single-statement bulk UPDATE via CASE/WHEN — avoids N+1 queries
    // -------------------------------------------------------------------------
    private function bulkUpdate(array $updates): void
    {
        if (empty($updates)) {
            return;
        }

        $ids      = array_column($updates, 'id');
        $bindings = [];

        $buCase  = '';
        $oiCase  = '';
        $volCase = '';
        $ltpCase = '';

        foreach ($updates as $u) {
            $buCase  .= 'WHEN ? THEN ? ';
            $oiCase  .= 'WHEN ? THEN ? ';
            $volCase .= 'WHEN ? THEN ? ';
            $ltpCase .= 'WHEN ? THEN ? ';
        }

        // Bindings: build_up values
        foreach ($updates as $u) {
            $bindings[] = $u['id'];
            $bindings[] = $u['build_up'];
        }
        // diff_oi values
        foreach ($updates as $u) {
            $bindings[] = $u['id'];
            $bindings[] = $u['diff_oi'];
        }
        // diff_volume values
        foreach ($updates as $u) {
            $bindings[] = $u['id'];
            $bindings[] = $u['diff_volume'];
        }
        // diff_ltp values
        foreach ($updates as $u) {
            $bindings[] = $u['id'];
            $bindings[] = $u['diff_ltp'];
        }
        // WHERE IN ids
        foreach ($ids as $id) {
            $bindings[] = $id;
        }

        $inPlaceholders = implode(',', array_fill(0, count($ids), '?'));

        DB::statement("
            UPDATE expired_ohlc
            SET
                build_up    = CASE id {$buCase}  ELSE build_up    END,
                diff_oi     = CASE id {$oiCase}  ELSE diff_oi     END,
                diff_volume = CASE id {$volCase} ELSE diff_volume  END,
                diff_ltp    = CASE id {$ltpCase} ELSE diff_ltp     END
            WHERE id IN ({$inPlaceholders})
        ", $bindings);
    }
}
