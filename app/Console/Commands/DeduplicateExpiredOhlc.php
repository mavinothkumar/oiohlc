<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeduplicateExpiredOhlc extends Command
{
    protected $signature = 'ohlc:deduplicate
                        {--chunk=1000       : Rows to delete per batch}
                        {--from=            : Start from this date (YYYY-MM-DD), overrides saved progress}
                        {--to=              : End at this date (YYYY-MM-DD) inclusive}
                        {--only=            : Process this single date only (YYYY-MM-DD)}
                        {--dry-run          : Show counts without deleting}
                        {--reset            : Clear saved progress and start from the beginning}
                        {--days=0           : Max number of working days to process per run (0 = all)}';

    protected $description = 'Remove duplicates from expired_ohlc day-by-day with resume support';

    private string $progressFile = 'deduplicate_ohlc_progress.json';

    public function handle(): int
    {
        if ($this->option('reset')) {
            Storage::delete($this->progressFile);
            $this->info('Progress reset. Will start from the beginning on next run.');
            return self::SUCCESS;
        }

        $workingDays = $this->getWorkingDays();

        if (empty($workingDays)) {
            $this->warn('No working days found in nse_working_days.');
            return self::SUCCESS;
        }

        $maxDays    = (int) $this->option('days');
        $processed  = 0;
        $grandTotal = 0;

        $this->info(sprintf(
            'Found %d working day(s) to process%s.',
            count($workingDays),
            $maxDays > 0 ? " (max {$maxDays} per run)" : ''
        ));

        foreach ($workingDays as $date) {
            if ($maxDays > 0 && $processed >= $maxDays) {
                $this->warn("Reached --days={$maxDays} limit. Run again to continue.");
                break;
            }

            $deleted     = $this->processDay($date);
            $grandTotal += $deleted;
            $processed++;

            // Save progress after each successfully completed day
            if (! $this->option('dry-run') && ! $this->option('only')) {
                $this->saveProgress($date);
            }
        }

        $this->newLine();
        $this->info("Done. Total deleted this run: {$grandTotal} rows across {$processed} day(s).");

        return self::SUCCESS;
    }

    private function collectAllDuplicateIds(string $tsFrom, string $tsTill): array
    {
        // Step 1: find the min(id) to KEEP per duplicate group
        $keepers = DB::table('expired_ohlc')
                     ->selectRaw('MIN(id) as min_id')
                     ->whereBetween('timestamp', [$tsFrom, $tsTill])
                     ->groupBy('instrument_key', 'interval', 'timestamp')
                     ->havingRaw('COUNT(*) > 1')
                     ->pluck('min_id')
                     ->toArray();
        $this->line('collectAllDuplicateIds $keepers Ends: '. now()->toDateTimeString());
        if (empty($keepers)) {
            return [];
        }

        // Step 2: get all IDs in duplicate groups EXCEPT the keepers
        return DB::table('expired_ohlc')
                 ->whereBetween('timestamp', [$tsFrom, $tsTill])
                 ->whereNotIn('id', $keepers)
                 ->whereIn('instrument_key', function ($sub) use ($tsFrom, $tsTill) {
                     $sub->select('instrument_key')
                         ->from('expired_ohlc')
                         ->whereBetween('timestamp', [$tsFrom, $tsTill])
                         ->groupBy('instrument_key', 'interval', 'timestamp')
                         ->havingRaw('COUNT(*) > 1');
                 })
                 ->pluck('id')
                 ->toArray();
    }

    // -------------------------------------------------------------------------
    // Working days list
    // -------------------------------------------------------------------------

    private function getWorkingDays(): array
    {
        // --only: single specific date
        if ($this->option('only')) {
            return [$this->option('only')];
        }

        $lastCompleted = $this->option('from') ?? $this->loadProgress();
        $toDate        = $this->option('to');

        $query = DB::table('nse_working_days')
                   ->select('working_date')
                   ->orderBy('working_date', 'asc');

        if ($lastCompleted) {
            $query->where('working_date', '>', $lastCompleted);
            $this->info("Resuming from after: {$lastCompleted}");
        }

        if ($toDate) {
            $query->where('working_date', '<=', $toDate);
            $this->info("Processing up to:     {$toDate}");
        }

        return $query->pluck('working_date')->toArray();
    }

    // -------------------------------------------------------------------------
    // Per-day processing
    // -------------------------------------------------------------------------

    private function processDay(string $date): int
    {
        $tsFrom = $date . ' 09:15:00';
        $tsTill = $date . ' 15:30:00';

        $this->line("  <fg=cyan>[ {$date} ]</> Scanning...");

        $this->line('collectAllDuplicateIds Starts: '. now()->toDateTimeString());
        // ONE query — get every duplicate ID for this day upfront
        $ids = $this->collectAllDuplicateIds($tsFrom, $tsTill);
        $this->line('collectAllDuplicateIds End: '. now()->toDateTimeString());
        if (empty($ids)) {
            $this->line("  <fg=gray>[ {$date} ]</> No duplicates.");
            return 0;
        }

        $total = count($ids);
        $this->line("  <fg=yellow>[ {$date} ]</> {$total} duplicate rows found.");

        if ($this->option('dry-run')) {
            return 0;
        }

        // Chunk the pre-fetched ID list — each delete is a pure PK lookup (instant)
        $deleted   = 0;
        $chunkSize = (int) $this->option('chunk');
        $chunks    = array_chunk($ids, $chunkSize);

        foreach ($chunks as $chunk) {
            $deleted += DB::table('expired_ohlc')->whereIn('id', $chunk)->delete();
        }

        $this->line("  <fg=green>[ {$date} ]</> Deleted: {$deleted} rows.");

        return $deleted;
    }

    // -------------------------------------------------------------------------
    // Progress persistence  (storage/app/deduplicate_ohlc_progress.json)
    // -------------------------------------------------------------------------

    private function saveProgress(string $date): void
    {
        Storage::put($this->progressFile, json_encode([
            'last_completed_date' => $date,
            'updated_at'          => now()->toDateTimeString(),
        ]));
    }

    private function loadProgress(): ?string
    {
        if (! Storage::exists($this->progressFile)) {
            return null;
        }

        $data = json_decode(Storage::get($this->progressFile), true);

        return $data['last_completed_date'] ?? null;
    }
}
