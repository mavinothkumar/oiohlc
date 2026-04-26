<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateDailyTrendTradingDate extends Command
{
    protected $signature = 'daily-trend:update-trading-date
                            {--date= : Update only records for a specific quote_date (Y-m-d). Defaults to all NULL records.}
                            {--force : Re-update all records, even those already populated.}';

    protected $description = 'Populate trading_date on daily_trend with the next NSE working day after quote_date.';

    public function handle(): int
    {
        $specificDate = $this->option('date');
        $force        = $this->option('force');

        // Build the subquery: for a given quote_date, find the smallest
        // working_date in nse_working_days that is strictly greater.
        $query = DB::table('daily_trend as dt')
            ->whereNotNull('dt.quote_date');

        if ($specificDate) {
            $query->where('dt.quote_date', $specificDate);
            $this->info("Updating records for quote_date = {$specificDate}");
        } elseif (! $force) {
            $query->whereNull('dt.trading_date');
            $this->info('Updating records where trading_date is NULL.');
        } else {
            $this->info('Force mode: re-updating ALL records.');
        }

        $total = (clone $query)->count();
        $this->info("Total records to process: {$total}");

        if ($total === 0) {
            $this->warn('Nothing to update.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updated = 0;

        // Chunk to avoid memory issues on large tables
        (clone $query)
            ->select('dt.id', 'dt.quote_date')
            ->orderBy('dt.quote_date')
            ->chunk(500, function ($rows) use (&$updated, $bar) {
                foreach ($rows as $row) {
                    $nextWorkingDay = DB::table('nse_working_days')
                        ->where('working_date', '>', $row->quote_date)
                        ->orderBy('working_date')
                        ->value('working_date');

                    if ($nextWorkingDay) {
                        DB::table('daily_trend')
                            ->where('id', $row->id)
                            ->update(['trading_date' => $nextWorkingDay]);
                        $updated++;
                    } else {
                        $this->newLine();
                        $this->warn("No next working day found for quote_date: {$row->quote_date} (id: {$row->id})");
                    }

                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine();
        $this->info("Done. Updated {$updated} / {$total} records.");

        return self::SUCCESS;
    }
}
