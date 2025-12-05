<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

class ProcessTrend5mJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // optional: timeout in seconds
    public $timeout = 300;

    public function __construct()
    {
        // put any parameters here if needed later
    }

    public function handle(): void
    {
        // Call your existing command
        Artisan::call('trend:process-5m');
    }
}
