<?php

namespace App\Listeners;

use App\Events\OhlcOneMinCollected;
use App\Jobs\FetchOptionChainJob;
use Illuminate\Contracts\Queue\ShouldQueue;

class TriggerOptionChainOnFiveMin
{
    public function handle(OhlcOneMinCollected $event): void
    {
        // Dispatch option chain collection for fine-grained 1-minute and 5-minute tracking
        dispatch(new FetchOptionChainJob($event->symbol, $event->timestamp));
    }
}
