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
Route::get('/option-chain', [App\Http\Controllers\OptionChainController::class, 'index'])->name('option.chain');
Route::get('/buildups', [\App\Http\Controllers\BuildUpSummaryController::class, 'index'])->name('buildups.index');
