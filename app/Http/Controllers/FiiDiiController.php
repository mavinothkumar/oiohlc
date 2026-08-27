<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class FiiDiiController extends Controller
{
    protected string $baseUrl = 'https://api.upstox.com/v2';

    protected function token(): string
    {
        return config('services.upstox.analytics_token');
    }

    protected function client()
    {
        return Http::withToken($this->token())
                   ->acceptJson()
                   ->timeout(15);
    }

    public function index(Request $request)
    {
        $interval = $request->input('interval', '1D');   // 1D or 1M
        $from     = $request->input('from', null);

        // ── FII segments ──────────────────────────────────────────────────────
        $fiiSegments = [
            'NSE_FO|INDEX_FUTURES',
            'NSE_FO|STOCK_FUTURES',
            'NSE_FO|INDEX_OPTIONS',
            'NSE_FO|STOCK_OPTIONS',
            'NSE_EQ|CASH',
        ];

        // ── Fetch FII data ────────────────────────────────────────────────────
        // Upstox expects repeated params: data_type=A&data_type=B (NO brackets)
        $fiiQuery = '';
        foreach ($fiiSegments as $seg) {
            $fiiQuery .= 'data_type=' . rawurlencode($seg) . '&';
        }
        $fiiQuery .= 'interval=' . rawurlencode($interval);
        if ($from) {
            $fiiQuery .= '&from=' . rawurlencode($from);
        }

        $fiiRaw  = [];
        $fiiError = null;
        try {
            $response = $this->client()->get($this->baseUrl . '/market/fii?' . $fiiQuery);
            if ($response->successful()) {
                $fiiRaw = $response->json('data', []);
            } else {
                $fiiError = $response->json('message') ?? $response->status();
            }
        } catch (\Exception $e) {
            $fiiError = $e->getMessage();
        }

        // ── Fetch DII data ────────────────────────────────────────────────────
        $diiQuery = 'data_type=' . rawurlencode('NSE_EQ|CASH') . '&interval=' . rawurlencode($interval);
        if ($from) {
            $diiQuery .= '&from=' . rawurlencode($from);
        }

        $diiRaw  = [];
        $diiError = null;
        try {
            $response = $this->client()->get($this->baseUrl . '/market/dii?' . $diiQuery);
            if ($response->successful()) {
                $diiRaw = $response->json('data', []);
            } else {
                $diiError = $response->json('message') ?? $response->status();
            }
        } catch (\Exception $e) {
            $diiError = $e->getMessage();
        }

        // ── Process & enrich ─────────────────────────────────────────────────
        $fiiData = $this->processSegments($fiiRaw);
        $diiData = $this->processSegments($diiRaw);

        // ── Build combined daily summary (FII + DII net flows by date) ────────
        $dailySummary = $this->buildDailySummary($fiiData, $diiData);

        // ── Latest day stats for hero cards ──────────────────────────────────
        $latestDate   = collect($dailySummary)->keys()->first();
        $latestFiiNet = isset($dailySummary[$latestDate]) ? $dailySummary[$latestDate]['fii_net'] : 0;
        $latestDiiNet = isset($dailySummary[$latestDate]) ? $dailySummary[$latestDate]['dii_net'] : 0;

        return view('fii-dii.index', compact(
            'fiiData',
            'diiData',
            'dailySummary',
            'interval',
            'from',
            'fiiError',
            'diiError',
            'latestDate',
            'latestFiiNet',
            'latestDiiNet',
            'fiiSegments'
        ));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Process raw segment data: sort by date desc, add net/derived fields.
     */
    protected function processSegments(array $raw): array
    {
        $processed = [];
        foreach ($raw as $segment => $rows) {
            $enriched = [];
            foreach ($rows as $row) {
                $date        = Carbon::createFromTimestampMs($row['time_stamp'])->setTimezone('Asia/Kolkata');
                $net_amount  = ($row['buy_amount'] ?? 0) - ($row['sell_amount'] ?? 0);
                $net_contracts = ($row['buy_contracts'] ?? 0) - ($row['sell_contracts'] ?? 0);
                $long_short_ratio = ($row['total_short_contracts'] ?? 0) > 0
                    ? round(($row['total_long_contracts'] ?? 0) / $row['total_short_contracts'], 2)
                    : null;

                $enriched[] = array_merge($row, [
                    'date'             => $date->format('d M Y'),
                    'date_key'         => $date->format('Y-m-d'),
                    'net_amount'       => $net_amount,
                    'net_contracts'    => $net_contracts,
                    'long_short_ratio' => $long_short_ratio,
                    'sentiment'        => $net_amount > 0 ? 'bullish' : ($net_amount < 0 ? 'bearish' : 'neutral'),
                ]);
            }

            // Sort descending by timestamp
            usort($enriched, fn($a, $b) => $b['time_stamp'] <=> $a['time_stamp']);
            $processed[$segment] = $enriched;
        }
        return $processed;
    }

    /**
     * Build a date-keyed summary merging all FII segments + DII.
     */
    protected function buildDailySummary(array $fiiData, array $diiData): array
    {
        $summary = [];

        // Aggregate FII
        foreach ($fiiData as $segment => $rows) {
            foreach ($rows as $row) {
                $dk = $row['date_key'];
                if (!isset($summary[$dk])) {
                    $summary[$dk] = [
                        'date'          => $row['date'],
                        'fii_buy'       => 0,
                        'fii_sell'      => 0,
                        'fii_net'       => 0,
                        'dii_buy'       => 0,
                        'dii_sell'      => 0,
                        'dii_net'       => 0,
                        'combined_net'  => 0,
                    ];
                }
                $summary[$dk]['fii_buy']  += $row['buy_amount'] ?? 0;
                $summary[$dk]['fii_sell'] += $row['sell_amount'] ?? 0;
                $summary[$dk]['fii_net']  += $row['net_amount'] ?? 0;
            }
        }

        // Aggregate DII
        foreach ($diiData as $segment => $rows) {
            foreach ($rows as $row) {
                $dk = $row['date_key'];
                if (!isset($summary[$dk])) {
                    $summary[$dk] = [
                        'date'          => $row['date'],
                        'fii_buy'       => 0,
                        'fii_sell'      => 0,
                        'fii_net'       => 0,
                        'dii_buy'       => 0,
                        'dii_sell'      => 0,
                        'dii_net'       => 0,
                        'combined_net'  => 0,
                    ];
                }
                $summary[$dk]['dii_buy']  += $row['buy_amount'] ?? 0;
                $summary[$dk]['dii_sell'] += $row['sell_amount'] ?? 0;
                $summary[$dk]['dii_net']  += $row['net_amount'] ?? 0;
            }
        }

        // Combined net
        foreach ($summary as $dk => &$row) {
            $row['combined_net'] = $row['fii_net'] + $row['dii_net'];
        }

        // Sort descending
        krsort($summary);
        return $summary;
    }

    /**
     * Ajax endpoint — returns JSON for chart updates.
     */
    public function data(Request $request)
    {
        // Reuse same logic, return JSON
        $interval = $request->input('interval', '1D');
        $from     = $request->input('from', null);

        $fiiSegments = [
            'NSE_FO|INDEX_FUTURES',
            'NSE_FO|STOCK_FUTURES',
            'NSE_FO|INDEX_OPTIONS',
            'NSE_FO|STOCK_OPTIONS',
            'NSE_EQ|CASH',
        ];

        $fiiQuery = '';
        foreach ($fiiSegments as $seg) {
            $fiiQuery .= 'data_type=' . rawurlencode($seg) . '&';
        }
        $fiiQuery .= 'interval=' . rawurlencode($interval);
        if ($from) {
            $fiiQuery .= '&from=' . rawurlencode($from);
        }

        $diiQuery = 'data_type=' . rawurlencode('NSE_EQ|CASH') . '&interval=' . rawurlencode($interval);
        if ($from) {
            $diiQuery .= '&from=' . rawurlencode($from);
        }

        try {
            $fiiResponse = $this->client()->get($this->baseUrl . '/market/fii?' . $fiiQuery);
            $diiResponse = $this->client()->get($this->baseUrl . '/market/dii?' . $diiQuery);

            $fiiData = $this->processSegments($fiiResponse->json('data', []));
            $diiData = $this->processSegments($diiResponse->json('data', []));
            $daily   = $this->buildDailySummary($fiiData, $diiData);

            return response()->json([
                'success'      => true,
                'fii_data'     => $fiiData,
                'dii_data'     => $diiData,
                'daily_summary'=> $daily,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
