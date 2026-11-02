<?php

namespace App\Http\Controllers;

use App\Models\OrderFlowSignalLog;
use App\Models\ZeroClickAlertLog;
use App\Services\Quant\HighProbabilityEngine;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class OrderFlowStrategyController extends Controller
{
    protected HighProbabilityEngine $quantEngine;

    public function __construct(HighProbabilityEngine $quantEngine)
    {
        $this->quantEngine = $quantEngine;
    }

    /**
     * Display the 30-Depth Order Flow & Multi-Strategy Confluence Radar.
     */
    public function index(Request $request): View
    {
        // Exclusively NIFTY 50 & Nifty Futures
        $symbol = 'NIFTY';

        // 1. Resolve working date
        $workingDay = null;
        if (Schema::hasTable('nse_working_days')) {
            $workingDay = DB::table('nse_working_days')
                ->where('current', 1)
                ->first();

            if (!$workingDay) {
                $workingDay = DB::table('nse_working_days')
                    ->where('working_date', '<=', Carbon::today('Asia/Kolkata')->format('Y-m-d'))
                    ->orderBy('working_date', 'desc')
                    ->first();
            }
        }
        $workingDate = $workingDay ? $workingDay->working_date : Carbon::today('Asia/Kolkata')->format('Y-m-d');

        // 2. Resolve expiries
        $expiries = collect();
        if (Schema::hasTable('nse_expiries')) {
            $expiries = DB::table('nse_expiries')
                ->where('trading_symbol', $symbol)
                ->where('instrument_type', 'OPT')
                ->where('expiry_date', '>=', $workingDate)
                ->orderBy('expiry_date')
                ->take(8)
                ->get();
        }

        $currentExpiry = $expiries->firstWhere('is_current', 1) ?? $expiries->first();
        $selectedExpiry = $request->input('expiry', $currentExpiry ? $currentExpiry->expiry_date : $workingDate);

        // 3. Resolve Index & Spot Key
        $indexKey = match ($symbol) {
            'BANKNIFTY' => 'NSE_INDEX|Nifty Bank',
            'FINNIFTY'  => 'NSE_INDEX|Nifty Fin Service',
            default     => 'NSE_INDEX|Nifty 50',
        };

        // 4. Resolve ATM Strike & Trend Reference
        $currentTrend = null;
        if (Schema::hasTable('daily_trend')) {
            $currentTrend = DB::table('daily_trend')
                ->where('symbol_name', $symbol)
                ->where(function ($q) use ($workingDate) {
                    $q->where('quote_date', $workingDate)
                      ->orWhere('trading_date', $workingDate);
                })
                ->orderByDesc('id')
                ->first();

            if (!$currentTrend) {
                $currentTrend = DB::table('daily_trend')
                    ->where('symbol_name', $symbol)
                    ->orderByDesc('id')
                    ->first();
            }
        }

        $spotPrice = 24500.0;
        $openPrice = 24450.0;
        $detectedAtm = 24500;

        if ($currentTrend) {
            $spotPrice = (float) ($currentTrend->index_close ?? $currentTrend->current_day_index_open ?? 24500.0);
            $openPrice = (float) ($currentTrend->current_day_index_open ?? $spotPrice);
            if (!empty($currentTrend->atm_index_open)) {
                $detectedAtm = (int) (round($currentTrend->atm_index_open / 50) * 50);
            } elseif (!empty($currentTrend->strike)) {
                $detectedAtm = (int) $currentTrend->strike;
            } else {
                $detectedAtm = (int) (round($spotPrice / 50) * 50);
            }
        }

        $strikeStep = $symbol === 'BANKNIFTY' ? 100 : 50;
        $atmStrike = $request->filled('atm') ? (int) $request->input('atm') : $detectedAtm;

        // 5. Resolve Active Contracts (Future, ATM CE, ATM PE, ATM±1, ATM±2)
        $contracts = $this->resolveAvailableContracts($symbol, $selectedExpiry, $atmStrike, $strikeStep);

        // 6. Pre-calculate Strategy Confluence Signals for Initial Load
        $strategySignals = $this->computeStrategyConfluenceSignals($symbol, $workingDate, $selectedExpiry, $atmStrike, $spotPrice);

        return view('order-flow-depth.index', [
            'symbol'          => $symbol,
            'workingDate'     => $workingDate,
            'expiries'        => $expiries,
            'selectedExpiry'  => $selectedExpiry,
            'indexKey'        => $indexKey,
            'spotPrice'       => $spotPrice,
            'openPrice'       => $openPrice,
            'atmStrike'       => $atmStrike,
            'strikeStep'      => $strikeStep,
            'contracts'       => $contracts,
            'strategySignals' => $strategySignals,
        ]);
    }

    /**
     * Get Upstox Market Data Feed V3 Authorized WebSocket URL.
     */
    public function getWsUrl(): JsonResponse
    {
        $token = config('services.upstox.analytics_token');
        if (!$token) {
            return response()->json([
                'success' => false,
                'error'   => 'Upstox access token not configured in services.upstox.analytics_token'
            ], 400);
        }

        try {
            $response = Http::withHeaders([
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ])->timeout(8)->get('https://api.upstox.com/v3/feed/market-data-feed/authorize');

            if ($response->successful()) {
                $data = $response->json();
                $uri = $data['data']['authorizedRedirectUri'] ?? $data['authorizedRedirectUri'] ?? null;
                return response()->json([
                    'success'               => true,
                    'authorizedRedirectUri' => $uri,
                    'data'                  => $data,
                ]);
            }

            return response()->json([
                'success' => false,
                'error'   => 'Upstox Auth Error: ' . $response->body(),
                'status'  => $response->status(),
            ], $response->status());
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Exception connecting to Upstox API: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * AJAX endpoint to poll/refresh strategy signals and confluence status.
     */
    public function getStrategySignals(Request $request): JsonResponse
    {
        $symbol   = 'NIFTY';
        $date     = $request->input('date', today()->toDateString());
        $expiry   = $request->input('expiry');
        $atm      = (int) $request->input('atm', 24500);
        $spot     = (float) $request->input('spot', 24500.0);

        $signals = $this->computeStrategyConfluenceSignals($symbol, $date, $expiry, $atm, $spot);

        return response()->json([
            'success' => true,
            'data'    => $signals,
        ]);
    }

    /**
     * AJAX endpoint to fetch contracts and instrument keys for a selected strike/expiry.
     */
    public function getContracts(Request $request): JsonResponse
    {
        $symbol   = 'NIFTY';
        $expiry   = $request->input('expiry');
        $atm      = (int) $request->input('atm', 24500);
        $step     = 50;

        $contracts = $this->resolveAvailableContracts($symbol, $expiry, $atm, $step);

        return response()->json([
            'success'   => true,
            'contracts' => $contracts,
        ]);
    }

    /**
     * Log a high-probability trade opportunity call (Entry).
     */
    public function logSignal(Request $request): JsonResponse
    {
        $today = Carbon::today()->toDateString();
        $signalType = $request->input('signal_type', 'BUY_CE');
        $instrument = $request->input('instrument', 'NIFTY');

        // Deduplicate: If same signal & instrument was logged in the last 45 seconds, return existing
        $recent = OrderFlowSignalLog::where('trade_date', $today)
            ->where('signal_type', $signalType)
            ->where('instrument', $instrument)
            ->where('created_at', '>=', Carbon::now()->subSeconds(45))
            ->first();

        if ($recent) {
            return response()->json([
                'success' => true,
                'message' => 'Signal already logged recently',
                'data'    => $recent,
            ]);
        }

        $log = OrderFlowSignalLog::create([
            'mode'             => $request->input('mode', 'live'),
            'trade_date'       => $today,
            'signal_time'      => Carbon::now()->format('H:i:s'),
            'symbol'           => $request->input('symbol', 'NIFTY'),
            'signal_type'      => $signalType,
            'action'           => $request->input('action', 'BUY CALL'),
            'instrument'       => $instrument,
            'spot_price'       => (float) $request->input('spot_price', 0),
            'future_price'     => (float) $request->input('future_price', 0),
            'entry_price'      => (float) $request->input('entry_price', 0),
            'stop_loss'        => (float) $request->input('stop_loss', 0),
            'target_price'     => (float) $request->input('target_price', 0),
            'opt_strike'       => $request->input('opt_strike') ? (int) $request->input('opt_strike') : null,
            'opt_type'         => $request->input('opt_type'),
            'opt_entry'        => $request->input('opt_entry') ? (float) $request->input('opt_entry') : null,
            'opt_sl'           => $request->input('opt_sl') ? (float) $request->input('opt_sl') : null,
            'opt_target'       => $request->input('opt_target') ? (float) $request->input('opt_target') : null,
            'confluence_score' => (int) $request->input('confluence_score', 50),
            'obi_pct'          => (float) $request->input('obi_pct', 0),
            'micro_drift'      => (float) $request->input('micro_drift', 0),
            'trigger_reason'   => $request->input('trigger_reason'),
            'status'           => 'ACTIVE',
        ]);

        // Optional: Auto-dispatch to 0Click.in Trade Webhook if enabled by user
        $webhookResult = null;
        $statusMsg = null;
        if ($request->boolean('dispatch_webhook') && $request->filled('webhook_url')) {
            $maxDailyTrades = (int) $request->input('max_daily_trades', 0);
            $todayDispatchedCount = 0;
            if ($maxDailyTrades > 0) {
                $todayDispatchedCount = OrderFlowSignalLog::where('symbol', $request->input('symbol', 'NIFTY'))
                    ->where('mode', $request->input('mode', 'live'))
                    ->whereDate('created_at', Carbon::today('Asia/Kolkata'))
                    ->where('id', '!=', $log->id)
                    ->where(function($q) {
                        $q->where('notes', 'like', '%0Click: SENT%');
                    })
                    ->count();
            }

            if ($maxDailyTrades > 0 && $todayDispatchedCount >= $maxDailyTrades) {
                $statusMsg = '0Click: CAP REACHED (' . $todayDispatchedCount . '/' . $maxDailyTrades . ')';
                $webhookResult = ['success' => false, 'status_code' => 429, 'message' => 'Daily trade execution cap reached'];
            } else {
                $webhookResult = $this->dispatchZeroClickWebhook($log, $request);
                if ($webhookResult) {
                    $statusMsg = $webhookResult['success'] 
                        ? '0Click: SENT (' . ($webhookResult['status_code'] ?? 200) . ')' 
                        : '0Click: FAILED (' . ($webhookResult['status_code'] ?? 500) . ')';
                }
            }
        }

        // Store comprehensive engine factors breakdown and webhook audit in notes
        $factors = $request->input('factors');
        $tradeStyle = $request->input('trade_style', 'OPTION_SELLING');
        $notesPayload = [
            'webhook'     => $statusMsg,
            'trade_style' => $tradeStyle,
            'factors'     => $factors,
            'logged_at'   => Carbon::now('Asia/Kolkata')->toDateTimeString(),
        ];
        $log->update(['notes' => json_encode($notesPayload)]);

        return response()->json([
            'success'        => true,
            'data'           => $log->fresh(),
            'webhook_result' => $webhookResult,
        ]);
    }

    /**
     * Dispatch trade order execution payload to 0Click.in Webhook contract
     */
    protected function dispatchZeroClickWebhook(OrderFlowSignalLog $log, Request $request): array
    {
        $startTime = microtime(true);
        $url = $request->input('webhook_url', 'https://0click.in/api/webhook/tradingview');
        $token = $request->input('webhook_token') ?: env('ZEROCLICK_WEBHOOK_TOKEN');
        $mode = strtoupper($request->input('execution_mode', 'SIMULATION'));
        $lots = max(1, (int) $request->input('lots', 1));
        $tradeStyle = $request->input('trade_style', 'OPTION_SELLING'); // 'OPTION_SELLING' or 'OPTION_BUYING'

        $isBull = (str_contains($log->signal_type, 'BUY') || str_contains($log->action, 'CALL') || str_contains($log->action, 'BUY'));
        $marketSignal = $isBull ? 'BUY' : 'SELL';

        // 0Click Standard Contract v1.0:
        // Option Selling: Bullish Market -> SELL ITM PE; Bearish Market -> SELL ITM CE
        // Option Buying: Bullish Market -> BUY ATM CE; Bearish Market -> BUY ATM PE
        if ($tradeStyle === 'OPTION_BUYING') {
            $instrument = $isBull ? 'CE' : 'PE';
            $orderAction = 'BUY';
        } else {
            $instrument = $isBull ? 'PE' : 'CE';
            $orderAction = 'SELL';
        }

        $strike = $log->opt_strike ?: (int)round($log->spot_price / 100.0) * 100;
        $strikeName = "{$log->symbol} {$strike} {$instrument}";

        $payload = [
            'category'       => 'ORDER_FLOW_DEPTH',
            'signal'         => $marketSignal,
            'action'         => $orderAction,
            'trade_type'     => $orderAction,
            'symbol'         => $log->symbol,
            'instrument'     => $instrument,
            'option_type'    => $instrument,
            'strike'         => $strike,
            'strike_name'    => $strikeName,
            'trading_symbol' => $strikeName,
            'price'          => $log->spot_price ?: $log->entry_price,
            'sl'             => $log->stop_loss,
            'tp'             => $log->target_price,
            'lots'           => $lots,
            'mode'           => $mode,
            'product'        => 'MARGIN',
        ];

        if (!empty($token)) {
            $payload['secret'] = $token;
        }

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

            $response = Http::timeout(10)->withHeaders($headers)->post($url, $payload);
            $latencyMs = round((microtime(true) - $startTime) * 1000, 2);
            $statusCode = $response->status();
            $body = $response->json() ?: ['raw' => $response->body()];
            $isSuccess = $response->successful();

            // Record in zero_click_alert_logs for cross-monitor audit compliance
            if (Schema::hasTable('zero_click_alert_logs')) {
                try {
                    ZeroClickAlertLog::create([
                        'triggered_at'       => Carbon::now('Asia/Kolkata'),
                        'trade_date'         => $log->trade_date,
                        'candle_time'        => Carbon::now('Asia/Kolkata')->format('H:i'),
                        'signal'             => $marketSignal,
                        'action'             => $orderAction,
                        'symbol'             => $log->symbol,
                        'instrument'         => $instrument,
                        'strike'             => $strike,
                        'spot_price'         => $log->spot_price,
                        'sl_price'           => $log->stop_loss,
                        'tp_price'           => $log->target_price,
                        'sl_offset_pts'      => (int)abs($log->spot_price - $log->stop_loss),
                        'tp_offset_pts'      => (int)abs($log->target_price - $log->spot_price),
                        'lots'               => $lots,
                        'mode'               => $mode,
                        'product'            => 'MARGIN',
                        'category'           => 'ORDER_FLOW_DEPTH',
                        'breadth_pct'        => $log->confluence_score,
                        'breadth_weight_pct' => $log->confluence_score,
                        'breadth_count'      => 0,
                        'net_points'         => $log->micro_drift,
                        'top_contributor'    => $log->trigger_reason,
                        'payload'            => $payload,
                        'status'             => $isSuccess ? 'SENT' : 'FAILED',
                        'endpoint_url'       => $url,
                        'api_status_code'    => $statusCode,
                        'api_response'       => $body,
                        'latency_ms'         => $latencyMs,
                        'dispatched_at'      => Carbon::now('Asia/Kolkata'),
                    ]);
                } catch (\Throwable $e) {}
            }

            return [
                'success'     => $isSuccess,
                'status_code' => $statusCode,
                'latency_ms'  => $latencyMs,
                'response'    => $body,
                'payload'     => $payload,
            ];
        } catch (\Throwable $e) {
            $latencyMs = round((microtime(true) - $startTime) * 1000, 2);
            return [
                'success'     => false,
                'status_code' => 500,
                'latency_ms'  => $latencyMs,
                'error'       => $e->getMessage(),
                'payload'     => $payload,
            ];
        }
    }

    /**
     * Test / Preview 0Click Webhook Dispatch from Settings Modal
     */
    public function testWebhook(Request $request): JsonResponse
    {
        $url = $request->input('webhook_url', 'https://0click.in/api/webhook/tradingview');
        $token = $request->input('webhook_token') ?: env('ZEROCLICK_WEBHOOK_TOKEN');
        $signal = strtoupper($request->input('signal', 'BUY'));
        $mode = strtoupper($request->input('mode', 'SIMULATION'));
        $lots = max(1, (int) $request->input('lots', 1));
        $tradeStyle = $request->input('trade_style', 'OPTION_SELLING');
        $price = (float) $request->input('price', 23300.0);
        $sl = (float) $request->input('sl', $signal === 'BUY' ? $price - 25 : $price + 25);
        $tp = (float) $request->input('tp', $signal === 'BUY' ? $price + 60 : $price - 60);

        $isBull = ($signal === 'BUY');
        if ($tradeStyle === 'OPTION_BUYING') {
            $instrument = $isBull ? 'CE' : 'PE';
            $orderAction = 'BUY';
        } else {
            $instrument = $isBull ? 'PE' : 'CE';
            $orderAction = 'SELL';
        }

        $strike = ((int)floor($price / 100.0) + ($isBull ? 1 : -1)) * 100;
        $strikeName = "NIFTY {$strike} {$instrument}";

        $payload = [
            'category'       => 'ORDER_FLOW_TEST',
            'signal'         => $signal,
            'action'         => $orderAction,
            'trade_type'     => $orderAction,
            'symbol'         => 'NIFTY',
            'instrument'     => $instrument,
            'option_type'    => $instrument,
            'strike'         => $strike,
            'strike_name'    => $strikeName,
            'trading_symbol' => $strikeName,
            'price'          => $price,
            'sl'             => $sl,
            'tp'             => $tp,
            'lots'           => $lots,
            'mode'           => $mode,
            'product'        => 'MARGIN',
        ];

        if (!empty($token)) {
            $payload['secret'] = $token;
        }

        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];

        if (!empty($token)) {
            $headers['X-Webhook-Token'] = $token;
            $headers['X-API-KEY']       = $token;
            $headers['Authorization']   = 'Bearer ' . $token;
        }

        $startTime = microtime(true);
        try {
            $response = Http::timeout(10)->withHeaders($headers)->post($url, $payload);
            $latencyMs = round((microtime(true) - $startTime) * 1000, 2);

            return response()->json([
                'success'     => $response->successful(),
                'status_code' => $response->status(),
                'latency_ms'  => $latencyMs,
                'response'    => $response->json() ?: ['raw' => $response->body()],
                'payload'     => $payload,
            ]);
        } catch (\Throwable $e) {
            $latencyMs = round((microtime(true) - $startTime) * 1000, 2);
            return response()->json([
                'success'     => false,
                'status_code' => 500,
                'latency_ms'  => $latencyMs,
                'error'       => $e->getMessage(),
                'payload'     => $payload,
            ]);
        }
    }

    /**
     * Update an active trade call's outcome (e.g. Target Hit, Stopped Out, Max MFE/MAE).
     */
    public function updateSignalOutcome(Request $request): JsonResponse
    {
        $id = $request->input('id');
        $log = OrderFlowSignalLog::find($id);

        if (!$log) {
            return response()->json(['success' => false, 'error' => 'Log not found'], 404);
        }

        $status = $request->input('status', $log->status);
        $pnl = (float) $request->input('pnl_points', $log->pnl_points);
        $mfe = (float) $request->input('max_favorable_pts', $log->max_favorable_pts);
        $mae = (float) $request->input('max_adverse_pts', $log->max_adverse_pts);

        $log->update([
            'status'            => $status,
            'exit_time'         => $status !== 'ACTIVE' ? ($log->exit_time ?? Carbon::now()->format('H:i:s')) : null,
            'exit_price'        => $request->filled('exit_price') ? (float) $request->input('exit_price') : $log->exit_price,
            'pnl_points'        => $pnl,
            'max_favorable_pts' => max($log->max_favorable_pts, $mfe),
            'max_adverse_pts'   => min($log->max_adverse_pts, $mae),
            'notes'             => $request->input('notes', $log->notes),
        ]);

        return response()->json(['success' => true, 'data' => $log]);
    }

    /**
     * Fetch all logged signals for today and summary scorecard.
     */
    public function getSignalLogs(Request $request): JsonResponse
    {
        $date = $request->input('date', Carbon::today()->toDateString());
        $mode = $request->input('mode');

        $query = OrderFlowSignalLog::where('trade_date', $date)
            ->orderByDesc('id');

        if ($mode) {
            $query->where('mode', $mode);
        }

        $logs = $query->limit(50)->get();

        $total = $logs->count();
        $targetHit = $logs->where('status', 'TARGET_HIT')->count();
        $stoppedOut = $logs->where('status', 'STOPPED_OUT')->count();
        $active = $logs->where('status', 'ACTIVE')->count();
        $netPnl = (float) $logs->sum('pnl_points');
        $decided = $targetHit + $stoppedOut;
        $winRate = $decided > 0 ? round(($targetHit / $decided) * 100) : 0;

        return response()->json([
            'success' => true,
            'date'    => $date,
            'summary' => [
                'total_signals' => $total,
                'target_hit'    => $targetHit,
                'stopped_out'   => $stoppedOut,
                'active'        => $active,
                'win_rate'      => $winRate,
                'net_pnl_pts'   => round($netPnl, 2),
            ],
            'logs'    => $logs,
        ]);
    }

    /**
     * Helper to resolve contracts (Future, ATM CE, ATM PE, and adjacent strikes).
     */
    protected function resolveAvailableContracts(string $symbol, ?string $expiry, int $atmStrike, int $step): array
    {
        $contracts = [
            'future'   => null,
            'atm_ce'   => null,
            'atm_pe'   => null,
            'strikes'  => [],
        ];

        if (!Schema::hasTable('instruments')) {
            return $contracts;
        }

        // 1. Futures
        $futInstrument = DB::table('instruments')
            ->where('name', $symbol)
            ->where('instrument_type', 'FUT')
            ->orderBy('expiry')
            ->first();

        if ($futInstrument) {
            $contracts['future'] = [
                'instrument_key' => $futInstrument->instrument_key,
                'trading_symbol' => $futInstrument->trading_symbol,
                'lot_size'       => $futInstrument->lot_size ?? 75,
                'type'           => 'FUT',
                'strike'         => 0,
            ];
        }

        // 2. Options Range around ATM (+/- 3 strikes)
        $targetStrikes = [];
        for ($i = -3; $i <= 3; $i++) {
            $targetStrikes[] = $atmStrike + ($i * $step);
        }

        $optionsQuery = DB::table('instruments')
            ->where('name', $symbol)
            ->whereIn('strike_price', $targetStrikes)
            ->whereIn('instrument_type', ['CE', 'PE']);

        if ($expiry) {
            $optionsQuery->where('expiry', $expiry);
        }

        $options = $optionsQuery->get();

        foreach ($options as $opt) {
            $strike = (int) $opt->strike_price;
            $type   = strtoupper($opt->instrument_type);

            $item = [
                'instrument_key' => $opt->instrument_key,
                'trading_symbol' => $opt->trading_symbol,
                'lot_size'       => $opt->lot_size ?? 75,
                'type'           => $type,
                'strike'         => $strike,
            ];

            if ($strike === $atmStrike) {
                if ($type === 'CE') $contracts['atm_ce'] = $item;
                if ($type === 'PE') $contracts['atm_pe'] = $item;
            }

            $contracts['strikes'][$strike][$type] = $item;
        }

        return $contracts;
    }

    /**
     * Compute multi-strategy signals (Quant Engine, Trending OI, Breadth, Breakouts).
     */
    protected function computeStrategyConfluenceSignals(
        string $symbol,
        string $date,
        ?string $expiry,
        int $atm,
        float $spot
    ): array {
        $cacheKey = "order_flow_signals_{$symbol}_{$date}_{$expiry}_{$atm}";
        return Cache::remember($cacheKey, 5, function () use ($symbol, $date, $expiry, $atm, $spot) {
            $signals = [
                'quant_engine' => [
                    'name'        => 'Quant Statistical Prob Engine',
                    'signal'      => 'NEUTRAL',
                    'score'       => 50,
                    'weight'      => 25,
                    'details'     => 'No high-impact skew detected',
                    'pop_pct'     => 70.0,
                    'expected_move' => 120.0,
                ],
                'oi_buildup' => [
                    'name'        => 'OI Buildup & Trending Flow',
                    'signal'      => 'NEUTRAL',
                    'score'       => 50,
                    'weight'      => 25,
                    'details'     => 'Balanced Call and Put open interest',
                    'pcr'         => 1.0,
                    'net_flow'    => 'Neutral',
                ],
                'market_breadth' => [
                    'name'        => 'Heavyweight Market Breadth',
                    'signal'      => 'NEUTRAL',
                    'score'       => 50,
                    'weight'      => 20,
                    'details'     => 'Heavyweights showing mixed divergence',
                    'advances'    => 25,
                    'declines'    => 25,
                ],
                'price_action' => [
                    'name'        => 'Price Action & Breakout Levels',
                    'signal'      => 'NEUTRAL',
                    'score'       => 50,
                    'weight'      => 15,
                    'details'     => 'Trading near pivot equilibrium',
                    'pivot'       => $atm,
                    'r1'          => $atm + 100,
                    's1'          => $atm - 100,
                ],
                'depth_orderflow' => [
                    'name'        => '30-Depth Live Microstructure',
                    'signal'      => 'WAITING_STREAM',
                    'score'       => 50,
                    'weight'      => 15,
                    'details'     => 'Awaiting live Upstox WebSocket feed updates',
                ],
                'unified_macro' => [
                    'has_call'    => false,
                    'signal'      => 'NEUTRAL',
                    'candle_time' => null,
                ],
            ];

            // 1. Live Options Chain PCR & Total Open Interest
            $livePcr = 1.0;
            $putOi = 0;
            $callOi = 0;
            $hasLiveChain = false;
            try {
                if (Schema::hasTable('option_chains')) {
                    $pcrRow = DB::table('option_chains')
                        ->where('trading_symbol', 'like', "{$symbol}%")
                        ->where('created_at', '>=', Carbon::now('Asia/Kolkata')->subMinutes(30))
                        ->selectRaw('SUM(CASE WHEN option_type = "PE" THEN oi ELSE 0 END) as put_oi, SUM(CASE WHEN option_type = "CE" THEN oi ELSE 0 END) as call_oi')
                        ->first();
                    if ($pcrRow && $pcrRow->call_oi > 0) {
                        $putOi = (int)$pcrRow->put_oi;
                        $callOi = (int)$pcrRow->call_oi;
                        $livePcr = round($putOi / $callOi, 2);
                        $hasLiveChain = true;
                    }
                }
            } catch (\Throwable $e) {}

            // 2. Primary Macro Driver: Unified Monitor (Zero-Click Alert Engine)
            $latestAlert = null;
            if (Schema::hasTable('zero_click_alert_logs')) {
                $latestAlert = DB::table('zero_click_alert_logs')
                    ->where('symbol', $symbol)
                    ->where('trade_date', $date)
                    ->orderByDesc('id')
                    ->first();
            }

            if ($latestAlert) {
                $isSell = ($latestAlert->signal === 'SELL');
                $candleTime = $latestAlert->candle_time;
                $strike = (int)$latestAlert->strike;
                $inst = $latestAlert->instrument;
                $netPts = (float)$latestAlert->net_points;
                $breadthWeight = (float)$latestAlert->breadth_weight_pct;
                $breadthCount = (int)$latestAlert->breadth_count;

                $signals['unified_macro'] = [
                    'has_call'       => true,
                    'signal'         => $latestAlert->signal,
                    'action'         => $latestAlert->action,
                    'candle_time'    => $candleTime,
                    'strike'         => $strike,
                    'instrument'     => $inst,
                    'spot_price'     => (float)$latestAlert->spot_price,
                    'sl_price'       => (float)$latestAlert->sl_price,
                    'tp_price'       => (float)$latestAlert->tp_price,
                    'breadth_weight' => $breadthWeight,
                    'breadth_count'  => $breadthCount,
                    'net_points'     => $netPts,
                ];

                // Dynamically calculated probability of profit (POP) based on net points drag and breadth magnitude
                $popPct = min(94.0, max(58.0, round(50.0 + (abs($netPts) * 0.45) + (abs($breadthWeight - 50.0) * 0.5), 1)));
                $expectedMove = round(max(40.0, abs((float)$latestAlert->tp_price - (float)$latestAlert->spot_price)), 1);

                if ($isSell) {
                    $bScore = max(8, min(30, round(100 - $breadthWeight)));
                    $signals['market_breadth'] = [
                        'name'     => 'Unified Stock Weightage Breadth',
                        'signal'   => 'BEARISH',
                        'score'    => $bScore,
                        'weight'   => 25,
                        'details'  => "Bears Leading: {$breadthWeight}% Red Weight ({$breadthCount} stocks down, {$netPts} pts at {$candleTime})",
                        'advances' => max(0, 50 - $breadthCount),
                        'declines' => $breadthCount,
                    ];

                    $quantScore = max(6, min(32, round(100 - $popPct)));
                    $signals['quant_engine'] = [
                        'name'          => 'Quant Statistical Prob Engine',
                        'signal'        => 'BEARISH',
                        'score'         => $quantScore,
                        'weight'        => 25,
                        'details'       => "5m Breakdown at {$candleTime} (Net Drag {$netPts} pts | POP {$popPct}%)",
                        'pop_pct'       => $popPct,
                        'expected_move' => $expectedMove,
                    ];

                    $oiScore = min(38, max(10, round(50 - (abs($netPts) * 0.75) - (($breadthWeight - 50) * 0.3))));
                    $signals['oi_buildup'] = [
                        'name'     => 'OI Buildup & Trending Flow',
                        'signal'   => 'BEARISH',
                        'score'    => $oiScore,
                        'weight'   => 20,
                        'details'  => "Call Writing Dominance at {$strike} CE (Live PCR: {$livePcr})",
                        'pcr'      => $livePcr,
                        'net_flow' => "Downside Flow at {$strike} CE",
                    ];

                    $signals['price_action'] = [
                        'name'     => 'Price Action & Breakout Levels',
                        'signal'   => 'BEARISH',
                        'score'    => max(10, min(35, round(50 - abs($netPts)))),
                        'weight'   => 15,
                        'details'  => "Breakdown below {$latestAlert->spot_price} | TGT: {$latestAlert->tp_price} | SL: {$latestAlert->sl_price}",
                        'pivot'    => (int)$strike,
                        'r1'       => (float)$latestAlert->sl_price,
                        's1'       => (float)$latestAlert->tp_price,
                    ];
                } else {
                    $bScore = min(92, max(70, round($breadthWeight)));
                    $signals['market_breadth'] = [
                        'name'     => 'Unified Stock Weightage Breadth',
                        'signal'   => 'BULLISH',
                        'score'    => $bScore,
                        'weight'   => 25,
                        'details'  => "Bulls Leading: {$breadthWeight}% Green Weight ({$breadthCount} stocks up, +{$netPts} pts at {$candleTime})",
                        'advances' => $breadthCount,
                        'declines' => max(0, 50 - $breadthCount),
                    ];

                    $quantScore = min(94, max(68, round($popPct)));
                    $signals['quant_engine'] = [
                        'name'          => 'Quant Statistical Prob Engine',
                        'signal'        => 'BULLISH',
                        'score'         => $quantScore,
                        'weight'        => 25,
                        'details'       => "5m Breakout at {$candleTime} (Net Push +{$netPts} pts | POP {$popPct}%)",
                        'pop_pct'       => $popPct,
                        'expected_move' => $expectedMove,
                    ];

                    $oiScore = min(90, max(62, round(50 + ($netPts * 0.75) + (($breadthWeight - 50) * 0.3))));
                    $signals['oi_buildup'] = [
                        'name'     => 'OI Buildup & Trending Flow',
                        'signal'   => 'BULLISH',
                        'score'    => $oiScore,
                        'weight'   => 20,
                        'details'  => "Put Writing Dominance at {$strike} PE (Live PCR: {$livePcr})",
                        'pcr'      => $livePcr,
                        'net_flow' => "Upside Support at {$strike} PE",
                    ];

                    $signals['price_action'] = [
                        'name'     => 'Price Action & Breakout Levels',
                        'signal'   => 'BULLISH',
                        'score'    => min(90, max(65, round(50 + $netPts))),
                        'weight'   => 15,
                        'details'  => "Breakout above {$latestAlert->spot_price} | TGT: {$latestAlert->tp_price} | SL: {$latestAlert->sl_price}",
                        'pivot'    => (int)$strike,
                        'r1'       => (float)$latestAlert->tp_price,
                        's1'       => (float)$latestAlert->sl_price,
                    ];
                }
            } else {
                // Fallback: Dynamically evaluate Live PCR & trend when no 5m zero-click alert has triggered
                $confluence = min(88, max(15, (int) round(50 + ($livePcr - 1.0) * 80)));
                $quantSignal = $confluence >= 60 ? 'BULLISH' : ($confluence <= 40 ? 'BEARISH' : 'NEUTRAL');
                $expectedMove = round($spot * 0.0075, 1);
                $pop = $confluence >= 65 || $confluence <= 35 ? 76.0 : 54.0;

                $signals['quant_engine'] = [
                    'name'          => 'Quant Statistical Prob Engine',
                    'signal'        => $quantSignal,
                    'score'         => $confluence,
                    'weight'        => 25,
                    'details'       => "POP {$pop}% | Expected Range ±{$expectedMove} pts",
                    'pop_pct'       => $pop,
                    'expected_move' => $expectedMove,
                ];

                $signals['oi_buildup'] = [
                    'name'     => 'OI Buildup & Trending Flow',
                    'signal'   => $quantSignal,
                    'score'    => $confluence,
                    'weight'   => 25,
                    'details'  => "Live Market PCR: {$livePcr}",
                    'pcr'      => $livePcr,
                    'net_flow' => $quantSignal,
                ];

                if ($hasLiveChain) {
                    $signals['market_breadth'] = [
                        'name'     => 'Market Flow Direction',
                        'signal'   => $quantSignal,
                        'score'    => $confluence,
                        'weight'   => 20,
                        'details'  => "Options Chain Bias: {$quantSignal} (PCR: {$livePcr})",
                        'advances' => $confluence >= 50 ? 30 : 20,
                        'declines' => $confluence >= 50 ? 20 : 30,
                    ];
                }
            }

        // 4. Calculate Aggregate Confluence Score
        $totalWeight = 0;
        $weightedScore = 0;

        foreach ($signals as $key => $sig) {
            if ($key === 'depth_orderflow' || !isset($sig['weight'], $sig['score'])) continue;
            $w = $sig['weight'];
            $s = $sig['score'];
            $weightedScore += ($s * $w);
            $totalWeight += $w;
        }

        $staticConfluence = $totalWeight > 0 ? round($weightedScore / $totalWeight) : 50;

        $overallSignal = 'NEUTRAL';
        if ($staticConfluence >= 70) {
            $overallSignal = 'BULLISH';
        } elseif ($staticConfluence <= 35) {
            $overallSignal = 'BEARISH';
        }

            return [
                'factors'           => $signals,
                'static_confluence' => $staticConfluence,
                'overall_signal'    => $overallSignal,
                'timestamp'         => Carbon::now('Asia/Kolkata')->format('h:i:s A'),
            ];
        });
    }
}
