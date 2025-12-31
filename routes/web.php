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

Route::get('/hlc-close', [App\Http\Controllers\HlcCloseController::class, 'index'])->name('hlc.close');

Route::get('/trend',  [App\Http\Controllers\TrendController::class, 'index'])->name('trend.index');
Route::get('/trend/meta', [\App\Http\Controllers\TrendMetaController::class, 'index'])->name('trend.meta');

Route::get('/backtests/six-level', [\App\Http\Controllers\SixLevelController::class, 'index'])->name('backtests.six-level.index');

Route::get('/backtests/straddles', [\App\Http\Controllers\StraddleBacktestController::class, 'index'])
     ->name('backtests.straddles.index');
Route::get('/backtests/futures/ohlc', [\App\Http\Controllers\FutureOhlcController::class, 'index'])
     ->name('backtests.futures.ohlc.index');

Route::get('/analysis', [\App\Http\Controllers\IndexOptionAnalysisController::class, 'index'])
     ->name('analysis.index');


Route::get('/options-chart', [App\Http\Controllers\OhlcChartController::class, 'index'])->name('options.chart');

// AJAX: get expiries for a date + underlying
Route::get('/api/expiries', [App\Http\Controllers\OhlcChartController::class, 'expiries'])->name('api.expiries');

// AJAX: get OHLC for CE & PE
Route::get('/api/ohlc', [App\Http\Controllers\OhlcChartController::class, 'ohlc'])->name('api.ohlc');


Route::get('/strangle-profit', [App\Http\Controllers\StrangleController::class, 'index'])->name('strangle.profit');




Route::get('/entry', [App\Http\Controllers\EntryController::class, 'index'])->name('entries.index');   // show form + P&L table
Route::post('/entries', [App\Http\Controllers\EntryController::class, 'store'])->name('entries.store'); // save entry
Route::get('/pnl-data', [App\Http\Controllers\EntryController::class, 'pnlData'])->name('entries.pnl'); // ajax P&L refresh
