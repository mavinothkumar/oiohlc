<?php

namespace App\Listeners;

use App\Events\OhlcOneMinCollected;
use App\Jobs\FetchOptionChainJob;
use Illuminate\Contracts\Queue\ShouldQueue;

class TriggerOptionChainOnFiveMin
{
    public function handle(OhlcOneMinCollected $event): void
    {
        info('Event TriggerOptionChainOnFiveMin start' . now()->toTimeString());
        $minute = (int) now()->format('i');

        if ($minute % 5 !== 0) {
            info('Event TriggerOptionChainOnFiveMin returned ' . $minute . ' - ' . now()->toTimeString());
            return;
        }
        info('Event TriggerOptionChainOnFiveMin dispatch start' . now()->toTimeString());
        dispatch(new FetchOptionChainJob($event->symbol, $event->timestamp));
        info('Event TriggerOptionChainOnFiveMin dispatch end' . now()->toTimeString());
    }
}
