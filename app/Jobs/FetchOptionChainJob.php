<?php

namespace App\Jobs;

use App\Console\Commands\FetchOptionChainData;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FetchOptionChainJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 120;

    public function handle(): void
    {
        // Reuse exact same command logic
        app(FetchOptionChainData::class)->handle();
    }
}
