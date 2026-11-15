<?php

namespace App\Http\Controllers;

use App\Services\Backtest\HeikinBreadthBacktestEngine;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HeikinBreadthBacktestController extends Controller
{
    protected HeikinBreadthBacktestEngine $engine;

    public function __construct(HeikinBreadthBacktestEngine $engine)
    {
        $this->engine = $engine;
    }

    /**
     * Render the Heikin Ashi Breadth Backtest visual page.
     */
    public function index(Request $request): View
    {
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

        // Available min date from ohlc_5m_quotes
        $minDate = '2024-01-01';

        // Default default start date (past 90 days for snappy default load)
        $defaultFromDate = Carbon::parse($maxDate)->subMonths(3)->toDateString();

        return view('backtest.heikin-breadth', [
            'minDate'         => $minDate,
            'maxDate'         => $maxDate,
            'defaultFromDate' => $defaultFromDate,
            'defaultToDate'   => $maxDate,
        ]);
    }

    /**
     * Run backtest via AJAX and return performance metrics, charts, and trade log.
     */
    public function run(Request $request): JsonResponse
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        $startTime = microtime(true);

        $config = [
            'interval'           => (int)$request->input('interval', 15),
            'threshold'          => (float)$request->input('threshold', 75.0),
            'sl_mode'            => $request->input('sl_mode', 'normal'), // 'normal' or 'ha'
            'breadth_mode'       => $request->input('breadth_mode', 'both'), // 'both', 'count', 'weight', 'either'
            'lot_size'           => (int)$request->input('lot_size', 65),
            'max_trades_per_day' => (int)$request->input('max_trades_per_day', 2),
            'max_duration_mins'  => (int)$request->input('max_duration_mins', 0),
            'min_entry_time'     => $request->input('min_entry_time', '09:15'),
            'max_entry_time'     => $request->input('max_entry_time', '14:30'),
            'from_date'          => $request->input('from_date'),
            'to_date'            => $request->input('to_date'),
        ];

        $results = $this->engine->run($config);
        $execTime = round(microtime(true) - $startTime, 2);

        $chartData = $this->generateChartSeries($results['trades'] ?? []);

        return response()->json([
            'success'          => $results['success'] ?? true,
            'execution_time'   => $execTime,
            'config'           => $results['config'] ?? $config,
            'stats'            => $results['stats'] ?? [],
            'charts'           => $chartData,
            'trades'           => $results['trades'] ?? [],
            'discarded_trades' => $results['discarded_trades'] ?? [],
        ]);
    }

    /**
     * Run full Priority Comparison Matrix across user-requested combinations:
     * 1. 15 mins with 75, 80, 85, 90%
     * 2. 5 mins with 75, 80, 85, 90%
     * 3. 30 mins with 70, 75, 80, 85%
     */
    public function matrix(Request $request): JsonResponse
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(240);

        $startTime = microtime(true);

        $config = [
            'from_date'          => $request->input('from_date'),
            'to_date'            => $request->input('to_date'),
            'sl_mode'            => $request->input('sl_mode', 'normal'),
            'breadth_mode'       => $request->input('breadth_mode', 'both'),
            'lot_size'           => (int)$request->input('lot_size', 65),
            'max_trades_per_day' => (int)$request->input('max_trades_per_day', 2),
            'max_duration_mins'  => (int)$request->input('max_duration_mins', 0),
            'min_entry_time'     => $request->input('min_entry_time', '09:15'),
            'max_entry_time'     => $request->input('max_entry_time', '14:30'),
        ];

        $results = $this->engine->runPriorityMatrix($config);
        $execTime = round(microtime(true) - $startTime, 2);

        return response()->json([
            'success'        => $results['success'] ?? true,
            'execution_time' => $execTime,
            'matrix'         => $results['matrix'] ?? [],
            'total_trading_days' => $results['total_trading_days'] ?? 0,
            'from_date'      => $results['from_date'] ?? null,
            'to_date'        => $results['to_date'] ?? null,
            'sl_mode'        => $results['sl_mode'] ?? 'normal',
            'breadth_mode'   => $results['breadth_mode'] ?? 'both',
            'min_entry_time' => $results['min_entry_time'] ?? '09:15',
            'max_entry_time' => $results['max_entry_time'] ?? '14:30',
        ]);
    }

    /**
     * Export trades to CSV.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $config = [
            'interval'           => (int)$request->input('interval', 15),
            'threshold'          => (float)$request->input('threshold', 75.0),
            'sl_mode'            => $request->input('sl_mode', 'normal'),
            'breadth_mode'       => $request->input('breadth_mode', 'both'),
            'lot_size'           => (int)$request->input('lot_size', 65),
            'max_trades_per_day' => (int)$request->input('max_trades_per_day', 2),
            'max_duration_mins'  => (int)$request->input('max_duration_mins', 0),
            'min_entry_time'     => $request->input('min_entry_time', '09:15'),
            'max_entry_time'     => $request->input('max_entry_time', '14:30'),
            'from_date'          => $request->input('from_date'),
            'to_date'            => $request->input('to_date'),
        ];

        $results = $this->engine->run($config);
        $retainedTrades = $results['trades'] ?? [];
        $discardedTrades = $results['discarded_trades'] ?? [];

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="heikin_breadth_backtest_' . date('Ymd_His') . '.csv"',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($retainedTrades, $discardedTrades) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'Trade #', 'Record Type', 'Date', 'Day Trade #', 'Direction', 'Signal Time',
                'Stock Count %', 'Stock Weight %', 'Entry Time', 'Entry Price',
                'SL Price', 'SL Mode', 'Exit Time', 'Exit Price', 'Exit Reason',
                'Net Points', 'Realized PnL (INR)', 'Duration (Mins)', 'Candles Held',
                'MFE Pts', 'MAE Pts', 'Status'
            ]);

            foreach ($retainedTrades as $t) {
                fputcsv($file, [
                    $t['id'] ?? '',
                    'RETAINED',
                    $t['date'] ?? '',
                    $t['trade_no_in_day'] ?? 1,
                    $t['direction'] ?? '',
                    $t['signal_time'] ?? '',
                    $t['signal_breadth_count'] ?? '',
                    $t['signal_breadth_weight'] ?? '',
                    $t['entry_time'] ?? '',
                    $t['entry_price'] ?? '',
                    $t['sl_price'] ?? '',
                    $t['sl_mode'] ?? 'normal',
                    $t['exit_time'] ?? '',
                    $t['exit_price'] ?? '',
                    $t['exit_reason'] ?? '',
                    $t['points'] ?? 0,
                    $t['pnl'] ?? 0,
                    $t['duration_mins'] ?? 0,
                    $t['candles_held'] ?? 0,
                    $t['mfe_pts'] ?? 0,
                    $t['mae_pts'] ?? 0,
                    $t['status'] ?? '',
                ]);
            }

            foreach ($discardedTrades as $t) {
                fputcsv($file, [
                    $t['id'] ?? '',
                    'DISCARDED_STAGNANT_LOSS',
                    $t['date'] ?? '',
                    $t['trade_no_in_day'] ?? 1,
                    $t['direction'] ?? '',
                    $t['signal_time'] ?? '',
                    $t['signal_breadth_count'] ?? '',
                    $t['signal_breadth_weight'] ?? '',
                    $t['entry_time'] ?? '',
                    $t['entry_price'] ?? '',
                    $t['sl_price'] ?? '',
                    $t['sl_mode'] ?? 'normal',
                    $t['exit_time'] ?? '',
                    $t['exit_price'] ?? '',
                    $t['exit_reason'] ?? '',
                    $t['points'] ?? 0,
                    $t['pnl'] ?? 0,
                    $t['duration_mins'] ?? 0,
                    $t['candles_held'] ?? 0,
                    $t['mfe_pts'] ?? 0,
                    $t['mae_pts'] ?? 0,
                    'DISCARDED_LOSS',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Generate chart series data for Equity Curve, Drawdown, and Duration Distribution.
     */
    protected function generateChartSeries(array $trades): array
    {
        $equityPoints = [];
        $equityInr    = [];
        $drawdownPts  = [];
        $dailyPnl     = [];
        $durations    = [];

        $cumPts   = 0.0;
        $cumInr   = 0.0;
        $peakPts  = 0.0;

        foreach ($trades as $idx => $t) {
            $pts = (float)$t['points'];
            $inr = (float)$t['pnl'];
            $date = $t['date'];

            $cumPts += $pts;
            $cumInr += $inr;

            if ($cumPts > $peakPts) {
                $peakPts = $cumPts;
            }
            $dd = round($peakPts - $cumPts, 2);

            $equityPoints[] = [
                'x' => ($idx + 1) . ' (' . $date . ' ' . $t['entry_time'] . ')',
                'y' => round($cumPts, 2),
            ];

            $equityInr[] = [
                'x' => ($idx + 1) . ' (' . $date . ')',
                'y' => round($cumInr, 2),
            ];

            $drawdownPts[] = [
                'x' => ($idx + 1) . ' (' . $date . ')',
                'y' => -$dd,
            ];

            $dailyPnl[$date] = ($dailyPnl[$date] ?? 0.0) + $inr;

            $durations[] = [
                'duration' => (int)($t['duration_mins'] ?? 0),
                'points'   => $pts,
                'status'   => $t['status'] ?? 'WIN',
            ];
        }

        $dailySeries = [];
        foreach ($dailyPnl as $d => $pnl) {
            $dailySeries[] = [
                'date' => $d,
                'pnl'  => round($pnl, 2),
            ];
        }

        return [
            'equity_points' => $equityPoints,
            'equity_inr'    => $equityInr,
            'drawdown_pts'  => $drawdownPts,
            'daily_pnl'     => $dailySeries,
            'durations'     => $durations,
        ];
    }
}
