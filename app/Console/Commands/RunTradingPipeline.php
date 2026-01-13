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
        Artisan::call('nse:update-working-day-flags');
        $this->info('Working day flags updated.');

        Artisan::call('upstox:fetch-instruments');
        $this->info('Instruments fetched.');

        // Repeat for others...
        Artisan::call('expiries:update-benchmarks');
        $this->info('Benchmarks updated.');

        Artisan::call('indices:collect-daily-ohlc');
        $this->info('Daily OHLC collected.');

        Artisan::call('trend:populate-daily');
        $this->info('Daily trends populated.');
    }
}
