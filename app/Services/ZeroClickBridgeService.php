<?php

namespace App\Services;

use App\Models\ZeroClickAlertLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZeroClickBridgeService
{
    protected string $webhookUrl;
    protected string $statusUrl;
    protected string $token;

    public function __construct()
    {
        $this->webhookUrl = (string) env('ZEROCLICK_WEBHOOK_URL', 'https://0click.in/api/webhook/tradingview');
        $this->statusUrl  = str_replace('/api/webhook/tradingview', '/api/webhook/position-status', $this->webhookUrl);
        $this->token      = (string) env('ZEROCLICK_WEBHOOK_TOKEN', '');
    }

    /**
     * Dispatch a V2 Entry (chosen single leg) to 0click.in.
     */
    public function dispatchEntry(array $data): array
    {
        $direction = strtoupper((string) ($data['direction'] ?? 'BULL'));
        $signalAction = ($direction === 'BULL') ? 'BUY' : 'SELL';
        $mode = strtoupper((string) ($data['mode'] ?? 'SIMULATION'));
        $mode = in_array($mode, ['LIVE', 'SIMULATION'], true) ? $mode : 'SIMULATION';

        $payload = [
            'action'           => $signalAction,
            'order_side'       => strtoupper((string) ($data['order_side'] ?? 'BUY')),
            'symbol'           => 'NIFTY',
            'strike'           => (int) ($data['strike'] ?? 0),
            'opt_type'         => strtoupper((string) ($data['opt_type'] ?? 'CE')),
            'price'            => (float) ($data['entry_spot'] ?? 0),
            'sl'               => (float) ($data['sl_spot'] ?? 0),
            'tp'               => (float) ($data['tp_spot'] ?? 0),
            'protection_basis' => 'NIFTY',
            'lots'             => max(1, (int) ($data['lots'] ?? 1)),
            'product'          => (string) ($data['product'] ?? 'MARGIN'),
            'mode'             => $mode,
            'category'         => 'OIOHLC_V2',
            'client_trade_uid' => (string) ($data['trade_uid'] ?? ''),
            'entry_reason'     => (string) ($data['entry_reason'] ?? ''),
        ];

        $log = $this->createLog('ENTRY_' . $payload['order_side'], $data, $payload, $mode);
        return $this->postToZeroClick($log, $payload);
    }

    /**
     * Query 0click.in to verify whether a strike/position is still open (and log request/response).
     */
    public function checkPositionExists(array $data): array
    {
        $mode = strtoupper((string) ($data['mode'] ?? 'SIMULATION'));
        $mode = in_array($mode, ['LIVE', 'SIMULATION'], true) ? $mode : 'SIMULATION';
        $startTime = microtime(true);

        $query = array_filter([
            'strike'           => (int) ($data['strike'] ?? 0),
            'opt_type'         => strtoupper((string) ($data['opt_type'] ?? '')),
            'client_trade_uid' => (string) ($data['trade_uid'] ?? ''),
            'mode'             => $mode,
            'category'         => 'OIOHLC_V2',
        ]);

        $log = $this->createLog('CHECK_POSITION', $data, $query, $mode, $this->statusUrl);

        try {
            $resp = Http::timeout(8)
                ->withHeaders($this->buildHeaders())
                ->get($this->statusUrl, $query);

            $latencyMs = round((microtime(true) - $startTime) * 1000, 2);
            $statusCode = $resp->status();
            $body = $resp->json() ?: ['raw' => $resp->body()];

            if (!$resp->successful()) {
                $log->update([
                    'api_status_code' => $statusCode,
                    'api_response'    => $body,
                    'latency_ms'      => $latencyMs,
                    'dispatched_at'   => Carbon::now('Asia/Kolkata'),
                    'status'          => 'FAILED',
                ]);
                return [
                    'success' => false,
                    'is_open' => null,
                    'status'  => $statusCode,
                    'message' => $body['message'] ?? "Status check failed ({$statusCode})",
                ];
            }

            $isOpen = (bool) ($body['is_open'] ?? false);
            $log->update([
                'api_status_code' => $statusCode,
                'api_response'    => $body,
                'latency_ms'      => $latencyMs,
                'dispatched_at'   => Carbon::now('Asia/Kolkata'),
                'status'          => $isOpen ? 'OPEN' : 'CLOSED',
            ]);

            return [
                'success'        => true,
                'is_open'        => $isOpen,
                'trading_symbol' => $body['trading_symbol'] ?? null,
                'net_qty'        => (int) ($body['net_qty'] ?? 0),
                'body'           => $body,
            ];
        } catch (\Throwable $e) {
            $latencyMs = round((microtime(true) - $startTime) * 1000, 2);
            $log->update([
                'api_status_code' => 500,
                'api_response'    => ['error' => $e->getMessage()],
                'latency_ms'      => $latencyMs,
                'dispatched_at'   => Carbon::now('Asia/Kolkata'),
                'status'          => 'FAILED',
            ]);
            Log::warning('ZeroClickBridgeService checkPositionExists error: ' . $e->getMessage());
            return [
                'success' => false,
                'is_open' => null,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Pre-verify position existence on 0click.in, then close only if still open.
     */
    public function dispatchExit(array $data): array
    {
        $mode = strtoupper((string) ($data['mode'] ?? 'SIMULATION'));
        $mode = in_array($mode, ['LIVE', 'SIMULATION'], true) ? $mode : 'SIMULATION';
        $exitReason = (string) ($data['exit_reason'] ?? 'OI_V2_EXIT');

        $check = $this->checkPositionExists($data);
        if ($check['success'] && $check['is_open'] === false) {
            $payload = [
                'action'           => 'CLOSE',
                'strike'           => (int) ($data['strike'] ?? 0),
                'opt_type'         => strtoupper((string) ($data['opt_type'] ?? '')),
                'mode'             => $mode,
                'client_trade_uid' => (string) ($data['trade_uid'] ?? ''),
                'exit_reason'      => $exitReason,
            ];
            $log = $this->createLog('EXIT_SKIPPED_CLOSED', $data, $payload, $mode);
            $log->update([
                'status'           => 'ALREADY_CLOSED',
                'api_status_code'  => 200,
                'api_response'     => ['message' => 'Position already closed on 0click.in (manual/external exit). Skipped close order.'],
                'dispatched_at'    => Carbon::now('Asia/Kolkata'),
            ]);

            return [
                'success'        => true,
                'already_closed' => true,
                'message'        => 'Position already closed on 0click.in — exit skipped safely.',
            ];
        }

        $payload = [
            'action'           => 'CLOSE',
            'symbol'           => 'NIFTY',
            'strike'           => (int) ($data['strike'] ?? 0),
            'opt_type'         => strtoupper((string) ($data['opt_type'] ?? '')),
            'price'            => (float) ($data['exit_spot'] ?? 0),
            'mode'             => $mode,
            'category'         => 'OIOHLC_V2',
            'client_trade_uid' => (string) ($data['trade_uid'] ?? ''),
            'exit_reason'      => $exitReason,
        ];

        $log = $this->createLog('EXIT_CLOSE', $data, $payload, $mode);
        return $this->postToZeroClick($log, $payload);
    }

    /**
     * Sync trailing Stop Loss to 0click.in when V2 trails SL.
     */
    public function syncTrailingSl(array $data): array
    {
        $mode = strtoupper((string) ($data['mode'] ?? 'SIMULATION'));
        $mode = in_array($mode, ['LIVE', 'SIMULATION'], true) ? $mode : 'SIMULATION';

        $payload = [
            'action'           => 'UPDATE_SL',
            'symbol'           => 'NIFTY',
            'strike'           => (int) ($data['strike'] ?? 0),
            'opt_type'         => strtoupper((string) ($data['opt_type'] ?? '')),
            'price'            => (float) ($data['current_spot'] ?? 0),
            'sl'               => (float) ($data['sl_spot'] ?? 0),
            'trail_peak'       => (float) ($data['peak_spot'] ?? 0),
            'mode'             => $mode,
            'category'         => 'OIOHLC_V2',
            'client_trade_uid' => (string) ($data['trade_uid'] ?? ''),
            'sl_reason'        => (string) ($data['sl_reason'] ?? 'V2 Trailing SL'),
        ];

        $log = $this->createLog('UPDATE_SL', $data, $payload, $mode);
        return $this->postToZeroClick($log, $payload);
    }

    /**
     * Resend an existing ZeroClickAlertLog entry by ID and update its status/response.
     */
    public function resendLog(int $id): array
    {
        $log = ZeroClickAlertLog::find($id);
        if (!$log) {
            return ['success' => false, 'status_code' => 404, 'message' => "Log #{$id} not found."];
        }

        $payload = is_array($log->payload) ? $log->payload : [];
        $endpoint = (string) ($log->endpoint_url ?: $this->webhookUrl);

        if (strtoupper((string) $log->action) === 'CHECK_POSITION' || str_contains($endpoint, 'position-status')) {
            $startTime = microtime(true);
            try {
                $resp = Http::timeout(8)
                    ->withHeaders($this->buildHeaders())
                    ->get($endpoint, $payload);

                $latencyMs = round((microtime(true) - $startTime) * 1000, 2);
                $statusCode = $resp->status();
                $body = $resp->json() ?: ['raw' => $resp->body()];
                $ok = $resp->successful();
                $isOpen = (bool) ($body['is_open'] ?? false);

                $log->update([
                    'api_status_code' => $statusCode,
                    'api_response'    => $body,
                    'latency_ms'      => $latencyMs,
                    'dispatched_at'   => Carbon::now('Asia/Kolkata'),
                    'status'          => $ok ? ($isOpen ? 'OPEN' : 'CLOSED') : 'FAILED',
                ]);

                return [
                    'success'     => $ok,
                    'status_code' => $statusCode,
                    'latency_ms'  => $latencyMs,
                    'response'    => $body,
                    'message'     => $body['message'] ?? ($ok ? ($isOpen ? 'Position OPEN' : 'Position CLOSED') : "HTTP {$statusCode}"),
                ];
            } catch (\Throwable $e) {
                $latencyMs = round((microtime(true) - $startTime) * 1000, 2);
                $log->update([
                    'api_status_code' => 500,
                    'api_response'    => ['error' => $e->getMessage()],
                    'latency_ms'      => $latencyMs,
                    'dispatched_at'   => Carbon::now('Asia/Kolkata'),
                    'status'          => 'FAILED',
                ]);
                return ['success' => false, 'status_code' => 500, 'latency_ms' => $latencyMs, 'message' => $e->getMessage()];
            }
        }

        return $this->postToZeroClick($log, $payload);
    }

    protected function buildHeaders(): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];
        if (!empty($this->token)) {
            $headers['X-Webhook-Token'] = $this->token;
            $headers['X-API-KEY']       = $this->token;
            $headers['Authorization']   = 'Bearer ' . $this->token;
        }
        return $headers;
    }

    protected function createLog(string $actionLabel, array $data, array $payload, string $mode, ?string $endpointUrl = null): ZeroClickAlertLog
    {
        $now = Carbon::now('Asia/Kolkata');
        return ZeroClickAlertLog::create([
            'triggered_at' => $now,
            'trade_date'   => $now->toDateString(),
            'candle_time'  => (string) ($data['candle_time'] ?? $now->format('H:i')),
            'signal'       => (string) ($data['direction'] ?? 'BULL'),
            'action'       => $actionLabel,
            'symbol'       => 'NIFTY',
            'instrument'   => (string) ($data['instrument'] ?? ''),
            'strike'       => (int) ($data['strike'] ?? 0),
            'spot_price'   => (float) ($data['entry_spot'] ?? $data['exit_spot'] ?? $data['current_spot'] ?? 0),
            'sl_price'     => (float) ($data['sl_spot'] ?? 0),
            'tp_price'     => (float) ($data['tp_spot'] ?? 0),
            'lots'         => max(1, (int) ($data['lots'] ?? 1)),
            'mode'         => $mode,
            'product'      => (string) ($data['product'] ?? 'MARGIN'),
            'category'     => 'OIOHLC_V2',
            'payload'      => $payload,
            'status'       => 'PENDING',
            'endpoint_url' => $endpointUrl ?: $this->webhookUrl,
        ]);
    }

    protected function postToZeroClick(ZeroClickAlertLog $log, array $payload): array
    {
        $startTime = microtime(true);
        if (!empty($this->token) && !isset($payload['secret'])) {
            $payload['secret'] = $this->token;
        }

        try {
            $resp = Http::timeout(10)
                ->withHeaders($this->buildHeaders())
                ->post($this->webhookUrl, $payload);

            $latencyMs = round((microtime(true) - $startTime) * 1000, 2);
            $statusCode = $resp->status();
            $body = $resp->json() ?: ['raw' => $resp->body()];
            $ok = $resp->successful();

            $log->update([
                'api_status_code' => $statusCode,
                'api_response'    => $body,
                'latency_ms'      => $latencyMs,
                'dispatched_at'   => Carbon::now('Asia/Kolkata'),
                'status'          => $ok ? 'SENT' : 'FAILED',
            ]);

            return [
                'success'     => $ok,
                'status_code' => $statusCode,
                'response'    => $body,
                'latency_ms'  => $latencyMs,
                'message'     => $body['message'] ?? ($ok ? 'Dispatched to 0click.in' : "HTTP {$statusCode}"),
            ];
        } catch (\Throwable $e) {
            $latencyMs = round((microtime(true) - $startTime) * 1000, 2);
            $log->update([
                'api_status_code' => 500,
                'api_response'    => ['error' => $e->getMessage()],
                'latency_ms'      => $latencyMs,
                'dispatched_at'   => Carbon::now('Asia/Kolkata'),
                'status'          => 'FAILED',
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
