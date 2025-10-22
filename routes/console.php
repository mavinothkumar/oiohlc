<?php

use Illuminate\Support\Facades\Schedule;



Schedule::command( 'upstox:fetch-instruments' )
        ->dailyAt( '08:40' )
        ->timezone( 'Asia/Kolkata' )
        ->appendOutputTo( storage_path( 'logs/instruments.log' ) );

Schedule::command( 'expiries:update-benchmarks' )
        ->weekdays()
        ->dailyAt( '09:02' )
        ->timezone( 'Asia/Kolkata' )
        ->appendOutputTo( storage_path( 'logs/expiry.log' ) );

Schedule::command( 'quotes:collect-daily-ohlc' )
        ->dailyAt( '09:08' )
        ->timezone( 'Asia/Kolkata' )
        ->appendOutputTo( storage_path( 'logs/instruments.log' ) );



Schedule::command('market:collect-quotes')->weekdays()
        ->everyMinute()
        ->between( '9:15', '15:32' )
        ->appendOutputTo( storage_path( 'logs/collect-quotes.log' ) );

Schedule::command('optionchain:fetch')->weekdays()
        ->everyMinute()
        ->between( '9:15', '15:32' )
        ->appendOutputTo( storage_path( 'logs/optionchain.log' ) );


Schedule::command('market:aggregate-3min-quotes')->weekdays()
        ->everyThreeMinutes()
        ->between( '9:15', '15:32' )
        ->appendOutputTo( storage_path( 'logs/collect-quotes.log' ) );
