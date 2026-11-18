<?php

namespace App\Http\Controllers;

use App\Models\OrderFlowTradedVolume;
use App\Models\ZeroClickAlertLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class NiftyOptionTraderController extends Controller
{
    /**
     * Institutional NIFTY Option Execution Desk
     */
    public function index(Request $request): View
    {
        $nowKolkata = Carbon::now('Asia/Kolkata');
        $todayStr = $nowKolkata->toDateString();

        // 1. Available Dates
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

        $selectedDate = $request->input('date', $availableDates[0] ?? $todayStr);

        // 2. Available Expiries for NIFTY
        $expiries = [];
        if (Schema::hasTable('nse_expiries')) {
            $expiries = DB::table('nse_expiries')
                ->where('trading_symbol', 'NIFTY')
                ->where('instrument_type', 'OPT')
                ->where('expiry_date', '>=', $selectedDate)
                ->orderBy('expiry_date')
                ->pluck('expiry_date')
                ->unique()
                ->values()
                ->toArray();
        }

        if (empty($expiries) && Schema::hasTable('option_chains_history')) {
            $expiries = DB::table('option_chains_history')
                ->where('trading_symbol', 'NIFTY')
                ->where('captured_at', '>=', "{$selectedDate} 09:00:00")
                ->where('captured_at', '<=', "{$selectedDate} 23:59:59")
                ->where('expiry', '>=', $selectedDate)
                ->distinct()
                ->orderBy('expiry')
                ->pluck('expiry')
                ->toArray();
        }

        if (empty($expiries)) {
            $expiries = [$selectedDate];
        }

        $selectedExpiry = $request->input('expiry', $expiries[0] ?? $selectedDate);

        // 3. Webhook Token & Default Configuration
        $defaultWebhookUrl = env('ZEROCLICK_WEBHOOK_URL', 'https://0click.in/api/webhook/tradingview');
        $defaultWebhookToken = env('ZEROCLICK_WEBHOOK_TOKEN', '');

        return view('nifty-option-trader.index', compact(
            'availableDates',
            'selectedDate',
            'expiries',
            'selectedExpiry',
            'defaultWebhookUrl',
            'defaultWebhookToken'
        ));
    }

    /**
     * Dispatch trade order execution payload to 0Click.in Webhook
     */
    public function dispatchOrder(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $url = $request->input('webhook_url', env('ZEROCLICK_WEBHOOK_URL', 'https://0click.in/api/webhook/tradingview'));
        $token = $request->input('webhook_token') ?: env('ZEROCLICK_WEBHOOK_TOKEN', '');
        $mode = strtoupper($request->input('mode', 'LIVE')); // 'LIVE' or 'SIMULATION'
        
        $tradeStyle = $request->input('trade_style', 'OPTION_SELLING'); // 'OPTION_SELLING' (default 80%) or 'OPTION_BUYING'
        $lots = max(1, (int) $request->input('lots', ($tradeStyle === 'OPTION_SELLING' ? 8 : 10)));
        $lotSize = 65; // NIFTY lot size
        $quantity = $lots * $lotSize;

        $symbol = 'NIFTY';
        $optionType = strtoupper($request->input('option_type', 'PE')); // 'PE' or 'CE'
        $orderAction = strtoupper($request->input('action', ($tradeStyle === 'OPTION_SELLING' ? 'SELL' : 'BUY')));
        $strike = (int) $request->input('strike', 25200);
        $strikeName = "{$symbol} {$strike} {$optionType}";

        $spotPrice = (float) $request->input('spot_price', 0);
        $niftySl = (float) $request->input('nifty_sl', 0);
        $niftyTp = (float) $request->input('nifty_tp', 0);

        $mtmTarget = (float) $request->input('mtm_target', 5000);
        $mtmLoss = (float) $request->input('mtm_loss', 3000);
        $autoTrailing = $request->boolean('auto_trailing', true);
        $trailStep = (float) $request->input('trail_step', 1000);

        $marketSignal = ($orderAction === 'BUY' && $optionType === 'CE') || ($orderAction === 'SELL' && $optionType === 'PE') ? 'BUY' : 'SELL';

        $payload = [
            'category'         => 'NIFTY_OPTION_TRADER',
            'signal'           => $marketSignal,
            'action'           => $orderAction,
            'trade_type'       => $orderAction,
            'trade_style'      => $tradeStyle,
            'symbol'           => $symbol,
            'instrument'       => $optionType,
            'option_type'      => $optionType,
            'strike'           => $strike,
            'strike_name'      => $strikeName,
            'trading_symbol'   => $strikeName,
            'price'            => $spotPrice,
            'protection_basis' => 'BOTH',
            'nifty_sl'         => $niftySl,
            'nifty_tp'         => $niftyTp,
            'sl'               => $niftySl,
            'tp'               => $niftyTp,
            'lots'             => $lots,
            'lot_size'         => $lotSize,
            'quantity'         => $quantity,
            'mode'             => $mode,
            'product'          => 'MARGIN',
            'mtm_target'       => $mtmTarget,
            'mtm_loss'         => $mtmLoss,
            'auto_trailing'    => $autoTrailing,
            'trail_step'       => $trailStep,
            'notes'            => $request->input('notes', 'Order Flow S/R Wall Execution'),
        ];

        if (!empty($token)) {
            $payload['secret'] = $token;
        }

        $statusCode = 200;
        $apiResponse = [];
        $latencyMs = 0.0;
        $status = 'EXECUTED';

        if ($mode === 'SIMULATION') {
            $latencyMs = round((microtime(true) - $startTime) * 1000, 2);
            $apiResponse = [
                'success' => true,
                'message' => 'Simulated order recorded locally (No broker request sent)',
                'order_id' => 'SIM-' . strtoupper(substr(md5(uniqid()), 0, 8)),
                'mode' => 'SIMULATION',
                'executed_at' => Carbon::now('Asia/Kolkata')->format('H:i:s'),
                'fill_price' => (float)$request->input('opt_entry_price', 115.0),
            ];
            $status = 'SIMULATED';
        } else {
            // Live Webhook Dispatch to 0click.in
            try {
                $headers = [
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ];
                if (!empty($token)) {
                    $headers['X-Webhook-Token'] = $token;
                    $headers['X-API-KEY']       = $token;
                    $headers['Authorization']   = 'Bearer ' . $token;
                }

                $res = Http::timeout(10)->withHeaders($headers)->post($url, $payload);
                $latencyMs = round((microtime(true) - $startTime) * 1000, 2);
                $statusCode = $res->status();
                $apiResponse = $res->json() ?: ['raw' => $res->body()];
                $status = $res->successful() ? 'DISPATCHED' : 'FAILED';
            } catch (\Throwable $e) {
                $latencyMs = round((microtime(true) - $startTime) * 1000, 2);
                $statusCode = 500;
                $status = 'FAILED';
                $apiResponse = [
                    'success' => false,
                    'error'   => $e->getMessage(),
                ];
            }
        }

        // Audit Logging to zero_click_alert_logs
        $logRecord = null;
        if (Schema::hasTable('zero_click_alert_logs')) {
            try {
                $logRecord = ZeroClickAlertLog::create([
                    'triggered_at'       => Carbon::now('Asia/Kolkata'),
                    'trade_date'         => Carbon::now('Asia/Kolkata')->toDateString(),
                    'candle_time'        => Carbon::now('Asia/Kolkata')->format('H:i'),
                    'signal'             => $marketSignal,
                    'action'             => $orderAction,
                    'symbol'             => $symbol,
                    'instrument'         => $optionType,
                    'strike'             => $strike,
                    'spot_price'         => $spotPrice,
                    'sl_price'           => $niftySl,
                    'tp_price'           => $niftyTp,
                    'sl_offset_pts'      => (int)abs($spotPrice - $niftySl),
                    'tp_offset_pts'      => (int)abs($spotPrice - $niftyTp),
                    'lots'               => $lots,
                    'mode'               => $mode,
                    'product'            => 'MARGIN',
                    'category'           => 'NIFTY_OPTION_TRADER',
                    'payload'            => $payload,
                    'status'             => $status,
                    'endpoint_url'       => $url,
                    'api_status_code'    => $statusCode,
                    'api_response'       => $apiResponse,
                    'latency_ms'         => $latencyMs,
                    'dispatched_at'      => Carbon::now('Asia/Kolkata'),
                ]);
            } catch (\Throwable $e) {
                // Log silently
            }
        }

        return response()->json([
            'success'      => ($status === 'DISPATCHED' || $status === 'SIMULATED'),
            'status'       => $status,
            'status_code'  => $statusCode,
            'latency_ms'   => $latencyMs,
            'payload'      => $payload,
            'api_response' => $apiResponse,
            'log_id'       => $logRecord?->id,
        ]);
    }

    /**
     * Get recent dispatched alert logs for the Execution Audit Table
     */
    public function getLogs(Request $request): JsonResponse
    {
        $limit = min(50, max(5, (int) $request->input('limit', 20)));
        $date = $request->input('date', Carbon::now('Asia/Kolkata')->toDateString());

        $logs = [];
        if (Schema::hasTable('zero_click_alert_logs')) {
            $logs = ZeroClickAlertLog::where(function ($q) use ($date) {
                $q->where('trade_date', $date)
                  ->orWhere('category', 'NIFTY_OPTION_TRADER');
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
        }

        return response()->json([
            'success' => true,
            'logs'    => $logs,
        ]);
    }
}
