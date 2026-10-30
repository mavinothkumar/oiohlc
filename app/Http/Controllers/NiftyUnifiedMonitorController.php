<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class NiftyUnifiedMonitorController extends Controller
{
    /**
     * Unified NIFTY 50 Command Center:
     * Combines NIFTY 50 Weightage Monitor, Options Breadth Monitor, and Trending OI & Buildup.
     */
    public function index(Request $request): View
    {
        $nowKolkata = Carbon::now('Asia/Kolkata');
        $todayStr = $nowKolkata->toDateString();

        // 1. Fetch available trading dates efficiently from nse_working_days (<1ms)
        $availableDates = [];
        if (Schema::hasTable('nse_working_days')) {
            $availableDates = DB::table('nse_working_days')
                ->where('working_date', '<=', $todayStr)
                ->orderByDesc('working_date')
                ->limit(20)
                ->pluck('working_date')
                ->toArray();
        }

        if (empty($availableDates)) {
            $availableDates = [$todayStr];
        }

        // Fast resolution of latest date with quotes (<50ms using compound index on uq_instrument_ts)
        $latestQuoteDate = null;
        try {
            $latestTs = DB::table('ohlc_5m_quotes')
                ->where('instrument_key', 'NSE_INDEX|Nifty 50')
                ->orderByDesc('ts')
                ->value('ts_at');
            if ($latestTs) {
                $latestQuoteDate = substr($latestTs, 0, 10);
            }
        } catch (\Throwable $e) {
            $latestQuoteDate = null;
        }

        // Ensure latestQuoteDate is in available dates list
        if ($latestQuoteDate && !in_array($latestQuoteDate, $availableDates)) {
            array_unshift($availableDates, $latestQuoteDate);
            $availableDates = array_values(array_unique($availableDates));
            rsort($availableDates);
        }

        // Default date selection:
        // Priority:
        // 1. Explicit query parameter (?date=YYYY-MM-DD)
        // 2. Current working date from nse_working_days (current = 1)
        // 3. Most recent working date (<= today) from nse_working_days
        // 4. Fallback to today
        $currentWorkingDate = null;
        if (Schema::hasTable('nse_working_days')) {
            $currentWorkingDate = DB::table('nse_working_days')
                ->where('current', 1)
                ->value('working_date');
        }

        $defaultDate = $currentWorkingDate ?: ($availableDates[0] ?? $todayStr);

        $selectedDate = $request->input('date', $defaultDate);

        // 2. Fetch available Expiries for NIFTY
        $expiries = [];

        // For past dates, load actual historical expiries
        if ($selectedDate < $todayStr) {
            if (Schema::hasTable('option_chains_history')) {
                $pastExpiries = DB::table('option_chains_history')
                    ->where('trading_symbol', 'NIFTY')
                    ->where('captured_at', '>=', "{$selectedDate} 09:00:00")
                    ->where('captured_at', '<=', "{$selectedDate} 15:35:00")
                    ->distinct()
                    ->orderBy('expiry')
                    ->pluck('expiry')
                    ->toArray();
                if (!empty($pastExpiries)) {
                    $expiries = $pastExpiries;
                }
            }

            if (empty($expiries) && Schema::hasTable('expired_expiries')) {
                $expiries = DB::table('expired_expiries')
                    ->where('underlying_symbol', 'NIFTY')
                    ->where('instrument_type', 'OPT')
                    ->where('expiry_date', '>=', $selectedDate)
                    ->orderBy('expiry_date')
                    ->limit(6)
                    ->pluck('expiry_date')
                    ->toArray();
            }
        }

        if (empty($expiries) && Schema::hasTable('nse_expiries')) {
            $expiries = DB::table('nse_expiries')
                ->where('trading_symbol', 'NIFTY')
                ->where('instrument_type', 'OPT')
                ->orderBy('expiry_date')
                ->pluck('expiry_date')
                ->toArray();
        }

        // If empty, check current live option_chains table (small table)
        if (empty($expiries) && Schema::hasTable('option_chains')) {
            $expiries = DB::table('option_chains')
                ->where('underlying_key', 'like', '%Nifty%')
                ->select('expiry')
                ->distinct()
                ->orderBy('expiry')
                ->pluck('expiry')
                ->toArray();
        }

        // Select the nearest expiry on or after selected date, or first available
        $defaultExpiry = null;
        if (!empty($expiries)) {
            foreach ($expiries as $exp) {
                if ($exp >= $selectedDate) {
                    $defaultExpiry = $exp;
                    break;
                }
            }
            if (!$defaultExpiry) {
                $defaultExpiry = $expiries[0];
            }
        } else {
            $defaultExpiry = $selectedDate;
        }

        $selectedExpiry = $request->input('expiry', $defaultExpiry);

        return view('nifty-unified.index', compact(
            'selectedDate',
            'availableDates',
            'expiries',
            'selectedExpiry'
        ));
    }
}
