<?php

use Illuminate\Support\Facades\Schedule;

/**
 * php artisan queue:work --queue=ohlc
 * php artisan queue:work --queue=trend5m
 * php artisan queue:work --queue=optionchain
 */

Schedule::command('upstox:fetch-market-holidays') // php artisan upstox:fetch-market-holidays
        ->yearlyOn(1, 1, '08:57')
        ->timezone('Asia/Kolkata')
        ->appendOutputTo(storage_path('logs/working-days.log'));

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

Schedule::command('trend:populate-daily') // php artisan trend:populate-daily
        ->dailyAt('09:09')
        ->timezone('Asia/Kolkata')
        ->appendOutputTo(storage_path('logs/populate-daily.log'));

Schedule::command('trend:update-index-open') // php artisan trend:update-index-open
        ->dailyAt('09:10')
        ->timezone('Asia/Kolkata')
        ->appendOutputTo(storage_path('logs/update-index-open.log'));


//Schedule::command('market:collect-ohlc')->weekdays()  // php artisan market:collect-ohlc
//        ->everyMinute()
//        ->between('9:15', '15:30')
//        ->withoutOverlapping()
//        ->appendOutputTo(storage_path('logs/collect-ohlc.log'));

Schedule::job(new \App\Jobs\CollectOhlcJob(), 'ohlc')
        ->weekdays()
        ->everyMinute()
        ->between('9:15', '15:30')
        ->appendOutputTo(storage_path('logs/collect-ohlc.log'));

//Schedule::command('trend:process-5m')->weekdays()  // php artisan trend:process-5m
//        ->everyFiveMinutes()
//        ->between('9:15', '15:30')
//        ->withoutOverlapping()
//        ->appendOutputTo(storage_path('logs/process-5m.log'));

Schedule::job(new \App\Jobs\ProcessTrend5mJob(), 'trend5m')->weekdays()  // php artisan trend:process-5m
        ->everyFiveMinutes()
        ->between('9:15', '15:30')
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/process-5m.log'));

//Schedule::command('optionchain:fetch')->weekdays()  // php artisan optionchain:fetch
//        ->everyThreeMinutes()
//        ->between('9:15', '15:33')
//        ->appendOutputTo(storage_path('logs/optionchain.log'));


Schedule::job(new \App\Jobs\FetchOptionChainJob(), 'optionchain')  // php artisan optionchain:fetch
        ->everyMinute()
        ->between('9:15', '15:33')
        ->appendOutputTo(storage_path('logs/optionchain.log'));


Schedule::command('ohlc:cleanup-daily')
        ->dailyAt('15:45')
        ->appendOutputTo(storage_path('logs/cleanup-daily.log'));

//php artisan queue:work --queue=default --sleep=1 --tries=1

//Schedule::command('quotes:collect-daily-ohlc') // php artisan quotes:collect-daily-ohlc
//        ->dailyAt('09:08')
//        ->timezone('Asia/Kolkata')
//        ->appendOutputTo(storage_path('logs/instruments.log'));


//Schedule::command('market:collect-ohlc-5m')->weekdays()  // php artisan market:collect-ohlc-5m
//        ->everyMinute()
//        ->between('9:15', '15:30')
//        ->appendOutputTo(storage_path('logs/collect-ohlc-5m.log'));


//Schedule::command('full-market:collect-quotes')->weekdays()
//        ->everyMinute()
//        ->between( '9:15', '15:32' )
//        ->appendOutputTo( storage_path( 'logs/full-market-collect-quotes.log' ) );
//
//Schedule::command('market:ohlc:cleanup-daily')->weekdays()
//        ->dailyAt('15:45')
//        ->appendOutputTo( storage_path( 'logs/cleanup-daily.log' ) );

//Schedule::command('app:testing')
//        ->timezone('Asia/Kolkata')
//        ->appendOutputTo(storage_path('logs/testing.log'));
