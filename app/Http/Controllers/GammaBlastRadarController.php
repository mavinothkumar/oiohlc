<?php

namespace App\Http\Controllers;

use App\Models\GammaBlastAlertLog;
use App\Services\Options\GammaExposureService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GammaBlastRadarController extends Controller
{
    protected GammaExposureService $gexService;

    public function __construct(GammaExposureService $gexService)
    {
        $this->gexService = $gexService;
    }

    /**
     * Main Gamma Blast Radar & GEX Cockpit View
     */
    public function index(Request $request): View
    {
        $symbol = $request->input('symbol', 'NIFTY');
        $availableDates = $this->gexService->getAvailableDates($symbol);
        $today = Carbon::today('Asia/Kolkata')->toDateString();
        $latestDateWithData = $this->gexService->getLatestDateWithData($symbol);
        $selectedDate = $request->input('date', $latestDateWithData);
        $expiries = $this->gexService->getExpiries($selectedDate, $symbol);
        $selectedExpiry = $request->input('expiry', $expiries[0] ?? null);
        $selectedTime = $request->input('time', null);

        $gexData = $this->gexService->calculateGex($selectedDate, $selectedExpiry, $symbol, $selectedTime);

        $initialAlertLogs = GammaBlastAlertLog::where('trade_date', $selectedDate)
            ->where('symbol', $symbol)
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();

        return view('gamma-blast-radar.index', compact(
            'symbol',
            'availableDates',
            'selectedDate',
            'expiries',
            'selectedExpiry',
            'selectedTime',
            'gexData',
            'initialAlertLogs'
        ));
    }

    /**
     * AJAX endpoint for real-time auto-refresh, expiry switch, and timeline scrubbing
     */
    public function getData(Request $request): JsonResponse
    {
        $symbol = $request->input('symbol', 'NIFTY');
        $date = $request->input('date', Carbon::today('Asia/Kolkata')->toDateString());
        $expiry = $request->input('expiry', null);
        $time = $request->input('time', null);

        try {
            $data = $this->gexService->calculateGex($date, $expiry, $symbol, $time);
            return response()->json($data);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * AJAX endpoint to get expiries for a specific date
     */
    public function getExpiries(Request $request): JsonResponse
    {
        $symbol = $request->input('symbol', 'NIFTY');
        $date = $request->input('date', Carbon::today('Asia/Kolkata')->toDateString());
        $expiries = $this->gexService->getExpiries($date, $symbol);

        return response()->json([
            'success'  => true,
            'expiries' => $expiries,
        ]);
    }

    /**
     * Log an alert generated based on user threshold settings
     */
    public function logAlert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'trade_date'        => 'nullable|string',
            'alert_time'        => 'nullable|string',
            'symbol'            => 'nullable|string',
            'expiry'            => 'nullable|string',
            'spot_price'        => 'required|numeric',
            'alert_type'        => 'required|string',
            'title'             => 'required|string',
            'strike'            => 'nullable|numeric',
            'option_type'       => 'nullable|string',
            'direction'         => 'nullable|string',
            'score'             => 'nullable|numeric',
            'distance_pts'      => 'nullable|numeric',
            'trigger_condition' => 'nullable|string',
            'zero_gamma_flip'   => 'nullable|numeric',
            'call_wall'         => 'nullable|numeric',
            'put_wall'          => 'nullable|numeric',
            'net_gex_cr'        => 'nullable|numeric',
            'regime'            => 'nullable|string',
            'message'           => 'required|string',
            'settings_snapshot' => 'nullable|array',
            'meta'              => 'nullable|array',
        ]);

        $tradeDate = $validated['trade_date'] ?? Carbon::now('Asia/Kolkata')->toDateString();
        $alertTime = $validated['alert_time'] ?? Carbon::now('Asia/Kolkata')->format('H:i:s');
        $symbol = strtoupper($validated['symbol'] ?? 'NIFTY');

        // Deduplication guard: avoid duplicate rows within 45 seconds for same message & date
        $existing = GammaBlastAlertLog::where('trade_date', $tradeDate)
            ->where('symbol', $symbol)
            ->where('message', $validated['message'])
            ->where('created_at', '>=', Carbon::now('Asia/Kolkata')->subSeconds(45))
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'logged'  => false,
                'message' => 'Duplicate alert within throttle window',
                'log'     => $existing,
            ]);
        }

        $log = GammaBlastAlertLog::create([
            'trade_date'        => $tradeDate,
            'alert_time'        => $alertTime,
            'symbol'            => $symbol,
            'expiry'            => $validated['expiry'] ?? null,
            'spot_price'        => round((float)$validated['spot_price'], 2),
            'alert_type'        => $validated['alert_type'],
            'title'             => $validated['title'],
            'strike'            => isset($validated['strike']) ? (float)$validated['strike'] : null,
            'option_type'       => $validated['option_type'] ?? null,
            'direction'         => $validated['direction'] ?? 'NEUTRAL',
            'score'             => isset($validated['score']) ? (float)$validated['score'] : null,
            'distance_pts'      => isset($validated['distance_pts']) ? (float)$validated['distance_pts'] : null,
            'trigger_condition' => $validated['trigger_condition'] ?? null,
            'zero_gamma_flip'   => isset($validated['zero_gamma_flip']) ? (float)$validated['zero_gamma_flip'] : null,
            'call_wall'         => isset($validated['call_wall']) ? (float)$validated['call_wall'] : null,
            'put_wall'          => isset($validated['put_wall']) ? (float)$validated['put_wall'] : null,
            'net_gex_cr'        => isset($validated['net_gex_cr']) ? (float)$validated['net_gex_cr'] : null,
            'regime'            => $validated['regime'] ?? null,
            'message'           => $validated['message'],
            'settings_snapshot' => $validated['settings_snapshot'] ?? null,
            'meta'              => $validated['meta'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'logged'  => true,
            'log'     => $log,
        ]);
    }

    /**
     * Get alert logs for audit and analysis
     */
    public function getAlertLogs(Request $request): JsonResponse
    {
        $symbol = $request->input('symbol', 'NIFTY');
        $date = $request->input('date', Carbon::today('Asia/Kolkata')->toDateString());
        $limit = (int)$request->input('limit', 100);

        $logs = GammaBlastAlertLog::where('trade_date', $date)
            ->where('symbol', $symbol)
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'count'   => $logs->count(),
            'logs'    => $logs,
        ]);
    }

    /**
     * Clear alert logs
     */
    public function clearAlertLogs(Request $request): JsonResponse
    {
        $symbol = $request->input('symbol', 'NIFTY');
        $date = $request->input('date', null);

        $query = GammaBlastAlertLog::where('symbol', $symbol);
        if ($date) {
            $query->where('trade_date', $date);
        }
        $deleted = $query->delete();

        return response()->json([
            'success' => true,
            'deleted' => $deleted,
            'message' => 'Alert logs cleared successfully',
        ]);
    }
}
