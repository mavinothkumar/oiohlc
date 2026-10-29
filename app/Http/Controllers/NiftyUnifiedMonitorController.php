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
        // If user explicitly provided ?date=..., use it.
        // Otherwise, during live market hours (09:15-15:35 weekdays) or if today has quotes, default to today.
        // During off-market/weekend/night hours when today has no quotes yet, default to the latest trading day with data.
        $curTimeStr = $nowKolkata->format('H:i');
        $isWeekday = !in_array($nowKolkata->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY]);
        $isMarketHours = ($isWeekday && $curTimeStr >= '09:15' && $curTimeStr <= '15:35');

        $defaultDate = ($isMarketHours || $latestQuoteDate === $todayStr)
            ? $todayStr
            : ($latestQuoteDate ?: $availableDates[0]);

        $selectedDate = $request->input('date', $defaultDate);

        // 2. Fetch available Expiries for NIFTY from nse_expiries (indexed, <1ms)
        $expiries = [];
        if (Schema::hasTable('nse_expiries')) {
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
