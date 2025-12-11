<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RunSixLevelBacktestAll extends Command
{
    protected $signature = 'backtest:six-level-all
                            {--symbol=NIFTY}';

    protected $description = 'Run six levels break backtest for all expiries of a symbol';

    public function handle(): int
    {
        $symbol = $this->option('symbol');

        $this->info("Starting six-level backtest for ALL expiries of {$symbol}");

        // Get all expiries for the symbol
        $expiries = DB::table('expired_expiries')
                      ->where('underlying_symbol', $symbol)
                      ->orderBy('expiry_date')
                      ->pluck('expiry_date');

        if ($expiries->isEmpty()) {
            $this->warn("No expiries found for {$symbol}");
            return Command::SUCCESS;
        }

        foreach ($expiries as $expiry) {
            $this->info("Running backtest for expiry {$expiry}");

            // Call your existing single-expiry command
            $this->call('backtest:six-level', [
                'expiry'   => $expiry,
                '--symbol' => $symbol,
            ]);
        }

        $this->info('Finished six-level backtest for all expiries.');

        return Command::SUCCESS;
    }
}
