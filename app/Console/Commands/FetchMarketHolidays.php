<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\UpstoxHolidayService;

class FetchMarketHolidays extends Command
{
    protected $signature = 'upstox:fetch-market-holidays';
    protected $description = 'Fetch market holidays from Upstox and update holidays table';

    public function handle(UpstoxHolidayService $service)
    {
        $count = $service->fetchAndStore();
        $this->info("Fetched and updated $count market holidays.");
    }
}

