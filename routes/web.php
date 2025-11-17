<?php

use Illuminate\Support\Facades\Route;

Route::get('/111', function () {
    return now()->second(0);
})->name('home');

Route::get('test', function () {
    $expiryValue = 1761848999000;
   $expiryDate = Carbon\Carbon::createFromTimestampMs($expiryValue)->format('Y-m-d');
   dd($expiryDate);
});

Route::get('/snipper-point', [App\Http\Controllers\SnipperPointController::class, 'index'])->name('snipper-point');
Route::get('/market-flow', [App\Http\Controllers\MarketFlowController::class, 'index'])->name('market-flow.index');
Route::get('/option-chain', [App\Http\Controllers\OptionChainController::class, 'index'])->name('option.chain');
Route::get('/buildups', [App\Http\Controllers\BuildUpSummaryController::class, 'index'])->name('buildups.index');
Route::get('/buildup/strike', [App\Http\Controllers\BuildUpSummaryController::class, 'strike'])->name('buildups.strike');
Route::get('/option-chain-diff', [App\Http\Controllers\OptionChainDiffController::class, 'index'])->name('option-chain-diff');
Route::get('/option-chain/build-up', [App\Http\Controllers\OptionChainController::class, 'showBuildUp'])->name('option-chain.build-up');
Route::get('/option-chain/build-up-all', [App\Http\Controllers\OptionChainController::class, 'showBuildUpAll'])->name('option-chain.build-up-all');
Route::get('/option-straddle', [App\Http\Controllers\OptionStraddleController::class, 'show'])->name('option-straddle');
Route::get('/ohlc', [App\Http\Controllers\OHLCController::class, 'index'])->name('ohlc.index');

Route::get('/hlc', [App\Http\Controllers\HlcController::class, 'index'])->name('hlc.index');
