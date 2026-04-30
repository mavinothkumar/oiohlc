<?php

// app/Http/Controllers/BacktestController.php

namespace App\Http\Controllers;

use App\Models\BacktestTrade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BacktestController extends Controller {
    /**
     * Index: one row per trading day (day_group_id).
     */
    public function index( Request $request ) {
        $availableStrategies = \App\Services\Backtest\StrategyRegistry::available();
        $monthlyStats        = collect();

        // ── No strategy selected → empty state ────────────────────────────
        if ( ! $request->filled( 'strategy' ) ) {
            return view( 'backtest.index', [
                'days'                => ( new \Illuminate\Pagination\LengthAwarePaginator( [], 0, 100 ) ),
                'statsQuery'          => null,
                'monthlyStats'        => $monthlyStats,
                'availableStrategies' => $availableStrategies,
                'expiryDates'         => [],
            ] );
        }

        // ── Expiry dates ───────────────────────────────────────────────────
        $expiryDates = DB::table( 'expired_expiries' )
                         ->where( 'underlying_symbol', 'NIFTY' )
                         ->where( 'instrument_type', 'OPT' )
                         ->pluck( 'expiry_date' )
                         ->mapWithKeys( fn( $d ) => [
                             \Carbon\Carbon::parse( $d )->toDateString() => true,
                         ] )
                         ->toArray();

        $expiryDateList = array_keys( $expiryDates );

        // ── Base query builder (reused for days, stats, monthly) ───────────
        $baseQuery = fn() => DB::table( 'backtest_trades' )
                               ->where( 'strategy', $request->strategy )
                               ->when( $request->filled( 'symbol' ),
                                   fn( $q ) => $q->where( 'underlying_symbol', $request->symbol ) )
                               ->when( $request->filled( 'outcome' ),
                                   fn( $q ) => $q->where( 'day_outcome', $request->outcome ) )
                               ->when( $request->filled( 'pnl_dir' ) && $request->filled( 'pnl_value' ),
                                   fn( $q ) => $request->pnl_dir === 'gte'
                                       ? $q->where( 'day_total_pnl', '>=', $request->pnl_value )
                                       : $q->where( 'day_total_pnl', '<=', $request->pnl_value ) )
                               ->when( $request->filled( 'from' ),
                                   fn( $q ) => $q->whereDate( 'trade_date', '>=', $request->from ) )
                               ->when( $request->filled( 'to' ),
                                   fn( $q ) => $q->whereDate( 'trade_date', '<=', $request->to ) )
                               ->when( $request->skip_expiry == '1' && ! empty( $expiryDateList ),
                                   fn( $q ) => $q->whereNotIn( 'trade_date', $expiryDateList ) )
                               ->when( request()->filled( 'entry_time' ), function ( $q ) {
                                   $q->whereRaw( "DATE_FORMAT(entry_time, '%H:%i') = ?", [ request( 'entry_time' ) ] );
                               } )
                               ->when( request()->filled( 'exit_time' ), function ( $q ) {
                                   $q->where( function ( $q ) {
                                       $q->whereRaw( "DATE_FORMAT(exit_time, '%H:%i') <= ?", [ request( 'exit_time' ) ] )
                                         ->orWhereNull( 'exit_time' );
                                   } );
                               } )
                               ->when( $request->filled( 'skip_days' ),
                                   fn( $q ) => $q->whereNotIn(
                                       DB::raw( 'DAYNAME(trade_date)' ),
                                       (array) $request->skip_days
                                   ) );

        // ── Peak filter (only on days query) ──────────────────────────────
        $applyPeakFilter = function ( $q ) use ( $request ) {
            if ( ! $request->filled( 'peak_filter' ) ) {
                return $q;
            }

            // ── Type filter ────────────────────────────────────────────────
            match ( $request->peak_filter ) {
                'has_peak_profit' => $q->where( 'day_max_profit', '>', 0 ),
                'no_peak_profit' => $q->where( fn( $q ) => $q->whereNull( 'day_max_profit' )
                                                             ->orWhere( 'day_max_profit', '<=', 0 ) ),
                'peak_profit_reversed' => $q->where( 'day_max_profit', '>', 0 )
                                            ->where( 'day_outcome', 'loss' ),
                'has_peak_loss' => $q->where( 'day_max_loss', '<', 0 ),
                'no_peak_loss' => $q->where( fn( $q ) => $q->whereNull( 'day_max_loss' )
                                                           ->orWhere( 'day_max_loss', '>=', 0 ) ),
                'peak_loss_recovered' => $q->where( 'day_max_loss', '<', 0 )
                                           ->where( 'day_outcome', 'profit' ),
                default => null,
            };

            // ── Value filter (peak_dir + peak_value) ──────────────────────
            if ( $request->filled( 'peak_dir' ) && $request->filled( 'peak_value' ) ) {
                $val = (float) $request->peak_value;
                $op  = $request->peak_dir === 'gte' ? '>=' : '<=';

                // Apply to the relevant peak column based on filter type
                $isProfitFilter = str_contains( $request->peak_filter, 'profit' );

                if ( $isProfitFilter ) {
                    $q->where( 'day_max_profit', $op, $val );
                } else {
                    $q->where( 'day_max_loss', $op, $val );
                }
            }

            return $q;
        };

        // ── Days (paginated) ───────────────────────────────────────────────
        $daysQuery = $baseQuery()
            ->selectRaw( '
        day_group_id, backtest_run_id, underlying_symbol, exchange,
        strategy, trade_date, expiry, index_price_at_entry,
        strike_offset, target, stoploss, lot_size,
        day_total_pnl, day_outcome,
        MIN(strike)                                                 AS strike,
        MIN(instrument_type)                                        AS instrument_type,
        MIN(signal_time)                                            AS signal_time,
        MAX(day_max_profit)                                         AS day_max_profit,
        MAX(day_max_profit_time)                                    AS day_max_profit_time,
        MIN(day_max_loss)                                           AS day_max_loss,
        MIN(day_max_loss_time)                                      AS day_max_loss_time,
        MIN(entry_time)                                             AS entry_time,
        MAX(exit_time)                                              AS exit_time,
        MAX(trade_time_duration)                                    AS trade_time_duration,
        COUNT(*)                                                    AS total_legs,
        MAX(CASE WHEN instrument_type = "CE" THEN strike END)       AS ce_strike,
        MAX(CASE WHEN instrument_type = "PE" THEN strike END)       AS pe_strike
    ' )
            ->groupBy(
                'day_group_id', 'backtest_run_id', 'underlying_symbol', 'exchange',
                'strategy', 'trade_date', 'expiry', 'index_price_at_entry',
                'strike_offset', 'target', 'stoploss', 'lot_size',
                'day_total_pnl', 'day_outcome'
            );

        $applyPeakFilter( $daysQuery );

        $days = $daysQuery->orderByDesc( 'trade_date' )->paginate( 100 )->withQueryString();

        // ── Summary stats ──────────────────────────────────────────────────
        $statsQuery = $baseQuery()
            ->selectRaw( '
        COUNT(DISTINCT day_group_id)                                          AS total_days,
        ROUND(SUM(pnl), 2)                                                    AS total_pnl,
        COUNT(DISTINCT CASE WHEN day_outcome="profit" THEN day_group_id END)  AS profit_days,
        COUNT(DISTINCT CASE WHEN day_outcome="loss"   THEN day_group_id END)  AS loss_days,
        AVG(CASE WHEN day_outcome="profit" THEN trade_time_duration END)      AS avg_profit_min,
        AVG(CASE WHEN day_outcome="loss"   THEN trade_time_duration END)      AS avg_loss_min,
        MAX(day_total_pnl)                                                    AS best_day,
        MIN(day_total_pnl)                                                    AS worst_day,
        AVG(day_max_profit)                                                   AS avg_max_profit,
        AVG(day_max_loss)                                                     AS avg_max_loss,
        AVG(CASE WHEN day_outcome="profit" THEN day_total_pnl END)            AS avg_profit_pnl,
        AVG(CASE WHEN day_outcome="loss"   THEN day_total_pnl END)            AS avg_loss_pnl
    ' )
            ->first();

        // ── Monthly stats ──────────────────────────────────────────────────
        $monthlyStats = $baseQuery()
            ->selectRaw( "
            YEAR(trade_date)                                                          AS year,
            MONTH(trade_date)                                                         AS month,
            ROUND(SUM(pnl), 0)                                                       AS total_pnl,
            COUNT(DISTINCT CASE WHEN day_outcome='profit' THEN day_group_id END)     AS profit_days,
            COUNT(DISTINCT CASE WHEN day_outcome='loss'   THEN day_group_id END)     AS loss_days,
            COUNT(DISTINCT day_group_id)                                              AS total_days
        " )
            ->groupByRaw( 'YEAR(trade_date), MONTH(trade_date)' )
            ->orderByRaw( 'YEAR(trade_date), MONTH(trade_date)' )
            ->get()
            ->groupBy( 'year' );


        // ── Weekly stats ───────────────────────────────────────────────────
        $weeklyStats = $baseQuery()
            ->selectRaw( "
        YEARWEEK(trade_date, 1)                                                   AS yw,
        MIN(trade_date)                                                            AS week_start,
        COUNT(DISTINCT day_group_id)                                              AS total_days,
        COUNT(DISTINCT CASE WHEN day_outcome='profit' THEN day_group_id END)      AS profit_days,
        COUNT(DISTINCT CASE WHEN day_outcome='loss'   THEN day_group_id END)      AS loss_days,
        ROUND(SUM(pnl), 0)                                                        AS total_pnl
    " )
            ->groupByRaw( 'YEARWEEK(trade_date, 1)' )
            ->orderByRaw( 'YEARWEEK(trade_date, 1)' )
            ->get();


        // ── Day-of-week stats ──────────────────────────────────────────────
        $dowStats = $baseQuery()
            ->selectRaw( "
        DAYNAME(trade_date)                                                        AS dow,
        DAYOFWEEK(trade_date)                                                      AS dow_num,
        COUNT(DISTINCT day_group_id)                                               AS total_days,
        COUNT(DISTINCT CASE WHEN day_outcome='profit' THEN day_group_id END)       AS profit_days,
        COUNT(DISTINCT CASE WHEN day_outcome='loss'   THEN day_group_id END)       AS loss_days,
        ROUND(
            COUNT(DISTINCT CASE WHEN day_outcome='profit' THEN day_group_id END)
            / NULLIF(COUNT(DISTINCT day_group_id), 0) * 100, 1
        )                                                                          AS win_rate,
        ROUND(SUM(day_total_pnl) / COUNT(DISTINCT day_group_id), 0)               AS avg_pnl,
        ROUND(SUM(day_total_pnl), 0)                                               AS total_pnl
    " )
            ->groupByRaw( 'DAYNAME(trade_date), DAYOFWEEK(trade_date)' )
            ->orderByRaw( 'DAYOFWEEK(trade_date)' )
            ->get();

// Aggregate for summary widgets
        $weeklyAvgWinRate = $weeklyStats->avg( fn( $w ) => $w->total_days > 0 ? ( $w->profit_days / $w->total_days * 100 ) : 0
        );
        $weeklyAvgPnl     = $weeklyStats->avg( 'total_pnl' );

        $availableEntryTimes = DB::table( function ( $sub ) {
            $sub->from( 'backtest_trades' )
                ->when( request()->filled( 'strategy' ), fn( $q ) => $q->where( 'strategy', request( 'strategy' ) ) )
                ->when( request()->filled( 'symbol' ), fn( $q ) => $q->where( 'underlying_symbol', request( 'symbol' ) ) )
                ->selectRaw( "DISTINCT DATE_FORMAT(entry_time, '%H:%i') AS entry_time_label" );
        }, 'times' )
                                 ->orderBy( 'entry_time_label' )
                                 ->pluck( 'entry_time_label' )
                                 ->toArray();

        $availableExitTimes = DB::table( function ( $sub ) {
            $sub->from( 'backtest_trades' )
                ->when( request()->filled( 'strategy' ), fn( $q ) => $q->where( 'strategy', request( 'strategy' ) ) )
                ->when( request()->filled( 'symbol' ), fn( $q ) => $q->where( 'underlying_symbol', request( 'symbol' ) ) )
                ->when( request()->filled( 'entry_time' ), fn( $q ) => // Only show exit times that are after the selected entry time
                $q->whereRaw( "DATE_FORMAT(entry_time, '%H:%i') = ?", [ request( 'entry_time' ) ] )
                )
                ->whereNotNull( 'exit_time' )
                ->selectRaw( "DISTINCT DATE_FORMAT(exit_time, '%H:%i') AS exit_time_label" );
        }, 'times' )
                                ->orderBy( 'exit_time_label' )
                                ->pluck( 'exit_time_label' )
                                ->toArray();

        return view( 'backtest.index', compact(
            'days',
            'statsQuery',
            'monthlyStats',
            'availableStrategies',
            'expiryDates',
            'weeklyStats',
            'weeklyAvgWinRate',
            'dowStats',
            'weeklyAvgPnl',
            'availableEntryTimes',
            'availableExitTimes',
        ) );
    }

    /**
     * Trades: show the 4 legs for a single day_group_id.
     */
    public function trades( Request $request ) {
        $groupId = $request->get( 'group_id' );

        abort_if( ! $groupId, 404, 'group_id is required.' );

        // The day meta (single row)
        // trades() — $day query
        $day = DB::table( 'backtest_trades' )
                 ->selectRaw( '
        day_group_id,
        backtest_run_id,
        underlying_symbol,
        exchange,
        strategy,
        trade_date,
        expiry,
        index_price_at_entry,
        strike_offset,
        target,
        stoploss,
        lot_size,
        day_total_pnl,
        day_outcome,
        MIN(signal_time)         AS signal_time,
        MAX(day_max_profit)          AS day_max_profit,
        MAX(day_max_profit_time)     AS day_max_profit_time,
        MIN(day_max_loss)            AS day_max_loss,
        MIN(day_max_loss_time)       AS day_max_loss_time,
        MIN(entry_time)              AS entry_time,
        MAX(exit_time)               AS exit_time,
        MAX(trade_time_duration)     AS trade_time_duration
    ' )
                 ->where( 'day_group_id', $groupId )
                 ->groupBy(
                     'day_group_id', 'backtest_run_id', 'underlying_symbol',
                     'exchange', 'strategy', 'trade_date', 'expiry',
                     'index_price_at_entry', 'strike_offset', 'target',
                     'stoploss', 'lot_size', 'day_total_pnl', 'day_outcome'
                 )
                 ->first();

        abort_if( ! $day, 404, 'Day group not found.' );

        // The 4 legs
        $legs = BacktestTrade::where( 'day_group_id', $groupId )
                             ->orderBy( 'strike' )
                             ->orderBy( 'instrument_type' )
                             ->get();

        return view( 'backtest.trades', compact( 'day', 'legs' ) );
    }
}
