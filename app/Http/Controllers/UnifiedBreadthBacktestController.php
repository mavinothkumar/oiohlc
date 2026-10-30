<?php

namespace App\Http\Controllers;

use App\Services\Backtest\UnifiedBreadthBacktestEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UnifiedBreadthBacktestController extends Controller
{
    protected UnifiedBreadthBacktestEngine $engine;

    public function __construct(UnifiedBreadthBacktestEngine $engine)
    {
        $this->engine = $engine;
    }

    /**
     * Render the Unified Breadth Backtest visual cockpit page.
     */
    public function index(Request $request): View
    {
        // Resolve date boundaries
        $minDate = DB::table('ohlc_5m_quotes')
            ->where('instrument_key', 'NSE_INDEX|Nifty 50')
            ->min(DB::raw('DATE(ts_at)')) ?: '2024-01-01';

        $maxDate = DB::table('ohlc_5m_quotes')
            ->where('instrument_key', 'NSE_INDEX|Nifty 50')
            ->max(DB::raw('DATE(ts_at)')) ?: Carbon::now('Asia/Kolkata')->toDateString();

        $optMinDate = DB::table('option_chains_history')
            ->where('trading_symbol', 'NIFTY')
            ->min(DB::raw('DATE(captured_at)')) ?: '2026-05-14';

        return view('backtest.unified-breadth', [
            'minDate'    => $minDate,
            'maxDate'    => $maxDate,
            'optMinDate' => $optMinDate,
        ]);
    }

    /**
     * Run backtest via AJAX and return performance metrics, equity curve, and trades.
     */
    public function run(Request $request): JsonResponse
    {
        $config = [
            'mode'               => $request->input('mode', 'both'),
            'from_date'          => $request->input('from_date'),
            'to_date'            => $request->input('to_date'),
            'stock_threshold'    => (float)$request->input('stock_threshold', 80.0),
            'option_threshold'   => (float)$request->input('option_threshold', 75.0),
            'sl_buffer'          => (float)$request->input('sl_buffer', 15.0),
            'tp1'                => (float)$request->input('tp1', 100.0),
            'tp2'                => (float)$request->input('tp2', 150.0),
            'basket_mode'        => $request->input('basket_mode', 'dynamic'),
            'max_trades_per_day' => (int)$request->input('max_trades_per_day', 2),
            'start_time'         => $request->input('start_time', '09:30'),
            'end_time'           => $request->input('end_time', '14:30'),
        ];

        $startTime = microtime(true);
        $results = $this->engine->run($config);
        $executionTime = round(microtime(true) - $startTime, 2);

        // Generate Chart Series (Equity Curve, Drawdown, Monthly Breakdown)
        $chartData = $this->generateChartSeries($results['trades']);

        return response()->json([
            'success'        => true,
            'execution_time' => $executionTime,
            'config'         => $results['config'],
            'stats'          => $results['stats'],
            'charts'         => $chartData,
            'trades'         => $results['trades'],
        ]);
    }

    /**
     * Build Chart.js formatted series from trade logs.
     */
    protected function generateChartSeries(array $trades): array
    {
        $equityLabels = ['Start'];
        $equityValues = [0.0];
        $drawdownValues = [0.0];

        $runningPnl = 0.0;
        $peak = 0.0;
        $monthlyPnl = [];

        foreach ($trades as $idx => $t) {
            $pts = (float)$t['net_pts'];
            $runningPnl += $pts;
            $runningPnl = round($runningPnl, 2);

            if ($runningPnl > $peak) {
                $peak = $runningPnl;
            }
            $dd = round($runningPnl - $peak, 2); // Negative or zero

            $equityLabels[] = "{$t['date']} {$t['entry_time']}";
            $equityValues[] = $runningPnl;
            $drawdownValues[] = $dd;

            // Monthly aggregation
            $monthKey = substr($t['date'], 0, 7); // YYYY-MM
            if (!isset($monthlyPnl[$monthKey])) {
                $monthlyPnl[$monthKey] = 0.0;
            }
            $monthlyPnl[$monthKey] += $pts;
        }

        // Format Monthly Chart
        $monthLabels = array_keys($monthlyPnl);
        $monthValues = array_map(fn($v) => round($v, 1), array_values($monthlyPnl));

        return [
            'equity' => [
                'labels' => $equityLabels,
                'values' => $equityValues,
            ],
            'drawdown' => [
                'labels' => $equityLabels,
                'values' => $drawdownValues,
            ],
            'monthly' => [
                'labels' => $monthLabels,
                'values' => $monthValues,
            ],
        ];
    }
}
