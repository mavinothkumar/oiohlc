<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PopulateNseWorkingDays extends Command
{
    protected $signature = 'nse:populate-working-days {year?}';
    protected $description = 'Populate NSE working days with previous and current flags for a given year';

    public function handle()
    {
        $year = $this->argument('year') ?? Carbon::now()->year;
        $now = now();
        $this->info("Populating NSE working days for year: $year");

        // Get NSE trading holidays for the year
        $holidays = DB::table('holidays')
                      ->where('EXCHANGE', 'NSE')
                      ->where('holiday_type', 'TRADING_HOLIDAY')
                      ->whereYear('date', $year)
                      ->pluck('date')
                      ->toArray();

        $startDate = Carbon::createFromDate($year, 1, 1);
        $endDate = Carbon::createFromDate($year, 12, 31);
        $insertData = [];
        $workingDays = [];

        // Generate working days and collect batch insert data at once
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            if ($date->isWeekday() && !in_array($date->toDateString(), $holidays)) {
                $workingDays[] = $date->toDateString();
                $insertData[] = [
                    'working_date' => $date->toDateString(),
                    'previous' => 0,
                    'current' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Clear existing data for the year
        DB::table('nse_working_days')
          ->whereYear('working_date', $year)
          ->delete();

        // Batch insert all working days
        if (!empty($insertData)) {
            DB::table('nse_working_days')->insert($insertData);
        }

        // Figure out 'current' and 'previous' working day to flag
        $today = Carbon::now();
        $todayStr = ($today->year == $year) ? $today->toDateString() : end($workingDays);

        $currentIndex = array_search($todayStr, $workingDays);

        if ($currentIndex !== false) {
            // Reset all flags to 0 for safety (if table not empty)
            DB::table('nse_working_days')
              ->whereYear('working_date', $year)
              ->update(['previous' => 0, 'current' => 0]);

            // Mark current working date
            DB::table('nse_working_days')
              ->where('working_date', $workingDays[$currentIndex])
              ->update(['current' => 1]);

            // Mark previous working date if it exists
            if ($currentIndex > 0) {
                DB::table('nse_working_days')
                  ->where('working_date', $workingDays[$currentIndex - 1])
                  ->update(['previous' => 1]);
            }
        }

        $this->info('NSE working days populated successfully.');
    }
}
