<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

class RunTradingPipeline extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:run-trading-pipeline {--skip-instruments : Skip downloading the 100MB Upstox instrument master} {--force : Force index open and strategy generation even before 09:08}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Complete end-to-end pre-market trading pipeline (Working days, Instruments, Expiries, OHLC, Trend, Index Open, Strategies)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('====================================================');
        $this->info('Starting Pre-Market Trading Pipeline: ' . Carbon::now('Asia/Kolkata')->toDateTimeString());
        $this->info('====================================================');

        // 1. Working Day Flags (08:50)
        $this->info('[1/7] Updating NSE working day flags...');
        Artisan::call('nse:update-working-day-flags');
        $this->line(trim(Artisan::output()));

        // 2. Instruments (08:52)
        if ($this->option('skip-instruments')) {
            $this->info('[2/7] Skipped Upstox instruments download (--skip-instruments).');
        } else {
            $this->info('[2/7] Fetching Upstox instruments master...');
            Artisan::call('upstox:fetch-instruments');
            $this->line(trim(Artisan::output()));
        }

        // 3. Benchmarks Expiries (09:00)
        $this->info('[3/7] Updating benchmark index expiries...');
        Artisan::call('expiries:update-benchmarks');
        $this->line(trim(Artisan::output()));

        // 4. Daily OHLC for Indices (09:01)
        $this->info('[4/7] Collecting previous day OHLC for indices and options...');
        Artisan::call('indices:collect-daily-ohlc');
        $this->line(trim(Artisan::output()));

        // 5. Populate Daily Trend (09:09)
        $this->info('[5/7] Populating daily trend and earth levels...');
        Artisan::call('trend:populate-daily');
        $this->line(trim(Artisan::output()));

        // 6 & 7. Index Open (09:13) & Daily Strategies (09:14)
        $now = Carbon::now('Asia/Kolkata');
        $currentTime = $now->format('H:i');

        if ($currentTime >= '09:08' || $this->option('force')) {
            $this->info('[6/7] Updating index open and ATM strikes from Upstox...');
            Artisan::call('trend:update-index-open');
            $this->line(trim(Artisan::output()));

            $this->info('[7/7] Generating daily strategy panels (ATM, ITM, OTM, Straddles)...');
            Artisan::call('trading:generate-daily-strategies');
            $this->line(trim(Artisan::output()));
        } else {
            $this->info("[6/7 & 7/7] Pre-market index open discovery runs after 09:08 (Current: {$currentTime}). Re-run or wait for 09:13 cron.");
        }

        $this->info('====================================================');
        $this->info('Trading Pipeline Completed: ' . Carbon::now('Asia/Kolkata')->toDateTimeString());
        $this->info('====================================================');

        return 0;
    }
}
