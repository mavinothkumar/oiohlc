<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UpdateNseCurrentPreviousWorkingDay extends Command
{
    protected $signature = 'nse:update-working-day-flags';
    protected $description = 'Update previous and current flags on NSE working days daily';

    public function handle()
    {
        $today = Carbon::now()->toDateString();

        // Find all future and past working days
        $workingDays = DB::table('nse_working_days')
                         ->orderBy('working_date')
                         ->pluck('working_date')
                         ->toArray();

        // Find today's working date (might not be a working day)
        $current = null; $previous = null;
        foreach ($workingDays as $i => $date) {
            if ($date >= $today) {
                $current = $date;
                $previous = $i > 0 ? $workingDays[$i - 1] : null;
                break;
            }
        }

        // Reset all flags
        DB::table('nse_working_days')->update(['current' => 0, 'previous' => 0]);
        // Set current and previous
        if ($current) {
            DB::table('nse_working_days')->where('working_date', $current)->update(['current' => 1]);
        }
        if ($previous) {
            DB::table('nse_working_days')->where('working_date', $previous)->update(['previous' => 1]);
        }

        $this->info("Updated NSE working days: current=$current, previous=$previous");
        info("Updated NSE working days: current=$current, previous=$previous");
    }
}
