<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Strategy Registry Configuration
    |--------------------------------------------------------------------------
    |
    | Maps strategy keys to Strategy definition classes.
    | Adding a new strategy requires only registering its class here without
    | modifying any controllers or blade views.
    |
    */
    'registered' => [
        'short_straddle' => \App\Domain\Strategy\Strategies\ShortStraddleStrategy::class,
        'long_straddle' => \App\Domain\Strategy\Strategies\LongStraddleStrategy::class,
        'short_strangle' => \App\Domain\Strategy\Strategies\ShortStrangleStrategy::class,
        'long_strangle' => \App\Domain\Strategy\Strategies\LongStrangleStrategy::class,
        'iron_condor' => \App\Domain\Strategy\Strategies\IronCondorStrategy::class,
        'iron_fly' => \App\Domain\Strategy\Strategies\IronFlyStrategy::class,
        'bull_call_spread' => \App\Domain\Strategy\Strategies\BullCallSpreadStrategy::class,
        'bear_call_spread' => \App\Domain\Strategy\Strategies\BearCallSpreadStrategy::class,
        'bull_put_spread' => \App\Domain\Strategy\Strategies\BullPutSpreadStrategy::class,
        'bear_put_spread' => \App\Domain\Strategy\Strategies\BearPutSpreadStrategy::class,
        'calendar_spread' => \App\Domain\Strategy\Strategies\CalendarSpreadStrategy::class,
        'custom' => \App\Domain\Strategy\Strategies\CustomStrategy::class,
    ],
];
