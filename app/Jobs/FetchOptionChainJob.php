<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

// FetchOptionChainJob.php
class FetchOptionChainJob implements ShouldQueue
{
    public function __construct(
        public readonly string $symbol,
        public readonly string $candleTimestamp, // "2026-04-21 09:20:00"
    ) {}

    public function handle(): void
    {
        // Use $this->candleTimestamp to build $candleTs, $windowStart, $windowEnd
        $candleTs    = Carbon::parse($this->candleTimestamp)->second(0);
        $windowStart = $candleTs->copy()->subMinutes(4);
        $windowEnd   = $candleTs->copy();

        // fetch option chain → aggregate → store ohlc_live_snapshots
    }
}
