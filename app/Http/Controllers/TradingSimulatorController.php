<?php
// app/Http/Controllers/TradingSimulatorController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TradingSimulatorController extends Controller {
    // Nifty lot size
    const LOT_SIZE = 75;

    public function index() {
        // Available trading dates from expired_ohlc

        $dates = DB::table( 'nse_working_days' )
                   ->selectRaw( 'DATE(working_date) as trade_date' )
                   ->distinct()
                   ->orderByDesc( 'trade_date' )
                   ->pluck( 'trade_date' );

        // Latest available date to default the calendar to
        $latestDate = $dates->first();

        return view( 'test.trading-simulator', compact( 'dates', 'latestDate' ) );
    }

    /**
     * Get expiry for a given date (nearest expiry >= date from expired_expiries)
     */
    public function getExpiry( Request $request ) {
        $date = $request->query( 'date' );

        $expiry = DB::table( 'expired_expiries' )
                    ->where( 'underlying_symbol', 'NIFTY' )
                    ->where( 'instrument_type', 'OPT' )
                    ->where( 'expiry_date', '>=', $date )
                    ->orderBy( 'expiry_date' )
                    ->value( 'expiry_date' );


        return response()->json( [ 'expiry' => $expiry ] );
    }

    /**
     * Get available strikes for a given date + expiry
     */
    public function getStrikes( Request $request ) {
        $date   = $request->query( 'date' );
        $expiry = $request->query( 'expiry' );

        $strikes = DB::table( 'expired_ohlc' )
                     ->where( 'underlying_symbol', 'NIFTY' )
                     ->where( 'expiry', $expiry )
                     ->whereDate( 'timestamp', $date )
                     ->select( 'strike', 'instrument_type' )
                     ->distinct()
                     ->orderBy( 'strike' )
                     ->get()
                     ->groupBy( 'strike' )
                     ->map( fn( $group ) => $group->pluck( 'instrument_type' ) )
                     ->toArray();

        return response()->json( [ 'strikes' => $strikes ] );
    }

    /**
     * Get OHLC open price for a specific strike/type/expiry at a given datetime
     */
    public function getPrice( Request $request ) {
        $expiry    = $request->query( 'expiry' );
        $strike    = $request->query( 'strike' );
        $type      = $request->query( 'type' );      // CE or PE
        $timestamp = $request->query( 'timestamp' ); // Y-m-d H:i:s

        $row = DB::table( 'expired_ohlc' )
                 ->where( 'underlying_symbol', 'NIFTY' )
                 ->where( 'expiry', $expiry )
                 ->where( 'strike', $strike )
                 ->where( 'instrument_type', $type )
                 ->where( 'timestamp', $timestamp )
                 ->select( 'open', 'high', 'low', 'close', 'volume', 'open_interest' )
                 ->first();

        return response()->json( $row ?? [ 'open' => null ] );
    }

    public function enterPosition(Request $request)
    {
        $sessionId  = $request->input('session_id');
        $tradeDate  = $request->input('trade_date');
        $expiry     = $request->input('expiry');
        $strike     = $request->input('strike');
        $type       = $request->input('instrument_type');
        $side       = $request->input('side');
        $avgEntry   = $request->input('avg_entry');
        $entryPrice = $request->input('entry_price');
        $lots       = $request->input('lots');
        $qty        = $request->input('qty');

        // Upsert position — update avg_entry and qty each time
        $position = \App\Models\SimPosition::firstOrCreate(
            [
                'session_id'      => $sessionId,
                'trade_date'      => $tradeDate,
                'strike'          => $strike,
                'instrument_type' => $type,
                'expiry'          => $expiry,
            ],
            [
                'underlying'   => 'NIFTY',
                'side'         => $side,
                'avg_entry'    => $avgEntry,
                'total_qty'    => 0,
                'open_qty'     => 0,
                'realized_pnl' => 0,
                'status'       => 'open',
            ]
        );

        // Update running avg_entry and quantities
        $position->avg_entry  = $avgEntry;           // JS already computed weighted avg
        $position->total_qty += $qty;
        $position->open_qty  += $qty;
        $position->status     = 'open';
        $position->save();

        // Log the entry order
        \App\Models\SimOrder::create([
            'position_id'  => $position->id,
            'session_id'   => $sessionId,
            'trade_date'   => $tradeDate,
            'order_type'   => 'entry',
            'side'         => $side,
            'price'        => $entryPrice,
            'qty'          => $qty,
            'lots'         => $lots,
            'pnl'          => 0,
            'executed_at'  => now(),
        ]);

        return response()->json(['success' => true, 'position_id' => $position->id]);
    }


    public function exitPosition(Request $request)
    {
        $sessionId  = $request->input('session_id');
        $tradeDate  = $request->input('trade_date');
        $expiry     = $request->input('expiry');
        $strike     = $request->input('strike');
        $type       = $request->input('instrument_type');
        $side       = $request->input('side');
        $avgEntry   = $request->input('avg_entry');
        $exitPrice  = $request->input('exit_price');
        $exitLots   = $request->input('exit_lots');
        $exitQty    = $request->input('exit_qty');
        $pnl        = $request->input('pnl');
        $orderType  = $request->input('order_type'); // partial_exit | full_exit
        $outcome    = $request->input('outcome');
        $comment    = $request->input('comment', '');
        $exitTime   = $request->input('exit_time');

        // Upsert position record
        $position = \App\Models\SimPosition::firstOrCreate(
            [
                'session_id'      => $sessionId,
                'trade_date'      => $tradeDate,
                'strike'          => $strike,
                'instrument_type' => $type,
                'expiry'          => $expiry,
            ],
            [
                'underlying'  => 'NIFTY',
                'side'        => $side,
                'avg_entry'   => $avgEntry,
                'total_qty'   => $exitQty,
                'open_qty'    => 0,
                'realized_pnl'=> 0,
                'status'      => 'open',
            ]
        );

        // Update position
        $position->avg_entry    = $avgEntry;
        $position->realized_pnl = $position->realized_pnl + $pnl;
        $position->open_qty     = max(0, $position->open_qty - $exitQty);
        if ($orderType === 'full_exit') {
            $position->status = 'closed';
        }
        $position->save();

        // Log exit order
        \App\Models\SimOrder::create([
            'position_id'  => $position->id,
            'session_id'   => $sessionId,
            'trade_date'   => $tradeDate,
            'order_type'   => $orderType,
            'side'         => $side === 'BUY' ? 'SELL' : 'BUY',
            'price'        => $exitPrice,
            'qty'          => $exitQty,
            'lots'         => $exitLots,
            'pnl'          => $pnl,
            'executed_at'  => now(),
        ]);

        // Save comment/note if present
        if (!empty($comment)) {
            \App\Models\SimTradeNote::create([
                'position_id' => $position->id,
                'session_id'  => $sessionId,
                'comment'     => $comment,
                'outcome'     => $outcome,
                'exit_price'  => $exitPrice,
                'exit_qty'    => $exitQty,
            ]);
        }

        return response()->json(['success' => true, 'position_id' => $position->id]);
    }


    /**
     * Consolidated Report — list all sessions/positions
     */
    public function report(Request $request)
    {
        $query = \App\Models\SimPosition::with(['orders', 'notes'])
                                        ->orderByDesc('trade_date')
                                        ->orderByDesc('created_at');

        // Filters
        if ($request->filled('date')) {
            $query->whereDate('trade_date', $request->input('date'));
        }
        if ($request->filled('outcome')) {
            $query->whereHas('notes', fn($q) => $q->where('outcome', $request->input('outcome')));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $positions = $query->paginate(15)->withQueryString();

        // Summary stats
        $stats = \App\Models\SimPosition::selectRaw('
        COUNT(*) as total_trades,
        SUM(CASE WHEN realized_pnl > 0 THEN 1 ELSE 0 END) as winners,
        SUM(CASE WHEN realized_pnl < 0 THEN 1 ELSE 0 END) as losers,
        SUM(realized_pnl) as total_pnl,
        AVG(realized_pnl) as avg_pnl,
        MAX(realized_pnl) as best_trade,
        MIN(realized_pnl) as worst_trade
    ')->where('status', 'closed')->first();

        return view('test.trading-simulator-report', compact('positions', 'stats'));
    }

    /**
     * Report Detail — single position with all orders + notes
     */
    public function reportDetail(\App\Models\SimPosition $position)
    {
        $position->load(['orders' => fn($q) => $q->orderBy('executed_at'),
            'notes']);

        return view('test.trading-simulator-report-detail', compact('position'));
    }

// TradingSimulatorController.php
    public function storeNote(\App\Models\SimPosition $position, \Illuminate\Http\Request $request)
    {
        \App\Models\SimTradeNote::create([
            'position_id' => $position->id,
            'session_id'  => $position->session_id,
            'comment'     => $request->input('comment'),
            'outcome'     => $request->input('outcome'),
        ]);

        return redirect()->route('trading-simulator.report.detail', $position->id)
                         ->with('success', 'Note saved.');
    }

}
