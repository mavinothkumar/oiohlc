<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class OrderFlowDaemonCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orderflow:daemon {--symbol=NIFTY : Trading symbol to monitor}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the headless 30-depth order flow streaming and intelligence persistence daemon';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $symbol = strtoupper($this->option('symbol') ?: 'NIFTY');

        $this->info("=================================================");
        $this->info("🚀 Starting Headless 30-Depth Order Flow Engine");
        $this->info("Target Symbol: {$symbol}");
        $this->info("=================================================");

        $scriptPath = base_path('scripts/orderflow-depth-daemon.js');
        if (!file_exists($scriptPath)) {
            $this->error("Daemon script not found at: {$scriptPath}");
            return 1;
        }

        $env = array_merge($_ENV, [
            'ORDER_FLOW_SYMBOL' => $symbol,
        ]);

        $process = new Process(['node', $scriptPath], base_path(), $env, null, null);
        $process->setTimeout(null); // Keep running indefinitely

        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        return 0;
    }
}
