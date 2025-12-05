<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

class CollectOhlcJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Option 1: call existing artisan command
//        $process = Process::fromShellCommandline('php artisan market:collect-ohlc');
//        $process->setTimeout(0);
//        $process->run();
        Artisan::call('market:collect-ohlc');
    }
}
