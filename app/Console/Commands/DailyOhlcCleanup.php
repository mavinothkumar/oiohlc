<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DailyOhlcCleanup extends Command
{
    protected $signature = 'ohlc:cleanup-daily';
    protected $description = 'Backup current day OHLC data and clear older records';

    public function handle()
    {
        $today = DB::table('nse_working_days')->where('current',1)->first()->value('working_day');

        DB::transaction(function () use ($today) {
            // Step 1: Move today's data to backup
            DB::statement("
                INSERT IGNORE INTO ohlc_quotes_backup
                SELECT * FROM ohlc_quotes
                WHERE DATE(created_at) = ?
            ", [$today]);

            // Step 2: Delete all records except today's data
            DB::table('ohlc_quotes')
              ->where('created_at', '<', Carbon::today())
              ->delete();

            $this->info("✅ Daily OHLC cleanup completed. Today's data backed up.");
        });

        return 0;
    }
}
