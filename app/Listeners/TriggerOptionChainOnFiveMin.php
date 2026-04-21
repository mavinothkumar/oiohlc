<?php

namespace App\Listeners;

use App\Events\OhlcOneMinCollected;
use App\Jobs\FetchOptionChainJob;
use Illuminate\Contracts\Queue\ShouldQueue;

class TriggerOptionChainOnFiveMin
{
    public function handle(OhlcOneMinCollected $event): void
    {
        $minute = (int) now()->format('i');

        if ($minute % 5 !== 0) {
            return;
        }

        dispatch(new FetchOptionChainJob($event->symbol, $event->timestamp));
    }
}
