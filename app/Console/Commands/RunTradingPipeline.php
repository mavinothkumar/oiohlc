<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class RunTradingPipeline extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:run-trading-pipeline';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Started: Working day flags.');
        Artisan::call('nse:update-working-day-flags');
        $this->info('Working day flags updated.');

        $this->info('Started: Instruments fetch.');
        Artisan::call('upstox:fetch-instruments');
        $this->info('Instruments fetched.');

        // Repeat for others...
        $this->info('Started: Benchmarks');
        Artisan::call('expiries:update-benchmarks');
        $this->info('Benchmarks updated.');

        $this->info('Started: Daily OHLC');
        Artisan::call('indices:collect-daily-ohlc');
        $this->info('Daily OHLC collected.');

        $this->info('Started: Trend Daily');
        Artisan::call('trend:populate-daily');
        $this->info('Daily trends populated.');
    }
}
