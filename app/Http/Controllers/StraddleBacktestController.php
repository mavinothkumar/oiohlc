<?php

namespace App\Http\Controllers;

use App\Models\StraddleEntrySlot;
use Illuminate\Http\Request;

class StraddleBacktestController extends Controller
{
    public function index(Request $request)
    {
        $query = StraddleEntrySlot::query()
                                  ->join('straddle_entries', function ($join) {
                                      $join->on('straddle_entry_slots.symbol', '=', 'straddle_entries.symbol')
                                           ->on('straddle_entry_slots.expiry_date', '=', 'straddle_entries.expiry_date')
                                           ->on('straddle_entry_slots.trade_date', '=', 'straddle_entries.trade_date')
                                           ->on('straddle_entry_slots.atm_strike', '=', 'straddle_entries.atm_strike');
                                  })
                                  ->select(
                                      'straddle_entry_slots.*',
                                      'straddle_entries.ce_entry_price',
                                      'straddle_entries.pe_entry_price'
                                  );

        // Symbol filter
        if ($request->filled('symbol')) {
            $query->where('straddle_entry_slots.symbol', $request->symbol);
        }

        if ($request->filled('exclude_expiry_day')) {
            $query->whereColumn('straddle_entries.trade_date', '!=', 'straddle_entries.expiry_date');
        }

        // Trade date range filter (IMPORTANT: fully qualify column)
        if ($request->filled('trade_date_from')) {
            $query->whereDate('straddle_entry_slots.trade_date', '>=', $request->trade_date_from);
        }

        if ($request->filled('trade_date_to')) {
            $query->whereDate('straddle_entry_slots.trade_date', '<=', $request->trade_date_to);
        }

        // Expiry date filter (optional)
        if ($request->filled('expiry_from')) {
            $query->whereDate('straddle_entry_slots.expiry_date', '>=', $request->expiry_from);
        }

        if ($request->filled('expiry_to')) {
            $query->whereDate('straddle_entry_slots.expiry_date', '<=', $request->expiry_to);
        }

        // Hour slot filter
        if ($request->filled('hour_slot')) {
            $query->where('straddle_entry_slots.hour_slot', $request->hour_slot);
        }

        // Total PnL filters
        if ($request->filled('pnl_min')) {
            $query->where('straddle_entry_slots.total_pnl', '>=', $request->pnl_min);
        }

        if ($request->filled('pnl_max')) {
            $query->where('straddle_entry_slots.total_pnl', '<=', $request->pnl_max);
        }

        // Entry-based filters using CE/PE entry from straddle_entries

        if ($request->filled('min_ce_entry')) {
            $query->where('straddle_entries.ce_entry_price', '>=', $request->min_ce_entry);
        }

        if ($request->filled('max_ce_entry')) {
            $query->where('straddle_entries.ce_entry_price', '<=', $request->max_ce_entry);
        }

        if ($request->filled('min_pe_entry')) {
            $query->where('straddle_entries.pe_entry_price', '>=', $request->min_pe_entry);
        }

        if ($request->filled('max_pe_entry')) {
            $query->where('straddle_entries.pe_entry_price', '<=', $request->max_pe_entry);
        }

        // Optional: min / max entry of either leg
        if ($request->filled('min_leg_entry')) {
            $min = $request->min_leg_entry;
            $query->where(function ($q) use ($min) {
                $q->where('straddle_entries.ce_entry_price', '>=', $min)
                  ->orWhere('straddle_entries.pe_entry_price', '>=', $min);
            });
        }

        if ($request->filled('max_leg_entry')) {
            $max = $request->max_leg_entry;
            $query->where(function ($q) use ($max) {
                $q->where('straddle_entries.ce_entry_price', '<=', $max)
                  ->orWhere('straddle_entries.pe_entry_price', '<=', $max);
            });
        }

        // Ordering
        $query->orderBy('straddle_entry_slots.trade_date', 'desc')
              ->orderBy('straddle_entry_slots.hour_slot')
              ->orderBy('straddle_entry_slots.slot_time');

        $slots = $query->paginate(50)->withQueryString();

        // For symbol dropdown
        $symbols = StraddleEntrySlot::select('symbol')->distinct()->pluck('symbol');

        return view('backtests.straddles.index', [
            'slots'   => $slots,
            'symbols' => $symbols,
            'filters' => $request->only([
                'symbol',
                'trade_date_from',
                'trade_date_to',
                'expiry_from',
                'expiry_to',
                'hour_slot',
                'pnl_min',
                'pnl_max',
                'min_ce_entry',
                'max_ce_entry',
                'min_pe_entry',
                'max_pe_entry',
                'min_leg_entry',
                'max_leg_entry',
                'exclude_expiry_day',
            ]),
        ]);
    }
}
