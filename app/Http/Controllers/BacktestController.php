<?php

// app/Http/Controllers/BacktestController.php

namespace App\Http\Controllers;

use App\Models\BacktestTrade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BacktestController extends Controller
{
    /**
     * Index: one row per trading day (day_group_id).
     */
    public function index(Request $request)
    {
        // Always pass available strategies for the dropdown
        $availableStrategies = \App\Services\Backtest\StrategyRegistry::available();

        // No strategy selected → return empty state
        if (!$request->filled('strategy')) {
            return view('backtest.index', [
                'days'                => (new \Illuminate\Pagination\LengthAwarePaginator([], 0, 30)),
                'statsQuery'          => null,
                'availableStrategies' => $availableStrategies,
            ]);
        }
        //$symbol = strtoupper($request->input('symbol', 'NIFTY'));

        // ── Load expiry dates for the symbol ──────────────────────────────
            $expiryDates = DB::table('expired_expiries')
                         ->where('underlying_symbol', 'NIFTY')
                         ->where('instrument_type', 'OPT')
                         ->pluck('expiry_date')
                         ->mapWithKeys(fn($d) => [
                             \Carbon\Carbon::parse($d)->toDateString() => true
                         ])
                         ->toArray();

        $expiryDateList = array_keys($expiryDates);


        $query = DB::table('backtest_trades')
                   ->selectRaw('
            day_group_id, backtest_run_id, underlying_symbol, exchange,
            strategy, trade_date, expiry, index_price_at_entry,
            strike_offset, target, stoploss, lot_size,
            day_total_pnl, day_outcome,
            MAX(day_max_profit)          AS day_max_profit,
            MAX(day_max_profit_time)     AS day_max_profit_time,
            MIN(day_max_loss)            AS day_max_loss,
            MIN(day_max_loss_time)       AS day_max_loss_time,
            MIN(entry_time)              AS entry_time,
            MAX(exit_time)               AS exit_time,
            MAX(trade_time_duration)     AS trade_time_duration,
            COUNT(*)                     AS total_legs
        ')
                   ->groupBy(
                       'day_group_id', 'backtest_run_id', 'underlying_symbol', 'exchange',
                       'strategy', 'trade_date', 'expiry', 'index_price_at_entry',
                       'strike_offset', 'target', 'stoploss', 'lot_size',
                       'day_total_pnl', 'day_outcome'
                   )
                   ->where('strategy', $request->strategy);

        // Symbol
        if ($request->filled('symbol')) {
            $query->where('underlying_symbol', $request->symbol);
        }

        // Outcome
        if ($request->filled('outcome')) {
            $query->where('day_outcome', $request->outcome);
        }

        // Day P&L filter
        if ($request->filled('pnl_dir') && $request->filled('pnl_value')) {
            $request->pnl_dir === 'gte'
                ? $query->where('day_total_pnl', '>=', $request->pnl_value)
                : $query->where('day_total_pnl', '<=', $request->pnl_value);
        }

        // Peak filter
        if ($request->filled('peak_filter')) {
            match($request->peak_filter) {
                'has_peak_profit'      => $query->where('day_max_profit', '>', 0),
                'no_peak_profit'       => $query->where(fn($q) =>
                $q->whereNull('day_max_profit')
                  ->orWhere('day_max_profit', '<=', 0)),
                'peak_profit_reversed' => $query->where('day_max_profit', '>', 0)
                                                ->where('day_outcome', 'loss'),
                'has_peak_loss'        => $query->where('day_max_loss', '<', 0),
                'no_peak_loss'         => $query->where(fn($q) =>
                $q->whereNull('day_max_loss')
                  ->orWhere('day_max_loss', '>=', 0)),
                'peak_loss_recovered'  => $query->where('day_max_loss', '<', 0)
                                                ->where('day_outcome', 'profit'),
                default                => null,
            };
        }

        // Date range
        if ($request->filled('from')) {
            $query->whereDate('trade_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('trade_date', '<=', $request->to);
        }

        if ($request->skip_expiry == '1' && !empty($expiryDateList)) {
            $query->whereNotIn('trade_date', $expiryDateList);
        }

        $days = $query->orderByDesc('trade_date')->paginate(30)->withQueryString();

        // Stats
        $statsQuery = DB::table('backtest_trades')
                        ->where('strategy', $request->strategy)
                        ->when($request->filled('symbol'),  fn($q) => $q->where('underlying_symbol', $request->symbol))
                        ->when($request->filled('outcome'), fn($q) => $q->where('day_outcome', $request->outcome))
                        ->when($request->filled('from'),    fn($q) => $q->whereDate('trade_date', '>=', $request->from))
                        ->when($request->filled('to'),      fn($q) => $q->whereDate('trade_date', '<=', $request->to))
                        ->when(
                            $request->skip_expiry == '1' && !empty($expiryDateList),
                            fn($q) => $q->whereNotIn('trade_date', $expiryDateList)
                        )
                        ->selectRaw('
            COUNT(DISTINCT day_group_id)                                            AS total_days,
            ROUND(SUM(pnl), 2)                                                     AS total_pnl,
            COUNT(DISTINCT CASE WHEN day_outcome="profit" THEN day_group_id END)   AS profit_days,
            COUNT(DISTINCT CASE WHEN day_outcome="loss"   THEN day_group_id END)   AS loss_days,
            AVG(CASE WHEN day_outcome="profit" THEN trade_time_duration END)       AS avg_profit_min,
            AVG(CASE WHEN day_outcome="loss"   THEN trade_time_duration END)       AS avg_loss_min,
            MAX(day_total_pnl)                                                     AS best_day,
            MIN(day_total_pnl)                                                     AS worst_day,
            AVG(day_max_profit)                                                    AS avg_max_profit,
            AVG(day_max_loss)                                                      AS avg_max_loss
        ')
                        ->first();

        return view('backtest.index', compact('days', 'statsQuery', 'availableStrategies','expiryDates'));
    }

    /**
     * Trades: show the 4 legs for a single day_group_id.
     */
    public function trades(Request $request)
    {
        $groupId = $request->get('group_id');

        abort_if(!$groupId, 404, 'group_id is required.');

        // The day meta (single row)
        // trades() — $day query
        $day = DB::table('backtest_trades')
                 ->selectRaw('
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
        MAX(day_max_profit)          AS day_max_profit,
        MAX(day_max_profit_time)     AS day_max_profit_time,
        MIN(day_max_loss)            AS day_max_loss,
        MIN(day_max_loss_time)       AS day_max_loss_time,
        MIN(entry_time)              AS entry_time,
        MAX(exit_time)               AS exit_time,
        MAX(trade_time_duration)     AS trade_time_duration
    ')
                 ->where('day_group_id', $groupId)
                 ->groupBy(
                     'day_group_id', 'backtest_run_id', 'underlying_symbol',
                     'exchange', 'strategy', 'trade_date', 'expiry',
                     'index_price_at_entry', 'strike_offset', 'target',
                     'stoploss', 'lot_size', 'day_total_pnl', 'day_outcome'
                 )
                 ->first();

        abort_if(!$day, 404, 'Day group not found.');

        // The 4 legs
        $legs = BacktestTrade::where('day_group_id', $groupId)
                             ->orderBy('strike')
                             ->orderBy('instrument_type')
                             ->get();

        return view('backtest.trades', compact('day', 'legs'));
    }
}
