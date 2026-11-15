<?php

namespace App\Http\Controllers;

use App\Services\TimeframeResampler;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class BankNiftyMultiTimeframeMonitorController extends Controller
{
    /**
     * Dedicated BANK NIFTY Command Center with Multi-Timeframe Capabilities:
     * Supports 1m, 3m, 5m, 15m, 30m, 60m, and 1 Day durations across all 4 quadrants.
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

        // Fast resolution of latest date with Bank Nifty quotes
        $latestQuoteDate = null;
        try {
            $latestTs = DB::table('ohlc_5m_quotes')
                ->where(function($q) {
                    $q->where('instrument_key', 'like', '%Nifty Bank%')
                      ->orWhere('trading_symbol', 'Nifty Bank')
                      ->orWhere('trading_symbol', 'BANKNIFTY');
                })
                ->orderByDesc('ts')
                ->value('ts_at');
            if ($latestTs) {
                $latestQuoteDate = substr($latestTs, 0, 10);
            }
        } catch (\Throwable $e) {
            $latestQuoteDate = null;
        }

        if ($latestQuoteDate && !in_array($latestQuoteDate, $availableDates)) {
            array_unshift($availableDates, $latestQuoteDate);
            $availableDates = array_values(array_unique($availableDates));
            rsort($availableDates);
        }

        $currentWorkingDate = null;
        if (Schema::hasTable('nse_working_days')) {
            $currentWorkingDate = DB::table('nse_working_days')
                ->where('current', 1)
                ->value('working_date');
        }

        $defaultDate = $currentWorkingDate ?: ($availableDates[0] ?? $todayStr);
        $selectedDate = $request->input('date', $defaultDate);

        // 2. Fetch available Expiries for BANKNIFTY
        $expiries = [];
        if ($selectedDate < $todayStr) {
            if (Schema::hasTable('option_chains_history')) {
                $pastExpiries = DB::table(DB::raw("option_chains_history USE INDEX (idx_och_sym_exp_cap)"))
                    ->where('trading_symbol', 'BANKNIFTY')
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
                    ->where('underlying_symbol', 'BANKNIFTY')
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
                ->where('trading_symbol', 'BANKNIFTY')
                ->where('instrument_type', 'OPT')
                ->orderBy('expiry_date')
                ->pluck('expiry_date')
                ->toArray();
        }

        if (empty($expiries) && Schema::hasTable('option_chains')) {
            $expiries = DB::table('option_chains')
                ->where(function($q) {
                    $q->where('underlying_key', 'like', '%Nifty Bank%')
                      ->orWhere('trading_symbol', 'BANKNIFTY');
                })
                ->select('expiry')
                ->distinct()
                ->orderBy('expiry')
                ->pluck('expiry')
                ->toArray();
        }

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

        // 3. Multi-Timeframe options
        $availableTimeframes = [
            '1m'  => ['label' => '1M',  'minutes' => 1],
            '3m'  => ['label' => '3M',  'minutes' => 3],
            '5m'  => ['label' => '5M',  'minutes' => 5],
            '15m' => ['label' => '15M', 'minutes' => 15],
            '30m' => ['label' => '30M', 'minutes' => 30],
            '60m' => ['label' => '1H',  'minutes' => 60],
            '1D'  => ['label' => '1D',  'minutes' => 375],
        ];

        $rawTf = $request->input('timeframe', $request->input('interval', '5m'));
        $tfMinutes = TimeframeResampler::normalizeInterval($rawTf);
        $selectedTimeframe = match ($tfMinutes) {
            1       => '1m',
            3       => '3m',
            15      => '15m',
            30      => '30m',
            60      => '60m',
            375     => '1D',
            default => '5m',
        };

        // 4. Institutional Quant S/R Levels (Bank Nifty Spot)
        $quantPlaybook = null;
        try {
            $reportService = app(\App\Services\OrderFlow\OrderFlowCumulativeReportService::class);
            $reportData = $reportService->generateReport($selectedDate, 'BANKNIFTY');
            $quantPlaybook = $reportData['tomorrow_playbook'] ?? null;
        } catch (\Throwable $e) {
            $quantPlaybook = null;
        }

        return view('banknifty-unified.multi-timeframe', compact(
            'selectedDate',
            'availableDates',
            'expiries',
            'selectedExpiry',
            'quantPlaybook',
            'availableTimeframes',
            'selectedTimeframe',
            'tfMinutes'
        ));
    }
}
