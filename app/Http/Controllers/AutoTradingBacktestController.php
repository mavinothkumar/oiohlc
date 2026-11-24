<?php

namespace App\Http\Controllers;

use App\Models\AutoTradeV2Day;
use App\Models\AutoTradeV2Trade;
use App\Services\Backtest\AutoTradingV2Engine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AutoTradingBacktestController extends Controller
{
    protected AutoTradingV2Engine $engine;

    public function __construct(AutoTradingV2Engine $engine)
    {
        $this->engine = $engine;
    }

    /**
     * Render the Automated Trading Backtest Dashboard page.
     */
    public function index(Request $request): View
    {
        $rangePreset = $request->input('range', 'all');
        $fromDate = $request->input('from');
        $toDate = $request->input('to');
        $statusFilter = $request->input('status', 'all');
        $setupFilter = $request->input('setup', 'all');
        $oiFilter = $request->input('oi_sentiment', 'all');
        $directionFilter = $request->input('direction', 'all');
        $exitFilter = $request->input('exit_category', 'all');
        $sessionFilter = $request->input('session_window', 'all');
        $modeFilter = $request->input('mode', 'SELLING');

        $nowKolkata = Carbon::now('Asia/Kolkata');
        $todayStr = $nowKolkata->toDateString();

        if ($rangePreset === '7d') {
            $fromDate = $nowKolkata->copy()->subDays(7)->toDateString();
            $toDate = $todayStr;
        } elseif ($rangePreset === '30d') {
            $fromDate = $nowKolkata->copy()->subDays(30)->toDateString();
            $toDate = $todayStr;
        } elseif ($rangePreset === '90d') {
            $fromDate = $nowKolkata->copy()->subDays(90)->toDateString();
            $toDate = $todayStr;
        } elseif ($rangePreset === '1y') {
            $fromDate = $nowKolkata->copy()->subYear()->toDateString();
            $toDate = $todayStr;
        } elseif ($rangePreset === 'this_month') {
            $fromDate = $nowKolkata->copy()->startOfMonth()->toDateString();
            $toDate = $todayStr;
        } elseif ($rangePreset === 'last_month') {
            $fromDate = $nowKolkata->copy()->subMonth()->startOfMonth()->toDateString();
            $toDate = $nowKolkata->copy()->subMonth()->endOfMonth()->toDateString();
        }

        // Query Days
        $hasTradeFilters = ($setupFilter !== 'all' || $oiFilter !== 'all' || $directionFilter !== 'all' || $exitFilter !== 'all' || $sessionFilter !== 'all' || $modeFilter !== 'all');

        $daysQuery = AutoTradeV2Day::with(['trades' => function ($q) use ($setupFilter, $oiFilter, $directionFilter, $exitFilter, $sessionFilter, $modeFilter) {
            if ($modeFilter !== 'all') $q->where('trade_mode', $modeFilter);
            if ($setupFilter !== 'all') $q->where('setup_type', $setupFilter);
            if ($oiFilter !== 'all') $q->where('entry_oi_sentiment', $oiFilter);
            if ($directionFilter !== 'all') $q->where('direction', $directionFilter);
            if ($exitFilter !== 'all') $q->where('exit_category', $exitFilter);
            if ($sessionFilter !== 'all') $q->where('session_window', $sessionFilter);
            $q->orderBy('entry_time', 'asc');
        }])->orderByDesc('date');

        if ($fromDate) {
            $daysQuery->where('date', '>=', $fromDate);
        }
        if ($toDate) {
            $daysQuery->where('date', '<=', $toDate);
        }
        if ($statusFilter !== 'all') {
            $daysQuery->where('day_status', $statusFilter);
        }
        if ($hasTradeFilters) {
            $daysQuery->whereHas('trades', function ($q) use ($setupFilter, $oiFilter, $directionFilter, $exitFilter, $sessionFilter, $modeFilter) {
                if ($modeFilter !== 'all') $q->where('trade_mode', $modeFilter);
                if ($setupFilter !== 'all') $q->where('setup_type', $setupFilter);
                if ($oiFilter !== 'all') $q->where('entry_oi_sentiment', $oiFilter);
                if ($directionFilter !== 'all') $q->where('direction', $directionFilter);
                if ($exitFilter !== 'all') $q->where('exit_category', $exitFilter);
                if ($sessionFilter !== 'all') $q->where('session_window', $sessionFilter);
            });
        }

        $days = $daysQuery->get();

        // Calculate dynamic per-day metrics aligned with active filters and mode
        foreach ($days as $day) {
            $dayTrades = $day->trades;
            $day->computed_trades_count = $dayTrades->count();
            $day->computed_net_rs = $dayTrades->sum('pnl_rupees');
            $day->computed_net_pts = $dayTrades->sum('pnl_points');
            $day->computed_status = $dayTrades->count() === 0 ? 'NO_TRADE' : ($day->computed_net_rs > 500 ? 'WIN' : ($day->computed_net_rs < -500 ? 'LOSS' : 'SCRATCH'));
        }

        // Query All Trades in Range for KPI Calculations
        $tradesQuery = AutoTradeV2Trade::query();
        if ($modeFilter !== 'all') $tradesQuery->where('trade_mode', $modeFilter);
        if ($fromDate) $tradesQuery->where('date', '>=', $fromDate);
        if ($toDate) $tradesQuery->where('date', '<=', $toDate);
        if ($setupFilter !== 'all') $tradesQuery->where('setup_type', $setupFilter);
        if ($oiFilter !== 'all') $tradesQuery->where('entry_oi_sentiment', $oiFilter);
        if ($directionFilter !== 'all') $tradesQuery->where('direction', $directionFilter);
        if ($exitFilter !== 'all') $tradesQuery->where('exit_category', $exitFilter);
        if ($sessionFilter !== 'all') $tradesQuery->where('session_window', $sessionFilter);
        $trades = $tradesQuery->get();

        // Calculate Global KPI Stats
        $totalDays = $days->count();
        $tradedDays = $days->where('computed_trades_count', '>', 0)->count();
        $winDays = $days->where('computed_status', 'WIN')->count();
        $lossDays = $days->where('computed_status', 'LOSS')->count();
        $noTradeDays = $days->where('computed_status', 'NO_TRADE')->count();

        $totalTrades = $trades->count();
        $winTrades = $trades->where('pnl_rupees', '>', 0)->count();
        $lossTrades = $trades->where('pnl_rupees', '<', 0)->count();
        $scratchTrades = $trades->where('pnl_rupees', '==', 0)->count();
        $winRate = $totalTrades > 0 ? round(($winTrades / $totalTrades) * 100, 1) : 0;

        $netPnlRupees = $trades->sum('pnl_rupees');
        $netPnlPoints = $trades->sum('pnl_points');

        $grossProfit = $trades->where('pnl_rupees', '>', 0)->sum('pnl_rupees');
        $grossLoss = abs($trades->where('pnl_rupees', '<', 0)->sum('pnl_rupees'));
        $profitFactor = $grossLoss > 0 ? round($grossProfit / $grossLoss, 2) : ($grossProfit > 0 ? 99.9 : 0);

        $avgWinRs = $winTrades > 0 ? round($grossProfit / $winTrades) : 0;
        $avgLossRs = $lossTrades > 0 ? round($grossLoss / $lossTrades) : 0;
        $winLossRatio = $avgLossRs > 0 ? round($avgWinRs / $avgLossRs, 2) : 0;

        $stagnationExits = $trades->where('is_stagnation_trailed', true)->count();
        $squeezeTrades = $trades->where('setup_type', 'SQUEEZE_BREAKOUT')->count();
        $confluenceTrades = $trades->where('setup_type', 'GRADE_A_CONFLUENCE')->count();

        // Max Drawdown Calculation (from cumulative peak)
        $runningPnl = 0;
        $peakPnl = 0;
        $maxDrawdown = 0;
        foreach ($trades->sortBy('entry_time')->sortBy('date') as $t) {
            $runningPnl += $t->pnl_rupees;
            if ($runningPnl > $peakPnl) $peakPnl = $runningPnl;
            $dd = $peakPnl - $runningPnl;
            if ($dd > $maxDrawdown) $maxDrawdown = $dd;
        }

        // Monthly Breakdown
        $monthlyStats = [];
        foreach ($days->groupBy(fn($d) => $d->date->format('Y-m')) as $month => $mDays) {
            $mTradesCount = $mDays->sum('computed_trades_count');
            $mWins = $mDays->where('computed_status', 'WIN')->count();
            $mLosses = $mDays->where('computed_status', 'LOSS')->count();
            $mNetRs = $mDays->sum('computed_net_rs');
            $mNetPts = $mDays->sum('computed_net_pts');

            $monthlyStats[$month] = [
                'month_name'   => Carbon::createFromFormat('Y-m', $month)->format('F Y'),
                'total_days'   => $mDays->count(),
                'traded_days'  => $mDays->where('computed_trades_count', '>', 0)->count(),
                'win_days'     => $mWins,
                'loss_days'    => $mLosses,
                'total_trades' => $mTradesCount,
                'net_rupees'   => $mNetRs,
                'net_points'   => $mNetPts,
                'win_rate'     => ($mWins + $mLosses) > 0 ? round(($mWins / ($mWins + $mLosses)) * 100, 1) : 0,
            ];
        }

        // Weekly Breakdown
        $weeklyStats = [];
        foreach ($days->groupBy(fn($d) => $d->date->format('Y') . '-W' . $d->date->format('W')) as $weekKey => $wDays) {
            $wNetRs = $wDays->sum('computed_net_rs');
            $wNetPts = $wDays->sum('computed_net_pts');
            $wWins = $wDays->where('computed_status', 'WIN')->count();
            $wLosses = $wDays->where('computed_status', 'LOSS')->count();
            $weeklyStats[$weekKey] = [
                'week_label'   => 'Week ' . substr($weekKey, -2) . ' (' . $wDays->last()->date->format('d M') . ' - ' . $wDays->first()->date->format('d M') . ')',
                'net_rupees'   => $wNetRs,
                'net_points'   => $wNetPts,
                'win_days'     => $wWins,
                'loss_days'    => $wLosses,
                'total_trades' => $wDays->sum('computed_trades_count'),
            ];
        }

        return view('backtest.auto-trading-v2', [
            'days'             => $days,
            'totalDays'        => $totalDays,
            'tradedDays'       => $tradedDays,
            'winDays'          => $winDays,
            'lossDays'         => $lossDays,
            'noTradeDays'      => $noTradeDays,
            'totalTrades'      => $totalTrades,
            'winTrades'        => $winTrades,
            'lossTrades'       => $lossTrades,
            'scratchTrades'    => $scratchTrades,
            'winRate'          => $winRate,
            'netPnlRupees'     => $netPnlRupees,
            'netPnlPoints'     => $netPnlPoints,
            'grossProfit'      => $grossProfit,
            'grossLoss'        => $grossLoss,
            'profitFactor'     => $profitFactor,
            'avgWinRs'         => $avgWinRs,
            'avgLossRs'        => $avgLossRs,
            'winLossRatio'     => $winLossRatio,
            'maxDrawdown'      => $maxDrawdown,
            'stagnationExits'  => $stagnationExits,
            'squeezeTrades'    => $squeezeTrades,
            'confluenceTrades' => $confluenceTrades,
            'monthlyStats'     => $monthlyStats,
            'weeklyStats'      => $weeklyStats,
            'rangePreset'      => $rangePreset,
            'fromDate'         => $fromDate,
            'toDate'           => $toDate,
            'statusFilter'     => $statusFilter,
            'setupFilter'      => $setupFilter,
            'oiFilter'         => $oiFilter,
            'directionFilter'  => $directionFilter,
            'exitFilter'       => $exitFilter,
            'sessionFilter'    => $sessionFilter,
            'modeFilter'       => $modeFilter,
        ]);
    }

    /**
     * AJAX: Get candle decision forensics for a specific day.
     */
    public function dayForensics(string $date): JsonResponse
    {
        $day = AutoTradeV2Day::with('trades')->where('date', $date)->first();
        if (!$day) {
            return response()->json(['success' => false, 'message' => 'Day not found'], 404);
        }

        $trades = $day->trades;
        $telemetry = [];
        if ($trades->isNotEmpty() && !empty($trades->first()->telemetry_json)) {
            $telemetry = $trades->first()->telemetry_json;
        }

        return response()->json([
            'success'   => true,
            'day'       => $day,
            'trades'    => $trades,
            'telemetry' => $telemetry,
        ]);
    }

    /**
     * AJAX: Trigger backend backtest recalculation on demand.
     */
    public function runRecalculation(Request $request): JsonResponse
    {
        $fromDate = $request->input('from');
        $toDate = $request->input('to');
        $daysCount = $request->input('days', 30);

        $nowKolkata = Carbon::now('Asia/Kolkata');
        $todayStr = $nowKolkata->toDateString();

        $query = DB::table('nse_working_days')
            ->where('working_date', '<=', $todayStr)
            ->orderBy('working_date', 'asc');

        if ($fromDate) $query->where('working_date', '>=', $fromDate);
        if ($toDate) $query->where('working_date', '<=', $toDate);

        $dates = $query->pluck('working_date')->toArray();
        if ($daysCount && !$fromDate) {
            $dates = array_slice($dates, -$daysCount);
        }

        if (empty($dates)) {
            return response()->json(['success' => false, 'message' => 'No trading dates found'], 400);
        }

        $customConfig = [
            'startTime' => $request->input('startTime', '09:35'),
            'endTime'   => $request->input('endTime', '14:45'),
        ];

        $results = $this->engine->runDays($dates, $customConfig);

        return response()->json([
            'success'      => true,
            'total_days'   => $results['total_days'],
            'total_trades' => $results['total_trades'],
            'message'      => "Successfully simulated and saved {$results['total_days']} days.",
        ]);
    }
}
