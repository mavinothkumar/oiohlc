<?php

use Illuminate\Support\Facades\Schedule;



Schedule::command('nse:populate-working-days') // php artisan nse:populate-working-days
        ->yearlyOn(1, 1, '09:00')
        ->timezone('Asia/Kolkata')
        ->appendOutputTo(storage_path('logs/working-days.log'));


Schedule::command('nse:update-working-day-flags') // php artisan nse:update-working-day-flags
        ->dailyAt('08:50')
        ->timezone('Asia/Kolkata')
        ->appendOutputTo(storage_path('logs/update-working-day-flags.log'));


Schedule::command('upstox:fetch-instruments') // php artisan upstox:fetch-instruments
        ->dailyAt('08:52')
        ->timezone('Asia/Kolkata')
        ->appendOutputTo(storage_path('logs/instruments.log'));

Schedule::command('expiries:update-benchmarks') // php artisan expiries:update-benchmarks
        ->weekdays()
        ->dailyAt('09:00')
        ->timezone('Asia/Kolkata')
        ->appendOutputTo(storage_path('logs/expiry.log'));

Schedule::command('indices:collect-daily-ohlc') // php artisan indices:collect-daily-ohlc
        ->dailyAt('09:01')
        ->timezone('Asia/Kolkata')
        ->appendOutputTo(storage_path('logs/working-days.log'));



//Schedule::command('quotes:collect-daily-ohlc') // php artisan quotes:collect-daily-ohlc
//        ->dailyAt('09:08')
//        ->timezone('Asia/Kolkata')
//        ->appendOutputTo(storage_path('logs/instruments.log'));


Schedule::command('optionchain:fetch')->weekdays()  // php artisan optionchain:fetch
        ->everyMinute()
        ->between('9:15', '15:32')
        ->appendOutputTo(storage_path('logs/optionchain.log'));

Schedule::command('market:collect-ohlc')->weekdays()  // php artisan market:collect-ohlc
        ->everyMinute()
        ->between('9:15', '15:32')
        ->appendOutputTo(storage_path('logs/collect-ohlc.log'));

//Schedule::command('full-market:collect-quotes')->weekdays()
//        ->everyMinute()
//        ->between( '9:15', '15:32' )
//        ->appendOutputTo( storage_path( 'logs/full-market-collect-quotes.log' ) );
//
//Schedule::command('market:aggregate-3min-quotes')->weekdays()
//        ->everyThreeMinutes()
//        ->between( '9:15', '15:32' )
//        ->appendOutputTo( storage_path( 'logs/collect-quotes.log' ) );

//Schedule::command('app:testing')
//        ->timezone('Asia/Kolkata')
//        ->appendOutputTo(storage_path('logs/testing.log'));
