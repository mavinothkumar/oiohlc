<?php

namespace App\Http\Controllers;

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
        return Cache::remember($cacheKey, 30, function () use ($symbol, $date, $expiry, $atm, $spot) {
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
        ];

        // 1. Evaluate Quant Engine from Daily Trend / Statistical Metrics
        try {
            if (Schema::hasTable('daily_trend')) {
                $trendRow = DB::table('daily_trend')
                    ->where('symbol_name', $symbol)
                    ->orderByDesc('id')
                    ->first();

                if ($trendRow) {
                    $pcr = (float) ($trendRow->pcr ?? 1.0);
                    $confluence = 50;
                    $quantSignal = 'NEUTRAL';

                    if ($pcr > 1.25) {
                        $quantSignal = 'BULLISH';
                        $confluence = min(92, (int)(50 + ($pcr - 1.0) * 85));
                    } elseif ($pcr < 0.80) {
                        $quantSignal = 'BEARISH';
                        $confluence = max(15, (int)(50 - (1.0 - $pcr) * 85));
                    }

                    $expectedMove = round($spot * 0.0075, 1);
                    $pop = $confluence >= 70 ? 82.5 : 68.0;

                    $signals['quant_engine'] = [
                        'name'          => 'Quant Statistical Prob Engine',
                        'signal'        => $quantSignal,
                        'score'         => $confluence,
                        'weight'        => 25,
                        'details'       => "POP {$pop}% | Expected Range ±{$expectedMove} pts",
                        'pop_pct'       => $pop,
                        'expected_move' => $expectedMove,
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Quant engine graceful fallback
        }

        // 2. Evaluate OI Buildup from daily_trend / option_chains
        try {
            if (Schema::hasTable('daily_trend')) {
                $trendRow = DB::table('daily_trend')
                    ->where('symbol_name', $symbol)
                    ->orderByDesc('id')
                    ->first();

                if ($trendRow) {
                    $pcr = (float) ($trendRow->pcr ?? 1.0);
                    $netCeOi = (float) ($trendRow->total_ce_oi ?? 0);
                    $netPeOi = (float) ($trendRow->total_pe_oi ?? 0);

                    $oiScore = 50;
                    $oiSignal = 'NEUTRAL';
                    $flowDesc = 'Balanced Flow';

                    if ($pcr > 1.25) {
                        $oiSignal = 'BULLISH';
                        $oiScore  = min(95, (int)(50 + ($pcr - 1.0) * 80));
                        $flowDesc = "Put Writing Dominance (Bullish Floor, PCR: {$pcr})";
                    } elseif ($pcr < 0.80) {
                        $oiSignal = 'BEARISH';
                        $oiScore  = max(10, (int)(50 - (1.0 - $pcr) * 80));
                        $flowDesc = "Call Writing Dominance (Heavy Ceiling, PCR: {$pcr})";
                    }

                    $signals['oi_buildup'] = [
                        'name'     => 'OI Buildup & Trending Flow',
                        'signal'   => $oiSignal,
                        'score'    => $oiScore,
                        'weight'   => 25,
                        'details'  => $flowDesc,
                        'pcr'      => round($pcr, 2),
                        'net_flow' => $flowDesc,
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Fallback
        }

        // 3. Evaluate Market Breadth
        try {
            if (Schema::hasTable('nifty_weightage_history')) {
                $latestBreadth = DB::table('nifty_weightage_history')
                    ->orderByDesc('id')
                    ->first();

                if ($latestBreadth && isset($latestBreadth->advances, $latestBreadth->declines)) {
                    $adv = (int) $latestBreadth->advances;
                    $dec = (int) $latestBreadth->declines;
                    $ratio = ($adv + $dec > 0) ? ($adv / ($adv + $dec)) : 0.5;

                    $breadthSignal = 'NEUTRAL';
                    $bScore = 50;
                    if ($ratio >= 0.65) {
                        $breadthSignal = 'BULLISH';
                        $bScore = 80;
                    } elseif ($ratio <= 0.35) {
                        $breadthSignal = 'BEARISH';
                        $bScore = 20;
                    }

                    $signals['market_breadth'] = [
                        'name'     => 'Heavyweight Market Breadth',
                        'signal'   => $breadthSignal,
                        'score'    => $bScore,
                        'weight'   => 20,
                        'details'  => "Advances: {$adv} | Declines: {$dec} (" . round($ratio * 100) . "% Green)",
                        'advances' => $adv,
                        'declines' => $dec,
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Fallback
        }

        // 4. Calculate Aggregate Confluence Score
        $totalWeight = 0;
        $weightedScore = 0;

        foreach ($signals as $key => $sig) {
            if ($key === 'depth_orderflow') continue; // Real-time client computes depth
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
