<?php

use Illuminate\Support\Facades\Schedule;



Schedule::command( 'upstox:fetch-instruments' )
        ->dailyAt( '09:00' )
        ->timezone( 'Asia/Kolkata' )
        ->appendOutputTo( storage_path( 'logs/instruments.log' ) );

Schedule::command( 'quotes:collect-daily-ohlc' )
        ->dailyAt( '09:02' )
        ->timezone( 'Asia/Kolkata' )
        ->appendOutputTo( storage_path( 'logs/instruments.log' ) );

Schedule::command( 'expiries:update-benchmarks' )
    ->weekdays()
    ->dailyAt( '09:06' )
    ->timezone( 'Asia/Kolkata' )
    ->appendOutputTo( storage_path( 'logs/expiry.log' ) );

Schedule::command('market:collect-quotes')->weekdays()
        ->everyMinute()
        ->between( '9:14', '15:32' )
        ->appendOutputTo( storage_path( 'logs/collect-quotes.log' ) );
