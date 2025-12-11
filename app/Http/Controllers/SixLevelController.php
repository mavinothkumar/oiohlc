<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Expiry;
use Carbon\Carbon;

class SixLevelController extends Controller
{
    public function index(Request $request)
    {
        $q = DB::table('six_level_backtests'); // or your model

        // Date range
        if ($request->filled('from')) {
            $q->whereDate('trade_date', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $q->whereDate('trade_date', '<=', $request->input('to'));
        }

        // 6-level side filter (call/put/both)
        if ($request->filled('six_level_side') && $request->six_level_side !== 'all') {
            $q->where('six_level_broken_side', $request->six_level_side);
        }

        // “retraced to low?” filter
        if ($request->filled('retested_low') && $request->retested_low !== 'all') {
            $flag = $request->retested_low === 'yes' ? 1 : 0;
            $q->where(function ($sub) use ($flag) {
                $sub->where('ce_low_retested', $flag)
                    ->orWhere('pe_low_retested', $flag);
            });
        }

        // “opponent high/close reached?” filter
        if ($request->filled('opponent_reached') && $request->opponent_reached !== 'all') {
            $flag = $request->opponent_reached === 'yes' ? 1 : 0;
            $q->where(function ($sub) use ($flag) {
                $sub->where('ce_opponent_prev_high_broken', $flag)
                    ->orWhere('ce_opponent_prev_close_crossed', $flag)
                    ->orWhere('pe_opponent_prev_high_broken', $flag)
                    ->orWhere('pe_opponent_prev_close_crossed', $flag);
            });
        }

        // “broke at 9:15?” filter
        if ($request->filled('broke_at_open') && $request->broke_at_open !== 'all') {
            $flag = $request->broke_at_open === 'yes';
            $q->where(function ($sub) use ($flag) {
                if ($flag) {
                    $sub->whereTime('ce_break_time', '09:15:00')
                        ->orWhereTime('pe_break_time', '09:15:00');
                } else {
                    $sub->where(function ($s2) {
                        $s2->whereNotNull('ce_break_time')
                           ->whereTime('ce_break_time', '<>', '09:15:00');
                    })->orWhere(function ($s2) {
                        $s2->whereNotNull('pe_break_time')
                           ->whereTime('pe_break_time', '<>', '09:15:00');
                    });
                }
            });
        }

        $backtests = $q->orderBy('trade_date')->paginate(100);

        // Simple stats for the current filter
        $totalDays = $backtests->count();
        $daysRetestedLow = $backtests->filter(function ($row) {
            return !empty($row->ce_low_retested) || !empty($row->pe_low_retested);
        })->count();
        $daysOpponentReached = $backtests->filter(function ($row) {
            return !empty($row->ce_opponent_prev_high_broken)
                   || !empty($row->ce_opponent_prev_close_crossed)
                   || !empty($row->pe_opponent_prev_high_broken)
                   || !empty($row->pe_opponent_prev_close_crossed);
        })->count();
        $daysBrokeAtOpen = $backtests->filter(function ($row) {
            return in_array(substr($row->ce_break_time ?? '', 0, 5), ['09:15'])
                   || in_array(substr($row->pe_break_time ?? '', 0, 5), ['09:15']);
        })->count();

        return view('six-level.index', [
            'backtests'          => $backtests,
            'totalDays'          => $totalDays,
            'daysRetestedLow'    => $daysRetestedLow,
            'daysOpponentReached'=> $daysOpponentReached,
            'daysBrokeAtOpen'    => $daysBrokeAtOpen,
            'filters'            => $request->all(),
        ]);
    }

}
