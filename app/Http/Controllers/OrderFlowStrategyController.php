<?php

namespace App\Http\Controllers;

use App\Models\OrderFlowAlertLog;
use App\Models\OrderFlowSignalLog;
use App\Models\OrderFlowTradedVolume;
use App\Models\ZeroClickAlertLog;
use App\Services\OrderFlow\OrderFlowCumulativeReportService;
use App\Services\Quant\HighProbabilityEngine;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class OrderFlowStrategyController extends Controller
{
    protected HighProbabilityEngine $quantEngine;
    protected OrderFlowCumulativeReportService $reportService;

    public function __construct(HighProbabilityEngine $quantEngine, OrderFlowCumulativeReportService $reportService)
    {
        $this->quantEngine = $quantEngine;
        $this->reportService = $reportService;
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

        // 7. Highest Traded Ask & Bid Quantities (From 09:15 onwards, stored in order_flow_traded_volumes)
        $sessionAskRecords = [];
        $sessionBidRecords = [];

        // Primary source: Dedicated Traded Volumes Table
        if (Schema::hasTable('order_flow_traded_volumes')) {
            $tvRecords = OrderFlowTradedVolume::forDate($workingDate)
                ->forSymbol($symbol)
                ->orderBy('trade_time', 'asc')
                ->get(['trade_time', 'price', 'quantity', 'side']);

            foreach ($tvRecords as $r) {
                $item = [
                    'time'  => (string) $r->trade_time,
                    'price' => (float) $r->price,
                    'qty'   => (int) $r->quantity,
                    'side'  => (string) $r->side,
                ];
                if ($r->side === 'ASK') {
                    $sessionAskRecords[] = $item;
                } else {
                    $sessionBidRecords[] = $item;
                }
            }
        }

        // Secondary / fallback source: OrderFlowAlertLog (for historical blocks and alerts)
        if (Schema::hasTable('order_flow_alert_logs')) {
            $allTodayAlerts = OrderFlowAlertLog::where('trade_date', $workingDate)
                ->where('symbol', $symbol)
                ->orderBy('alert_time', 'asc')
                ->get(['alert_time', 'price', 'quantity', 'side', 'alert_type', 'title', 'details']);

            $existingAskKeys = [];
            foreach ($sessionAskRecords as $a) {
                $existingAskKeys[substr($a['time'], 0, 5) . '_' . number_format($a['price'], 2)] = true;
            }
            $existingBidKeys = [];
            foreach ($sessionBidRecords as $b) {
                $existingBidKeys[substr($b['time'], 0, 5) . '_' . number_format($b['price'], 2)] = true;
            }

            foreach ($allTodayAlerts as $r) {
                $qty = (int) $r->quantity;
                if ($qty <= 0 && !empty($r->title) && preg_match('/(\d+[\d,]*)\s*Qty/i', $r->title, $m)) {
                    $qty = (int) str_replace(',', '', $m[1]);
                }
                if ($qty <= 0 && !empty($r->details) && preg_match('/(\d+[\d,]*)\s*Qty/i', $r->details, $m)) {
                    $qty = (int) str_replace(',', '', $m[1]);
                }
                if ($qty <= 0) continue;

                $timeStr = is_string($r->alert_time) ? $r->alert_time : ($r->alert_time ? $r->alert_time->format('H:i:s') : '--');
                $price = (float) $r->price;

                // Determine if ASK (Sell wall / Ask block) or BID (Buy wall / Bid block)
                $isAsk = in_array(strtoupper((string) $r->side), ['SELL', 'ASK']) 
                    || str_contains((string) $r->alert_type, 'SELL') 
                    || str_contains((string) $r->alert_type, 'ASK')
                    || str_contains(strtoupper((string) $r->title), 'ASK');

                $minKey = substr($timeStr, 0, 5) . '_' . number_format($price, 2);

                $item = [
                    'time'  => $timeStr,
                    'price' => $price,
                    'qty'   => $qty,
                    'side'  => $isAsk ? 'ASK' : 'BID',
                ];

                if ($isAsk) {
                    if (!isset($existingAskKeys[$minKey])) {
                        $sessionAskRecords[] = $item;
                        $existingAskKeys[$minKey] = true;
                    }
                } else {
                    if (!isset($existingBidKeys[$minKey])) {
                        $sessionBidRecords[] = $item;
                        $existingBidKeys[$minKey] = true;
                    }
                }
            }
        }

        // 8. Resolve Previous Trading Day and its Top 5 Ask and Bid Traded Volumes
        $prevDayRow = null;
        if (Schema::hasTable('nse_working_days')) {
            $prevDayRow = DB::table('nse_working_days')
                ->where('working_date', '<', $workingDate)
                ->orderByDesc('working_date')
                ->first();
        }
        $prevTradingDate = $prevDayRow ? $prevDayRow->working_date : Carbon::parse($workingDate)->subDay()->toDateString();

        $prevTopAsk = [];
        $prevTopBid = [];
        if (Schema::hasTable('order_flow_traded_volumes')) {
            $dedupTop = function($query, $limit = 5) {
                $raw = $query->orderByDesc('quantity')->take($limit * 4)->get();
                $seen = [];
                $res = [];
                foreach ($raw as $r) {
                    $key = number_format($r->price, 2);
                    if (!isset($seen[$key])) {
                        $seen[$key] = true;
                        $res[] = [
                            'time'  => (string) $r->trade_time,
                            'price' => (float) $r->price,
                            'qty'   => (int) $r->quantity,
                            'side'  => (string) $r->side,
                        ];
                        if (count($res) >= $limit) break;
                    }
                }
                return $res;
            };

            $prevTopAsk = $dedupTop(OrderFlowTradedVolume::forDate($prevTradingDate)->forSymbol($symbol)->ask());
            $prevTopBid = $dedupTop(OrderFlowTradedVolume::forDate($prevTradingDate)->forSymbol($symbol)->bid());
        }

        return view('order-flow-depth.index', [
            'symbol'            => $symbol,
            'workingDate'       => $workingDate,
            'prevTradingDate'   => $prevTradingDate,
            'prevTopAsk'        => $prevTopAsk,
            'prevTopBid'        => $prevTopBid,
            'expiries'          => $expiries,
            'selectedExpiry'    => $selectedExpiry,
            'indexKey'          => $indexKey,
            'spotPrice'         => $spotPrice,
            'openPrice'         => $openPrice,
            'atmStrike'         => $atmStrike,
            'strikeStep'        => $strikeStep,
            'contracts'         => $contracts,
            'strategySignals'   => $strategySignals,
            'sessionAlertsAsk'  => $sessionAskRecords,
            'sessionAlertsBid'  => $sessionBidRecords,
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
            $nowIst = Carbon::now('Asia/Kolkata');
            $currentTime = $nowIst->format('H:i');
            $enableTimeRestriction = $request->boolean('enable_time_restriction', true);
            $apiStartTime = $request->input('api_start_time', '09:30');

            if ($enableTimeRestriction && $currentTime < $apiStartTime) {
                $statusMsg = '0Click: TIME LOCKED (Before ' . $apiStartTime . ' IST)';
                $webhookResult = [
                    'success'     => false,
                    'status_code' => 403,
                    'message'     => 'API order execution locked before ' . $apiStartTime . ' IST (Opening market volatility guard)',
                ];
            } else {
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

        $protectionBasis = strtoupper(trim((string)$request->input('protection_basis', 'STRIKE')));
        if (!in_array($protectionBasis, ['STRIKE', 'NIFTY', 'BOTH', 'INDEX'], true)) {
            $protectionBasis = 'STRIKE';
        }
        if ($protectionBasis === 'INDEX') {
            $protectionBasis = 'NIFTY';
        }

        $slPts = max(1, (float)$request->input('sl_pts', 20));
        $tpPts = max(1, (float)$request->input('tp_pts', 20));

        $spotPrice = (float)($log->spot_price ?: $log->entry_price ?: 23600.0);
        $optEntry = (float)($log->opt_entry ?: 135.0);

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

        $strike = $log->opt_strike ?: (int)round($spotPrice / 100.0) * 100;
        $strikeName = "{$log->symbol} {$strike} {$instrument}";

        // Calculate Strike SL & TP vs Nifty Spot SL & TP
        $posSlPrice = null;
        $posTgtPrice = null;
        $niftySl = null;
        $niftyTp = null;

        if ($protectionBasis === 'STRIKE' || $protectionBasis === 'BOTH') {
            // For Option Selling (SELL): premium rise is loss -> optEntry + slPts, decay is profit -> optEntry - tpPts
            // For Option Buying (BUY): premium drop is loss -> optEntry - slPts, rise is profit -> optEntry + tpPts
            $posSlPrice = ($orderAction === 'SELL')
                ? round($optEntry + $slPts, 2)
                : max(0.05, round($optEntry - $slPts, 2));

            $posTgtPrice = ($orderAction === 'SELL')
                ? max(0.05, round($optEntry - $tpPts, 2))
                : round($optEntry + $tpPts, 2);
        }

        if ($protectionBasis === 'NIFTY' || $protectionBasis === 'BOTH') {
            $niftySl = $isBull ? round($spotPrice - $slPts, 2) : round($spotPrice + $slPts, 2);
            $niftyTp = $isBull ? round($spotPrice + $tpPts, 2) : round($spotPrice - $tpPts, 2);
        }

        $payload = [
            'category'         => 'ORDER_FLOW_DEPTH',
            'signal'           => $marketSignal,
            'action'           => $orderAction,
            'trade_type'       => $orderAction,
            'symbol'           => $log->symbol,
            'instrument'       => $instrument,
            'option_type'      => $instrument,
            'strike'           => $strike,
            'strike_name'      => $strikeName,
            'trading_symbol'   => $strikeName,
            'price'            => $spotPrice,
            'opt_price'        => $optEntry,
            'protection_basis' => $protectionBasis,
            'sl_pts'           => $slPts,
            'tp_pts'           => $tpPts,
            'strike_sl'        => $posSlPrice,
            'strike_tp'        => $posTgtPrice,
            'sl'               => ($protectionBasis === 'STRIKE') ? $slPts : $niftySl,
            'tp'               => ($protectionBasis === 'STRIKE') ? $tpPts : $niftyTp,
            'nifty_sl'         => $niftySl,
            'nifty_tp'         => $niftyTp,
            'lots'             => $lots,
            'mode'             => $mode,
            'product'          => 'MARGIN',
        ];

        if ($request->filled('mtm_target') && (float)$request->input('mtm_target') > 0) {
            $payload['mtm_target'] = (float)$request->input('mtm_target');
        }
        if ($request->filled('mtm_loss') && (float)$request->input('mtm_loss') > 0) {
            $payload['mtm_loss'] = (float)$request->input('mtm_loss');
        } elseif ($request->boolean('auto_trailing_loss') && !empty($payload['mtm_target'])) {
            // If auto-trailing is active and loss wasn't specified, anchor initial loss limit to target value
            $payload['mtm_loss'] = (float)$payload['mtm_target'];
        }
        if ($request->boolean('auto_trailing_loss')) {
            $payload['auto_trailing_loss'] = true;
            $payload['trail_step'] = (float)$request->input('trail_step', 1000);
        }

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
     * Manually trigger and dispatch an existing Journal Setup to 0Click Webhook
     */
    public function dispatchLogToWebhook(int $id, Request $request): JsonResponse
    {
        $log = OrderFlowSignalLog::find($id);
        if (!$log) {
            return response()->json([
                'success' => false,
                'message' => 'Journal entry not found with ID #' . $id,
            ], 404);
        }

        $webhookResult = $this->dispatchZeroClickWebhook($log, $request);

        $statusMsg = $webhookResult['success'] 
            ? '0Click: SENT (' . ($webhookResult['status_code'] ?? 200) . ')' 
            : '0Click: FAILED (' . ($webhookResult['status_code'] ?? 500) . ')';

        // Parse existing notes and update with webhook status and timestamp
        $notes = [];
        if (!empty($log->notes)) {
            $decoded = json_decode($log->notes, true);
            $notes = is_array($decoded) ? $decoded : ['notes' => $log->notes];
        }

        $notes['webhook'] = $statusMsg;
        $notes['dispatched_at'] = Carbon::now('Asia/Kolkata')->toDateTimeString();
        $notes['dispatched_manually'] = true;

        if ($request->filled('trade_style')) {
            $notes['trade_style'] = $request->input('trade_style');
        }

        $log->update(['notes' => json_encode($notes)]);

        return response()->json([
            'success'        => $webhookResult['success'],
            'status_code'    => $webhookResult['status_code'] ?? ($webhookResult['success'] ? 200 : 500),
            'message'        => $statusMsg,
            'webhook_result' => $webhookResult,
            'data'           => $log->fresh(),
        ]);
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
        $optPrice = (float) $request->input('opt_price', 135.0);
        $protectionBasis = strtoupper(trim((string)$request->input('protection_basis', 'STRIKE')));
        if (!in_array($protectionBasis, ['STRIKE', 'NIFTY', 'BOTH', 'INDEX'], true)) {
            $protectionBasis = 'STRIKE';
        }
        if ($protectionBasis === 'INDEX') {
            $protectionBasis = 'NIFTY';
        }

        $slPts = max(1, (float)$request->input('sl_pts', 20));
        $tpPts = max(1, (float)$request->input('tp_pts', 20));

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

        $posSlPrice = null;
        $posTgtPrice = null;
        $niftySl = null;
        $niftyTp = null;

        if ($protectionBasis === 'STRIKE' || $protectionBasis === 'BOTH') {
            $posSlPrice = ($orderAction === 'SELL')
                ? round($optPrice + $slPts, 2)
                : max(0.05, round($optPrice - $slPts, 2));

            $posTgtPrice = ($orderAction === 'SELL')
                ? max(0.05, round($optPrice - $tpPts, 2))
                : round($optPrice + $tpPts, 2);
        }

        if ($protectionBasis === 'NIFTY' || $protectionBasis === 'BOTH') {
            $niftySl = $isBull ? round($price - $slPts, 2) : round($price + $slPts, 2);
            $niftyTp = $isBull ? round($price + $tpPts, 2) : round($price - $tpPts, 2);
        }

        $payload = [
            'category'         => 'ORDER_FLOW_TEST',
            'signal'           => $signal,
            'action'           => $orderAction,
            'trade_type'       => $orderAction,
            'symbol'           => 'NIFTY',
            'instrument'       => $instrument,
            'option_type'      => $instrument,
            'strike'           => $strike,
            'strike_name'      => $strikeName,
            'trading_symbol'   => $strikeName,
            'price'            => $price,
            'opt_price'        => $optPrice,
            'protection_basis' => $protectionBasis,
            'sl_pts'           => $slPts,
            'tp_pts'           => $tpPts,
            'strike_sl'        => $posSlPrice,
            'strike_tp'        => $posTgtPrice,
            'sl'               => ($protectionBasis === 'STRIKE') ? $slPts : $niftySl,
            'tp'               => ($protectionBasis === 'STRIKE') ? $tpPts : $niftyTp,
            'nifty_sl'         => $niftySl,
            'nifty_tp'         => $niftyTp,
            'lots'             => $lots,
            'mode'             => $mode,
            'product'          => 'MARGIN',
        ];

        if ($request->filled('mtm_target') && (float)$request->input('mtm_target') > 0) {
            $payload['mtm_target'] = (float)$request->input('mtm_target');
        }
        if ($request->filled('mtm_loss') && (float)$request->input('mtm_loss') > 0) {
            $payload['mtm_loss'] = (float)$request->input('mtm_loss');
        } elseif ($request->boolean('auto_trailing_loss') && !empty($payload['mtm_target'])) {
            // If auto-trailing is active and loss wasn't specified, anchor initial loss limit to target value
            $payload['mtm_loss'] = (float)$payload['mtm_target'];
        }
        if ($request->boolean('auto_trailing_loss')) {
            $payload['auto_trailing_loss'] = true;
            $payload['trail_step'] = (float)$request->input('trail_step', 1000);
        }

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
     * Store an Order Flow Alert of the day (Consecutive Stacks, Liquidity Blocks, Confluence Setups).
     */
    public function logAlert(Request $request): JsonResponse
    {
        $today = Carbon::today('Asia/Kolkata')->toDateString();
        $alertType = (string) $request->input('alert_type', 'STACK_BUY');
        $side = strtoupper((string) $request->input('side', 'BUY'));
        $price = (float) $request->input('price', 0);
        $symbol = (string) $request->input('symbol', 'NIFTY');

        // Deduplication: prevent duplicate alert insertion within 30 seconds for same type & side & price vicinity
        $recent = OrderFlowAlertLog::where('trade_date', $today)
            ->where('symbol', $symbol)
            ->where('alert_type', $alertType)
            ->where('side', $side)
            ->where('created_at', '>=', Carbon::now()->subSeconds(30))
            ->whereBetween('price', [max(0, $price - 1.5), $price + 1.5])
            ->first();

        if ($recent) {
            return response()->json([
                'success' => true,
                'message' => 'Alert already persisted recently',
                'data'    => $recent,
            ]);
        }

        $alert = OrderFlowAlertLog::create([
            'mode'             => $request->input('mode', 'live'),
            'trade_date'       => $today,
            'alert_time'       => Carbon::now('Asia/Kolkata')->format('H:i:s'),
            'symbol'           => $symbol,
            'instrument'       => $request->input('instrument'),
            'alert_type'       => $alertType,
            'side'             => $side,
            'price'            => $price,
            'quantity'         => $request->filled('quantity') ? (int) $request->input('quantity') : null,
            'level'            => $request->filled('level') ? (int) $request->input('level') : null,
            'confluence_score' => $request->filled('confluence_score') ? (int) $request->input('confluence_score') : null,
            'title'            => (string) $request->input('title', 'Order Flow Alert'),
            'details'          => $request->input('details'),
            'guidance'         => $request->input('guidance'),
            'sound'            => $request->input('sound'),
            'meta'             => $request->input('meta'),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $alert,
        ]);
    }

    /**
     * Fetch all logged alerts for today or specific date.
     */
    public function getAlertLogs(Request $request): JsonResponse
    {
        $date = $request->input('date', Carbon::today('Asia/Kolkata')->toDateString());
        $mode = $request->input('mode');
        $symbol = $request->input('symbol', 'NIFTY');

        $query = OrderFlowAlertLog::where('trade_date', $date)
            ->where('symbol', $symbol)
            ->orderByDesc('id');

        if ($mode && $mode !== 'all') {
            $query->where('mode', $mode);
        }

        $alerts = $query->limit(300)->get();

        $total = $alerts->count();
        $buyCount = $alerts->where('side', 'BUY')->count();
        $sellCount = $alerts->where('side', 'SELL')->count();
        $stacksCount = $alerts->filter(fn($a) => str_contains($a->alert_type, 'STACK'))->count();
        $blocksCount = $alerts->filter(fn($a) => str_contains($a->alert_type, 'BLOCK'))->count();

        return response()->json([
            'success' => true,
            'date'    => $date,
            'summary' => [
                'total_alerts' => $total,
                'buy_count'    => $buyCount,
                'sell_count'   => $sellCount,
                'stacks_count' => $stacksCount,
                'blocks_count' => $blocksCount,
            ],
            'alerts'  => $alerts,
        ]);
    }

    /**
     * Persist batch of Highest Traded Volume Prints (both ASK and BID) to the database.
     */
    public function saveTradedVolume(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trade_date'     => 'required|date',
            'symbol'         => 'nullable|string|max:50',
            'trades'         => 'required|array',
            'trades.*.time'  => 'required|string',
            'trades.*.price' => 'required|numeric',
            'trades.*.qty'   => 'required|integer|min:1',
            'trades.*.side'  => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        $tradeDate = Carbon::parse($request->input('trade_date'))->toDateString();
        $symbol = strtoupper($request->input('symbol', 'NIFTY'));
        $trades = $request->input('trades', []);
        $instrument = $request->input('instrument');
        $source = $request->input('source', 'LIVE_TICK');

        $savedCount = 0;
        foreach ($trades as $t) {
            $rawSide = strtoupper(trim($t['side'] ?? 'ASK'));
            $side = in_array($rawSide, ['BID', 'SELL']) ? 'BID' : 'ASK';
            $price = round((float) $t['price'], 2);
            $qty = (int) $t['qty'];
            $time = trim((string) $t['time']);

            if ($qty < 500 || $price <= 0 || empty($time)) {
                continue;
            }

            // Format time as HH:mm:ss if HH:mm
            if (strlen($time) === 5) {
                $time .= ':00';
            }

            OrderFlowTradedVolume::updateOrInsert(
                [
                    'trade_date' => $tradeDate,
                    'symbol'     => $symbol,
                    'side'       => $side,
                    'trade_time' => $time,
                    'price'      => $price,
                ],
                [
                    'quantity'   => DB::raw("GREATEST(COALESCE(quantity, 0), {$qty})"),
                    'instrument' => $instrument,
                    'source'     => $source,
                    'updated_at' => now(),
                    'created_at' => DB::raw("COALESCE(created_at, NOW())"),
                ]
            );
            $savedCount++;
        }

        return response()->json([
            'success'     => true,
            'saved_count' => $savedCount,
            'message'     => "Successfully persisted {$savedCount} traded volume prints to database.",
        ]);
    }

    /**
     * Retrieve Top Traded Volume prints (both ASK and BID) for any given date & symbol.
     * Useful for backtesting and historical review.
     */
    public function getTopTradedVolume(Request $request): JsonResponse
    {
        $tradeDate = $request->filled('date')
            ? Carbon::parse($request->input('date'))->toDateString()
            : Carbon::today('Asia/Kolkata')->toDateString();

        $symbol = strtoupper($request->input('symbol', 'NIFTY'));
        $skipTime = $request->input('skip', '09:30'); // Skip opening minutes e.g. 09:30
        $limit = (int) $request->input('limit', 10);
        $minQty = (int) $request->input('min_qty', 500);

        $cutoff = strlen($skipTime) === 5 ? ($skipTime . ':59') : $skipTime;

        $baseQuery = OrderFlowTradedVolume::forDate($tradeDate)
            ->forSymbol($symbol)
            ->where('quantity', '>=', $minQty);

        if ($skipTime && $skipTime !== 'none' && $skipTime !== '09:15') {
            $baseQuery->where('trade_time', '>', $cutoff);
        }

        $askQuery = (clone $baseQuery)->ask()->orderByDesc('quantity')->take($limit * 3)->get();
        $bidQuery = (clone $baseQuery)->bid()->orderByDesc('quantity')->take($limit * 3)->get();

        // Deduplicate by same minute + price to get unique institutional levels
        $dedup = function ($collection) use ($limit) {
            $seen = [];
            $result = [];
            foreach ($collection as $item) {
                $minKey = substr($item->trade_time, 0, 5) . '_' . number_format($item->price, 2);
                if (!isset($seen[$minKey])) {
                    $seen[$minKey] = true;
                    $result[] = [
                        'time'  => $item->trade_time,
                        'price' => (float) $item->price,
                        'qty'   => (int) $item->quantity,
                        'side'  => $item->side,
                    ];
                    if (count($result) >= $limit) break;
                }
            }
            return $result;
        };

        $topAsk = $dedup($askQuery);
        $topBid = $dedup($bidQuery);

        return response()->json([
            'success'    => true,
            'date'       => $tradeDate,
            'symbol'     => $symbol,
            'skip_time'  => $skipTime,
            'top_ask'    => $topAsk,
            'top_bid'    => $topBid,
            'peak_ask'   => $topAsk[0] ?? null,
            'peak_bid'   => $topBid[0] ?? null,
            'total_ask_volume' => array_sum(array_column($topAsk, 'qty')),
            'total_bid_volume' => array_sum(array_column($topBid, 'qty')),
        ]);
    }

    /**
     * Enterprise Cumulative Report Dashboard (HTML View or JSON).
     */
    public function dailyReport(Request $request)
    {
        $symbol = $request->input('symbol', 'NIFTY');

        // Resolve working date
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
        $defaultDate = $workingDay ? $workingDay->working_date : Carbon::today('Asia/Kolkata')->format('Y-m-d');
        $date = $request->input('date', $defaultDate);
        $mode = $request->input('mode');

        $report = $this->reportService->generateReport($date, $symbol, $mode);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'report'  => $report,
            ]);
        }

        return view('order-flow-depth.report', [
            'report'      => $report,
            'symbol'      => $symbol,
            'workingDate' => $date,
        ]);
    }

    /**
     * JSON API Endpoint for Enterprise Cumulative Report.
     */
    public function getCumulativeReportApi(Request $request): JsonResponse
    {
        $symbol = $request->input('symbol', 'NIFTY');
        $date = $request->input('date', Carbon::today('Asia/Kolkata')->format('Y-m-d'));
        $mode = $request->input('mode');

        $report = $this->reportService->generateReport($date, $symbol, $mode);

        return response()->json([
            'success' => true,
            'report'  => $report,
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
                'market_breadth' => [
                    'name'            => 'NIFTY 50 Stocks Breadth',
                    'signal'          => 'NEUTRAL',
                    'score'           => 50,
                    'weight'          => 25,
                    'details'         => 'Heavyweights showing mixed divergence',
                    'advances'        => 25,
                    'declines'        => 25,
                    'green_weight'    => 50.0,
                    'red_weight'      => 50.0,
                    'green_count'     => 25,
                    'red_count'       => 25,
                    'green_count_pct' => 50.0,
                    'red_count_pct'   => 50.0,
                    'nifty_move_pts'  => 0.0,
                    'candle_time'     => null,
                ],
                'options_flow' => [
                    'name'          => 'NIFTY Options Flow Parity',
                    'signal'        => 'NEUTRAL',
                    'score'         => 50,
                    'weight'        => 25,
                    'details'       => 'Options Flow in equilibrium',
                    'bull_flow_pct' => 50.0,
                    'bear_flow_pct' => 50.0,
                    'net_points'    => 0.0,
                    'candle_time'   => null,
                ],
                'trending_oi' => [
                    'name'        => 'Trending OI & Sentiment',
                    'signal'      => 'NEUTRAL',
                    'score'       => 50,
                    'weight'      => 25,
                    'details'     => 'Balanced Call and Put open interest',
                    'call_oi'     => 0,
                    'put_oi'      => 0,
                    'diff_oi'     => 0,
                    'pcr'         => 1.0,
                    'sentiment'   => 0,
                    'candle_time' => null,
                ],
                'quant_engine' => [
                    'name'          => 'Quant Statistical Prob Engine',
                    'signal'        => 'NEUTRAL',
                    'score'         => 50,
                    'weight'        => 15,
                    'details'       => 'Equilibrium state',
                    'pop_pct'       => 50.0,
                    'expected_move' => 60.0,
                ],
                'oi_buildup' => [
                    'name'     => 'OI Buildup & PCR',
                    'signal'   => 'NEUTRAL',
                    'score'    => 50,
                    'weight'   => 10,
                    'details'  => 'Balanced Call and Put open interest',
                    'pcr'      => 1.0,
                    'net_flow' => 'Neutral',
                ],
                'price_action' => [
                    'name'        => 'Price Action & Breakout Levels',
                    'signal'      => 'NEUTRAL',
                    'score'       => 50,
                    'weight'      => 10,
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
                    'has_call'       => false,
                    'signal'         => 'NEUTRAL',
                    'action'         => null,
                    'candle_time'    => null,
                    'strike'         => $atm,
                    'instrument'     => 'CE',
                    'spot_price'     => $spot,
                    'sl_price'       => null,
                    'tp_price'       => null,
                    'breadth_weight' => 50.0,
                    'breadth_count'  => 25,
                    'net_points'     => 0.0,
                ],
                'anchor_trigger' => null,
            ];

            // ── 1. REAL PILLAR 1: NIFTY 50 STOCKS BREADTH (BOTTOM-UP CONSTITUENTS) ──
            $wBreadthLatest = null;
            $anchorTrigger = null;
            try {
                $wCtrl = app(NiftyWeightageMonitorController::class);
                $wRes = $wCtrl->getData(new Request(['date' => $date]));
                $wData = $wRes->getData(true);
                $wBreadth = $wData['breadth_by_time'] ?? [];

                // Scan all 5m candles today for Master Strategic Anchor (MUST be >= 80% in BOTH Weight AND Stocks)
                foreach ($wBreadth as $timeKey => $bInfo) {
                    $gw = (float)($bInfo['green_weight'] ?? 0);
                    $rw = (float)($bInfo['red_weight'] ?? 0);
                    $gcPct = (float)($bInfo['green_count_pct'] ?? 0);
                    $rcPct = (float)($bInfo['red_count_pct'] ?? 0);
                    $gc = (int)($bInfo['green_count'] ?? 0);
                    $rc = (int)($bInfo['red_count'] ?? 0);
                    $pts = (float)($bInfo['nifty_move_pts'] ?? 0);

                    if ($gw >= 80.0 && $gcPct >= 80.0) {
                        $anchorTrigger = [
                            'direction'   => 'BULLISH',
                            'signal'      => 'BUY',
                            'candle_time' => $timeKey,
                            'weight'      => $gw,
                            'count'       => $gc,
                            'count_pct'   => $gcPct,
                            'net_points'  => $pts,
                        ];
                    } elseif ($rw >= 80.0 && $rcPct >= 80.0) {
                        $anchorTrigger = [
                            'direction'   => 'BEARISH',
                            'signal'      => 'SELL',
                            'candle_time' => $timeKey,
                            'weight'      => $rw,
                            'count'       => $rc,
                            'count_pct'   => $rcPct,
                            'net_points'  => $pts,
                        ];
                    }
                }

                if (!empty($wBreadth)) {
                    $lastTimeKey = array_key_last($wBreadth);
                    $wBreadthLatest = $wBreadth[$lastTimeKey];
                    $wBreadthLatest['time_key'] = $lastTimeKey;
                }
            } catch (\Throwable $e) {}

            if ($wBreadthLatest) {
                $gw = (float)($wBreadthLatest['green_weight'] ?? 50);
                $rw = (float)($wBreadthLatest['red_weight'] ?? 50);
                $gc = (int)($wBreadthLatest['green_count'] ?? 25);
                $rc = (int)($wBreadthLatest['red_count'] ?? 25);
                $gcPct = (float)($wBreadthLatest['green_count_pct'] ?? 50);
                $rcPct = (float)($wBreadthLatest['red_count_pct'] ?? 50);
                $pts = (float)($wBreadthLatest['nifty_move_pts'] ?? 0);
                $cTime = $wBreadthLatest['time_key'] ?? 'live';

                // Condition 1: MUST reach >= 80% in BOTH Weight AND Stocks to be Bullish or Bearish!
                $breadthSignal = 'NEUTRAL';
                if ($gw >= 80.0 && $gcPct >= 80.0) {
                    $breadthSignal = 'BULLISH';
                } elseif ($rw >= 80.0 && $rcPct >= 80.0) {
                    $breadthSignal = 'BEARISH';
                }

                $signals['market_breadth'] = [
                    'name'            => 'NIFTY 50 Stocks Breadth',
                    'signal'          => $breadthSignal,
                    'score'           => round($gw),
                    'weight'          => 25,
                    'details'         => "{$gw}% Green ({$gc} stocks) vs {$rw}% Red ({$rc} stocks) at {$cTime}",
                    'advances'        => $gc,
                    'declines'        => $rc,
                    'green_weight'    => $gw,
                    'red_weight'      => $rw,
                    'green_count'     => $gc,
                    'red_count'       => $rc,
                    'green_count_pct' => $gcPct,
                    'red_count_pct'   => $rcPct,
                    'nifty_move_pts'  => $pts,
                    'candle_time'     => $cTime,
                ];
            }

            // ── 2. REAL PILLAR 2: NIFTY 50 OPTIONS FLOW PARITY ──
            $oBreadthLatest = null;
            try {
                $oCtrl = app(OptionsBreadthMonitorController::class);
                $oRes = $oCtrl->getData(new Request([
                    'symbol'      => 'NIFTY',
                    'date'        => $date,
                    'range'       => 5,
                    'expiry'      => $expiry,
                    'basket_mode' => 'dynamic'
                ]));
                $oData = $oRes->getData(true);
                $oBreadth = $oData['breadth_by_time'] ?? [];
                if (!empty($oBreadth)) {
                    $lastOTime = array_key_last($oBreadth);
                    $oBreadthLatest = $oBreadth[$lastOTime];
                    $oBreadthLatest['time_key'] = $lastOTime;
                }
            } catch (\Throwable $e) {}

            if ($oBreadthLatest) {
                $bullFlow = (float)($oBreadthLatest['up_weight'] ?? 50);
                $bearFlow = (float)($oBreadthLatest['down_weight'] ?? 50);
                $oPts = (float)($oBreadthLatest['net_points'] ?? 0);
                $oTime = $oBreadthLatest['time_key'] ?? 'live';

                // Condition 2: MUST reach >= 70% in Bull Flow or Bear Flow!
                $optSignal = 'NEUTRAL';
                if ($bullFlow >= 70.0) {
                    $optSignal = 'BULLISH';
                } elseif ($bearFlow >= 70.0) {
                    $optSignal = 'BEARISH';
                }

                $signals['options_flow'] = [
                    'name'          => 'NIFTY Options Flow Parity',
                    'signal'        => $optSignal,
                    'score'         => round($bullFlow),
                    'weight'        => 25,
                    'bull_flow_pct' => $bullFlow,
                    'bear_flow_pct' => $bearFlow,
                    'net_points'    => $oPts,
                    'candle_time'   => $oTime,
                    'details'       => "Options Flow: {$bullFlow}% Bull vs {$bearFlow}% Bear at {$oTime}",
                ];
            }

            // ── 3. REAL PILLAR 3: TRENDING OI & SENTIMENT (CE vs PE DIFFERENCE) ──
            try {
                $toiCtrl = app(TrendingOiController::class);
                $tRes = $toiCtrl->getData(new Request([
                    'mode'        => 'live',
                    'expiry'      => $expiry,
                    'date'        => $date,
                    'underlying'  => 'NSE_INDEX|Nifty 50',
                    'interval'    => 5,
                    'lookback'    => 'auto',
                    'skip_open'   => 0
                ]));
                $tData = $tRes->getData(true);
                $chart = $tData['chart'] ?? [];
                $labels = $chart['labels'] ?? [];
                $len = count($labels);
                if ($len > 0) {
                    $lastIdx = $len - 1;
                    $cOi = (float)($chart['call_oi'][$lastIdx] ?? 0);
                    $pOi = (float)($chart['put_oi'][$lastIdx] ?? 0);
                    $sent = (float)($chart['sentiment'][$lastIdx] ?? 0);
                    $diff = $pOi - $cOi; // Positive = Put Writing (Bullish); Negative = Call Writing (Bearish)
                    $toiPcr = $cOi > 0 ? round($pOi / $cOi, 2) : 1.0;
                    $toiTime = $labels[$lastIdx];

                    // Condition 3: MUST match the flow! (PE > CE and PCR >= 1.05 for Bull; CE > PE and PCR <= 0.95 for Bear)
                    $toiSignal = 'NEUTRAL';
                    if ($pOi > $cOi && $toiPcr >= 1.05 && $diff > 0) {
                        $toiSignal = 'BULLISH';
                    } elseif ($cOi > $pOi && $toiPcr <= 0.95 && $diff < 0) {
                        $toiSignal = 'BEARISH';
                    }

                    $diffL = round(abs($diff) / 100000, 1);
                    $diffSign = $diff >= 0 ? "+{$diffL}L PE" : "-{$diffL}L CE";

                    $signals['trending_oi'] = [
                        'name'        => 'Trending OI & Sentiment',
                        'signal'      => $toiSignal,
                        'score'       => $toiSignal === 'BULLISH' ? 75 : ($toiSignal === 'BEARISH' ? 25 : 50),
                        'weight'      => 25,
                        'call_oi'     => $cOi,
                        'put_oi'      => $pOi,
                        'diff_oi'     => $diff,
                        'pcr'         => $toiPcr,
                        'sentiment'   => $sent,
                        'candle_time' => $toiTime,
                        'details'     => "PCR: {$toiPcr} | Diff: {$diffSign} | {$toiSignal}",
                    ];

                    $signals['oi_buildup']['pcr'] = $toiPcr;
                    $signals['oi_buildup']['signal'] = $toiSignal;
                    $signals['oi_buildup']['details'] = $signals['trending_oi']['details'];
                }
            } catch (\Throwable $e) {}

            // ── 3.5 INSTITUTIONAL QUANT LEVELS & PRICE ACTION CONFLUENCE ──
            try {
                $reportService = app(\App\Services\OrderFlow\OrderFlowCumulativeReportService::class);
                $rep = $reportService->generateReport($date, $symbol);
                $ql = $rep['tomorrow_playbook']['levels'] ?? [];
                $centralPivot = (float)($rep['tomorrow_playbook']['central_pivot'] ?? $atm);
                
                $r3 = (float)($ql[0]['price'] ?? ($atm + 150));
                $r2 = (float)($ql[1]['price'] ?? ($atm + 100));
                $r1 = (float)($ql[2]['price'] ?? ($atm + 50));
                $s1 = (float)($ql[4]['price'] ?? ($atm - 50));
                $s2 = (float)($ql[5]['price'] ?? ($atm - 100));
                $s3 = (float)($ql[6]['price'] ?? ($atm - 150));

                $distToPivot = abs($spot - $centralPivot);
                $distToR1 = abs($spot - $r1);
                $distToR2 = abs($spot - $r2);
                $distToS1 = abs($spot - $s1);
                $distToS2 = abs($spot - $s2);

                $paSignal = 'NEUTRAL';
                $paDetails = "Trading near Central Pivot ₹" . number_format($centralPivot, 0);
                $paScore = 50;

                if ($spot >= $centralPivot) {
                    $paSignal = 'BULLISH';
                    $paScore = 65;
                    if ($distToR2 <= 15) {
                        $paDetails = "Testing Major VAH / Resistance R2 ₹" . number_format($r2, 0) . " - Watching for Wall Absorption or Breakout";
                        $paScore = 70;
                    } elseif ($distToR1 <= 15) {
                        $paDetails = "Testing Tactical Supply R1 ₹" . number_format($r1, 0) . " - Bullish acceptance targets R2";
                        $paScore = 65;
                    } elseif ($distToPivot <= 15) {
                        $paDetails = "Holding above Central Equilibrium Pivot ₹" . number_format($centralPivot, 0) . " (Bullish Long Continuation Zone)";
                        $paScore = 75;
                    } else {
                        $paDetails = "Bullish territory above Central Pivot (₹" . number_format($centralPivot, 0) . ")";
                    }
                } else {
                    $paSignal = 'BEARISH';
                    $paScore = 35;
                    if ($distToS2 <= 15) {
                        $paDetails = "Testing Major VAL / Support S2 ₹" . number_format($s2, 0) . " - Watching for Bid Wall defense or Breakdown";
                        $paScore = 30;
                    } elseif ($distToS1 <= 15) {
                        $paDetails = "Testing Tactical Demand S1 ₹" . number_format($s1, 0) . " - First line of defense";
                        $paScore = 35;
                    } elseif ($distToPivot <= 15) {
                        $paDetails = "Failing below Central Equilibrium Pivot ₹" . number_format($centralPivot, 0) . " (Bearish Short Breakdown Zone)";
                        $paScore = 25;
                    } else {
                        $paDetails = "Bearish territory below Central Pivot (₹" . number_format($centralPivot, 0) . ")";
                    }
                }

                $signals['price_action'] = [
                    'name'          => 'Price Action & Institutional Quant Levels',
                    'signal'        => $paSignal,
                    'score'         => $paScore,
                    'weight'        => 15,
                    'details'       => $paDetails,
                    'pivot'         => $centralPivot,
                    'r1'            => $r1,
                    'r2'            => $r2,
                    'r3'            => $r3,
                    's1'            => $s1,
                    's2'            => $s2,
                    's3'            => $s3,
                ];
            } catch (\Throwable $e) {}

            // ── 4. MASTER STRATEGIC ANCHOR & UNIFIED MACRO SYNC ──
            $signals['anchor_trigger'] = $anchorTrigger;
            if ($anchorTrigger) {
                $signals['unified_macro'] = [
                    'has_call'       => true,
                    'signal'         => $anchorTrigger['signal'],
                    'action'         => $anchorTrigger['signal'] === 'BUY' ? 'SELL PE' : 'SELL CE',
                    'candle_time'    => $anchorTrigger['candle_time'],
                    'strike'         => $atm,
                    'instrument'     => $anchorTrigger['signal'] === 'BUY' ? 'PE' : 'CE',
                    'spot_price'     => $spot,
                    'sl_price'       => null,
                    'tp_price'       => null,
                    'breadth_weight' => $anchorTrigger['weight'],
                    'breadth_count'  => $anchorTrigger['count'],
                    'net_points'     => $anchorTrigger['net_points'],
                ];
            } else {
                // Secondary check: look for legitimate historical alerts in zero_click_alert_logs (strictly >= 80% breadth)
                $legitAlert = null;
                if (Schema::hasTable('zero_click_alert_logs')) {
                    $legitAlert = DB::table('zero_click_alert_logs')
                        ->where('symbol', $symbol)
                        ->where('trade_date', $date)
                        ->whereNotIn('category', ['ORDER_FLOW_DEPTH', 'ORDER_FLOW_TEST', 'ORDER_FLOW_STRATEGY'])
                        ->where('breadth_weight_pct', '>=', 80.0)
                        ->where('breadth_count', '>=', 40)
                        ->orderByDesc('id')
                        ->first();
                }

                if ($legitAlert) {
                    $signals['anchor_trigger'] = [
                        'direction'   => $legitAlert->signal === 'BUY' ? 'BULLISH' : 'BEARISH',
                        'signal'      => $legitAlert->signal,
                        'candle_time' => $legitAlert->candle_time,
                        'weight'      => (float)$legitAlert->breadth_weight_pct,
                        'count'       => (int)$legitAlert->breadth_count,
                        'count_pct'   => (float)$legitAlert->breadth_pct,
                        'net_points'  => (float)$legitAlert->net_points,
                    ];
                    $signals['unified_macro'] = [
                        'has_call'       => true,
                        'signal'         => $legitAlert->signal,
                        'action'         => $legitAlert->action,
                        'candle_time'    => $legitAlert->candle_time,
                        'strike'         => (int)$legitAlert->strike,
                        'instrument'     => $legitAlert->instrument,
                        'spot_price'     => (float)$legitAlert->spot_price,
                        'sl_price'       => (float)$legitAlert->sl_price,
                        'tp_price'       => (float)$legitAlert->tp_price,
                        'breadth_weight' => (float)$legitAlert->breadth_weight_pct,
                        'breadth_count'  => (int)$legitAlert->breadth_count,
                        'net_points'     => (float)$legitAlert->net_points,
                    ];
                }
            }

            // ── 5. AGGREGATE STATIC CONFLUENCE SCORE ──
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
            if ($staticConfluence >= 70 && $signals['market_breadth']['signal'] === 'BULLISH' && $signals['options_flow']['signal'] === 'BULLISH') {
                $overallSignal = 'BULLISH';
            } elseif ($staticConfluence <= 35 && $signals['market_breadth']['signal'] === 'BEARISH' && $signals['options_flow']['signal'] === 'BEARISH') {
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
