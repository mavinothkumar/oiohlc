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
        // Instant resolution (<1ms)
        $maxDate = null;
        try {
            $maxDate = DB::table('nse_working_days')
                ->where('current', 1)
                ->value('working_date');
        } catch (\Throwable $e) {
            $maxDate = null;
        }

        if (!$maxDate) {
            $maxDate = Carbon::now('Asia/Kolkata')->toDateString();
        }

        $dbTradesCount = 0;
        try {
            $dbTradesCount = DB::table('unified_backtest_trades')->count();
        } catch (\Throwable $e) {
            $dbTradesCount = 0;
        }

        return view('backtest.unified-breadth', [
            'minDate'       => '2024-01-01',
            'maxDate'       => $maxDate,
            'optMinDate'    => '2026-05-14',
            'dbTradesCount' => $dbTradesCount,
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
            'start_time'         => $request->input('start_time', '09:15'),
            'end_time'           => $request->input('end_time', '15:30'),
        ];

        // 1. Instant Database Filter Mode if pre-computed trades exist
        $dbCount = 0;
        try {
            $dbCount = DB::table('unified_backtest_trades')->count();
        } catch (\Throwable $e) {
            $dbCount = 0;
        }

        if ($dbCount > 0 && !$request->boolean('force_recompute')) {
            $startTime = microtime(true);
            $query = DB::table('unified_backtest_trades');

            if (!empty($config['from_date'])) {
                $query->where('date', '>=', $config['from_date']);
            }
            if (!empty($config['to_date'])) {
                $query->where('date', '<=', $config['to_date']);
            }
            if (!empty($config['start_time'])) {
                $query->where('entry_time', '>=', $config['start_time']);
            }
            if (!empty($config['end_time'])) {
                $query->where('entry_time', '<=', $config['end_time']);
            }
            if (!empty($config['mode']) && $config['mode'] !== 'both') {
                $query->where('mode', $config['mode']);
            }

            $trades = $query->orderBy('date')->orderBy('entry_time')->get()->map(fn($t) => (array)$t)->toArray();

            $daysCount = count(array_unique(array_column($trades, 'date')));
            $stats = $this->engine->calculateStatistics($trades, $daysCount);
            $chartData = $this->generateChartSeries($trades);
            $execTime = round(microtime(true) - $startTime, 3);

            return response()->json([
                'success'        => true,
                'source'         => 'database',
                'execution_time' => $execTime,
                'config'         => $config,
                'stats'          => $stats,
                'charts'         => $chartData,
                'trades'         => $trades,
            ]);
        }

        // 2. Fallback: Dynamic simulation calculation
        $startTime = microtime(true);
        $results = $this->engine->run($config);
        $executionTime = round(microtime(true) - $startTime, 2);

        // Generate Chart Series (Equity Curve, Drawdown, Monthly Breakdown)
        $chartData = $this->generateChartSeries($results['trades']);

        return response()->json([
            'success'        => true,
            'source'         => 'simulation',
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
