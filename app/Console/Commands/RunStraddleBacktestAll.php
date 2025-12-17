<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RunStraddleBacktestAll extends Command
{
    protected $signature = 'backtest:straddle-all
                            {--symbol=NIFTY}';

    protected $description = 'Run intraday straddle backtest for all expiries of a symbol';

    public function handle(): int
    {
        $symbol = $this->option('symbol');

        $this->info("Starting straddle backtest for ALL expiries of {$symbol}");

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
            $this->info("Running straddle backtest for expiry {$expiry}");

            // Call your existing single-expiry straddle command
            $this->call('backtest:straddle', [
                'expiry'   => $expiry,
                '--symbol' => $symbol,
            ]);
        }

        $this->info('Finished straddle backtest for all expiries.');

        return Command::SUCCESS;
    }
}
