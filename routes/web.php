<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('test', function () {
    $expiryValue = 1761848999000;
   $expiryDate = Carbon\Carbon::createFromTimestampMs($expiryValue)->format('Y-m-d');
   dd($expiryDate);
});

Route::get('/market-flow', [App\Http\Controllers\MarketFlowController::class, 'index'])->name('market-flow.index');
