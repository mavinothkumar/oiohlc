<?php

use Illuminate\Support\Facades\Schedule;


Schedule::command('nse:populate-working-days') // php artisan nse:populate-working-days
        ->dailyAt('08:50')
        ->timezone('Asia/Kolkata')
        ->appendOutputTo(storage_path('logs/working-days.log'));


Schedule::command('upstox:fetch-instruments') // php artisan upstox:fetch-instruments
        ->dailyAt('08:52')
        ->timezone('Asia/Kolkata')
        ->appendOutputTo(storage_path('logs/instruments.log'));

Schedule::command('CollectOhlcForIndices.php') // php artisan nse:populate-working-days
        ->dailyAt('08:56')
        ->timezone('Asia/Kolkata')
        ->appendOutputTo(storage_path('logs/working-days.log'));

Schedule::command('expiries:update-benchmarks') // php artisan expiries:update-benchmarks
        ->weekdays()
        ->dailyAt('09:06')
        ->timezone('Asia/Kolkata')
        ->appendOutputTo(storage_path('logs/expiry.log'));

Schedule::command('quotes:collect-daily-ohlc') // php artisan quotes:collect-daily-ohlc
        ->dailyAt('09:08')
        ->timezone('Asia/Kolkata')
        ->appendOutputTo(storage_path('logs/instruments.log'));


Schedule::command('optionchain:fetch')->weekdays()
        ->everyMinute()
        ->between('9:15', '15:32')
        ->appendOutputTo(storage_path('logs/optionchain.log'));

//Schedule::command('market:collect-quotes')->weekdays()
//        ->everyMinute()
//        ->between( '9:15', '15:32' )
//        ->appendOutputTo( storage_path( 'logs/collect-quotes.log' ) );
//
//Schedule::command('market:aggregate-3min-quotes')->weekdays()
//        ->everyThreeMinutes()
//        ->between( '9:15', '15:32' )
//        ->appendOutputTo( storage_path( 'logs/collect-quotes.log' ) );
