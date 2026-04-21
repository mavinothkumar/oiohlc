<?php

namespace App\Jobs;

use DB;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

// FetchOptionChainJob.php
class FetchOptionChainJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $symbol,
        public readonly string $candleTimestamp,
    ) {}

    public function handle(): void
    {
        Log::info('FetchOptionChainJob started', [
            'symbol'    => $this->symbol,
            'timestamp' => $this->candleTimestamp,
        ]);

        // Step 1 — Run the existing command (fetches option chain + stores + aggregates)
        Artisan::call('optionchain:fetch');

        Log::info('FetchOptionChainJob completed', [
            'symbol'    => $this->symbol,
            'timestamp' => $this->candleTimestamp,
            'output'    => Artisan::output(),
        ]);
    }
}
