<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TradingViewController;

Route::get( '/', function () {
    return view( 'welcome' );
} )->name( 'home' );

Route::get( '/111', function () {
    return now()->second( 0 );
} );

Route::get( 'test', [ '\App\Http\Controllers\OhlcChartController', 'ohlc' ] );

Route::get( '/snipper-point', [ App\Http\Controllers\SnipperPointController::class, 'index' ] )->name( 'snipper-point' );
Route::get( '/market-flow', [ App\Http\Controllers\MarketFlowController::class, 'index' ] )->name( 'market-flow.index' );
Route::get( '/option-chain', [ App\Http\Controllers\OptionChainController::class, 'index' ] )->name( 'option.chain' );
Route::get( '/buildups', [ App\Http\Controllers\BuildUpSummaryController::class, 'index' ] )->name( 'buildups.index' );
Route::get( '/buildup/strike', [ App\Http\Controllers\BuildUpSummaryController::class, 'strike' ] )->name( 'buildups.strike' );
Route::get( '/option-chain-diff', [ App\Http\Controllers\OptionChainDiffController::class, 'index' ] )->name( 'option-chain-diff' );
Route::get( '/option-chain/build-up', [ App\Http\Controllers\OptionChainController::class, 'showBuildUp' ] )->name( 'option-chain.build-up' );
Route::get( '/option-chain/build-up-all', [ App\Http\Controllers\OptionChainController::class, 'showBuildUpAll' ] )->name( 'option-chain.build-up-all' );
Route::get( '/option-straddle', [ App\Http\Controllers\OptionStraddleController::class, 'show' ] )->name( 'option-straddle' );
Route::get( '/ohlc', [ App\Http\Controllers\OHLCController::class, 'index' ] )->name( 'ohlc.index' );

Route::get( '/hlc', [ App\Http\Controllers\HlcController::class, 'index' ] )->name( 'hlc.index' );

Route::get( '/hlc-close', [ App\Http\Controllers\HlcCloseController::class, 'index' ] )->name( 'hlc.close' );

Route::get( '/trend', [ App\Http\Controllers\TrendController::class, 'index' ] )->name( 'trend.index' );
Route::get( '/trend/meta', [ \App\Http\Controllers\TrendMetaController::class, 'index' ] )->name( 'trend.meta' );

Route::get( '/backtests/six-level', [ \App\Http\Controllers\SixLevelController::class, 'index' ] )->name( 'backtests.six-level.index' );

Route::get( '/backtests/straddles', [ \App\Http\Controllers\StraddleBacktestController::class, 'index' ] )
     ->name( 'backtests.straddles.index' );
Route::get( '/backtests/futures/ohlc', [ \App\Http\Controllers\FutureOhlcController::class, 'index' ] )
     ->name( 'backtests.futures.ohlc.index' );

Route::get( '/analysis', [ \App\Http\Controllers\IndexOptionAnalysisController::class, 'index' ] )
     ->name( 'analysis.index' );


Route::get( '/options-chart', [ App\Http\Controllers\OhlcChartController::class, 'index' ] )->name( 'options.chart' );

// AJAX: get expiries for a date + underlying
Route::get( '/api/expiries', [ App\Http\Controllers\OhlcChartController::class, 'expiries' ] )->name( 'api.expiries' );

// AJAX: get OHLC for CE & PE
Route::get( '/api/ohlc', [ App\Http\Controllers\OhlcChartController::class, 'ohlc' ] )->name( 'api.ohlc' );


Route::get( '/strangle-profit', [ App\Http\Controllers\StrangleController::class, 'index' ] )->name( 'strangle.profit' );


Route::get( '/entry', [ App\Http\Controllers\EntryController::class, 'index' ] )->name( 'entries.index' );   // show form + P&L table
Route::post( '/entries', [ App\Http\Controllers\EntryController::class, 'store' ] )->name( 'entries.store' ); // save entry
Route::put( '/entries/{entry}', [ App\Http\Controllers\EntryController::class, 'update' ] )->name( 'entries.update' ); // new
Route::get( '/pnl-data', [ App\Http\Controllers\EntryController::class, 'pnlData' ] )->name( 'entries.pnl' ); // ajax P&L refresh
Route::delete( '/entries/{entry}', [ App\Http\Controllers\EntryController::class, 'destroy' ] )->name( 'entries.destroy' );
Route::get( '/pnl-series', [ App\Http\Controllers\EntryController::class, 'pnlSeries' ] )->name( 'entries.pnlSeries' );


Route::get( '/index-futures-chart', [ App\Http\Controllers\IndexFuturesChartController::class, 'index' ] )->name( 'index.futures.chart' );
Route::get( '/api/index-futures-daily', [ App\Http\Controllers\IndexFuturesChartController::class, 'dailyTrend' ] )->name( 'api.index.futures.daily' );


Route::get( '/oi-buildup-live', [ App\Http\Controllers\OiBuildupLiveController::class, 'index' ] )->name( 'oi-buildup.live' );
Route::get( '/oi-buildup/expiries', [ App\Http\Controllers\OiBuildupController::class, 'expiries' ] )
     ->name( 'oi-buildup.expiries' );

Route::get( '/volume-buildup-live', [ App\Http\Controllers\VolumeBuildupLiveController::class, 'index' ] )->name( 'volume-buildup.live' );
Route::get( '/live-ohlc', [ App\Http\Controllers\OhlcLiveSnapshotController::class, 'index' ] )->name( 'live-ohlc.index' );

// AJAX: get expiries for a date + underlying
Route::get( '/api/multi-chart-expiries', [ App\Http\Controllers\OhlcChartController::class, 'multiExpiries' ] )->name( 'api.multi-chart-expiries' );

// AJAX: get OHLC for CE & PE
Route::get( '/api/multi-chart-ohlc', [ App\Http\Controllers\OhlcChartController::class, 'multiOhlc' ] )->name( 'api.multi-chart-ohlc' );


Route::get( '/daily-trend-view', [ App\Http\Controllers\DailyTrendController::class, 'index' ] )
     ->name( 'daily_trend.view' );

Route::get( '/oi-diff-live', [ \App\Http\Controllers\OiDiffLiveController::class, 'index' ] )->name( 'oi-diff-live.index' );
Route::get( '/oi-diff-live/data', [ \App\Http\Controllers\OiDiffLiveController::class, 'data' ] )->name( 'oi-diff-live.data' );

Route::get( '/build-up-snapshot', [ App\Http\Controllers\BuildUpSnapshotController::class, 'index' ] )->name( 'buildup.snapshot' );
Route::get( '/buildups/net-pressure-history', [ App\Http\Controllers\BuildUpSummaryController::class, 'netPressureHistory' ] );
Route::get( '/build-up-analysis', [ App\Http\Controllers\BuildUpAnalysisController::class, 'index' ] )->name( 'build-up.index' );

Route::get( '/option-chain-analysis', [ App\Http\Controllers\OptionChainAnalysisController::class, 'index' ] )->name( 'option-chain.analysis' );
Route::get( '/api/option-chain-analysis', [ App\Http\Controllers\OptionChainAnalysisController::class, 'getData' ] )->name( 'option-chain.analysis.data' );

Route::get('/options-charts', [ App\Http\Controllers\OptionsChartController::class, 'index'])->name('options.chart.index');
Route::get('/options-charts/expiry-range', [ App\Http\Controllers\OptionsChartController::class, 'getExpiryRange'])->name('options.chart.expiry.range');
Route::get('/options-charts/chart-data', [ App\Http\Controllers\OptionsChartController::class, 'getChartData'])->name('options.chart.chart.data');



Route::prefix('trading')->name('trading.')->group(function () {

    // Main chart page
    Route::get('/chart', [TradingViewController::class, 'index'])
         ->name('chart');

    // AJAX: fetch candle data for submitted strikes
    Route::post('/chart/data', [TradingViewController::class, 'fetchChartData'])
         ->name('chart.data');

    // AJAX: expiry dates for a symbol  (reads nse_expiries, is_current = 1)
    Route::get('/expiries', [TradingViewController::class, 'getExpiries'])
         ->name('expiries');
});


Route::prefix( 'test' )->name( 'test.' )->group( function () {
    Route::get( '/options-multi-chart', [ App\Http\Controllers\OhlcChartController::class, 'multiIndex' ] )->name( 'options.multi.chart' );
    Route::get( '/index-futures-chart', [ App\Http\Controllers\IndexFuturesChartController::class, 'index' ] )->name( 'index.futures.chart' );


    Route::get( '/oi-diff', [ App\Http\Controllers\OiDiffController::class, 'index' ] )->name( 'oi.diff' );
    Route::get( '/oi-diff/expiries', [ App\Http\Controllers\OiDiffController::class, 'fetchExpiries' ] )->name( 'oi.diff.expiries' );

    Route::get( '/oi-buildup', [ App\Http\Controllers\OiBuildupController::class, 'index' ] )->name( 'oi-buildup.index' );

    Route::get( '/short-build-atm', [ App\Http\Controllers\ShortBuildController::class, 'index' ] )
         ->name( 'short-build-atm.index' );


    Route::get( '/buildup-report', [ App\Http\Controllers\BuildUpReportController::class, 'index' ] )->name( 'buildup.report' );


    Route::get( '/oi-step', [ App\Http\Controllers\OiStepController::class, 'index' ] )->name( 'oi.step' );
    Route::get( '/oi-step/slot', [ App\Http\Controllers\OiStepController::class, 'fetchSlot' ] )->name( 'oi.step.slot' );
    Route::get( '/oi-step/expiries', [ App\Http\Controllers\OiStepController::class, 'fetchExpiries' ] )->name( 'oi.step.expiries' );

    Route::get( '/options-chart-step', [ App\Http\Controllers\OhlcChartController::class, 'chartStepIndex' ] )->name( 'options.chart.step' );
    Route::get( '/api/chart-step-slot', [ App\Http\Controllers\OhlcChartController::class, 'chartStepSlot' ] )->name( 'api.chart.step.slot' );


    Route::get( '/trading-simulator', [ App\Http\Controllers\TradingSimulatorController::class, 'index' ] )->name( 'trading-simulator' );
    Route::get( '/trading-simulator/expiry', [ App\Http\Controllers\TradingSimulatorController::class, 'getExpiry' ] )->name( 'trading-simulator.expiry' );
    Route::get( '/trading-simulator/strikes', [ App\Http\Controllers\TradingSimulatorController::class, 'getStrikes' ] )->name( 'trading-simulator.strikes' );
    Route::get( '/trading-simulator/price', [ App\Http\Controllers\TradingSimulatorController::class, 'getPrice' ] )->name( 'trading-simulator.price' );


    // Simulator actions (POST)
    Route::post( '/trading-simulator/position/enter', [ App\Http\Controllers\TradingSimulatorController::class, 'enterPosition' ] )->name( 'trading-simulator.enter' );
    Route::post( '/trading-simulator/position/exit', [ App\Http\Controllers\TradingSimulatorController::class, 'exitPosition' ] )->name( 'trading-simulator.exit' );
    Route::delete('/trading-simulator/position/{id}', [App\Http\Controllers\TradingSimulatorController::class, 'deletePosition'])->name('trading-simulator.delete');
// Report
    Route::get( '/trading-simulator/report', [ App\Http\Controllers\TradingSimulatorController::class, 'report' ] )->name( 'trading-simulator.report' );
    Route::get( '/trading-simulator/report/{position}', [ App\Http\Controllers\TradingSimulatorController::class, 'reportDetail' ] )->name( 'trading-simulator.report.detail' );
    Route::post( '/trading-simulator/report/{position}/note', [ App\Http\Controllers\TradingSimulatorController::class, 'storeNote' ] )->name( 'trading-simulator.report.note' );



    Route::get( '/oi-volume-chart', [ App\Http\Controllers\OiVolumeChartController::class, 'index' ] )->name( 'oi.volume.chart' );
    Route::get( '/api/oi-volume-expiries', [ App\Http\Controllers\OiVolumeChartController::class, 'getExpiries' ] )->name( 'api.oi.volume.expiries' );
    Route::get( '/api/oi-volume-slot', [ App\Http\Controllers\OiVolumeChartController::class, 'getSlotData' ] )->name( 'api.oi.volume.slot' );


} );
