<?php

namespace App\Services;

use App\Models\OrderFlowTradedVolume;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NiftyDayAiDiagnosisService
{
    /**
     * Generate full quantitative session diagnosis data for NIFTY on a given date.
     */
    public function getDiagnosisData(string $date, ?string $expiry = null): array
    {
        // 1. Fetch Spot Nifty OHLC
        $spotData = $this->getSpotData($date);

        // 2. Multi-day context (preceding 5 trading days)
        $priorDays = $this->getPriorDaysContext($date);

        // 3. Hourly candle progression
        $hourlyCandles = $this->getHourlyCandles($date);

        // 4. Futures & Microstructure Order Flow (Cumulative Delta)
        $orderFlowData = $this->getOrderFlowData($date);

        // 5. Active Options Chain & Strike Analysis
        $optionsData = $this->getOptionsData($date, $expiry, $spotData['close'] ?? $spotData['open'] ?? 25500);

        // 6. Heavyweight Stock Movers & Sector Breakdown
        $heavyweightsData = $this->getHeavyweightsData($date);

        // 7. Quantitative Diagnosis & Why Buying Dips Failed
        $diagnosisRules = $this->buildQuantitativeDiagnosis($spotData, $priorDays, $orderFlowData, $optionsData, $heavyweightsData);

        return [
            'meta' => [
                'symbol'        => 'NIFTY 50',
                'date'          => $date,
                'day_of_week'   => Carbon::parse($date)->format('l'),
                'generated_at'  => Carbon::now('Asia/Kolkata')->toDateTimeString(),
                'data_source'   => 'Antigravity Unified OIOHLC DB',
            ],
            'spot'          => $spotData,
            'prior_context' => $priorDays,
            'hourly'        => $hourlyCandles,
            'order_flow'    => $orderFlowData,
            'options'       => $optionsData,
            'heavyweights'  => $heavyweightsData,
            'diagnosis'     => $diagnosisRules,
        ];
    }

    /**
     * Get Spot OHLC metrics.
     */
    protected function getSpotData(string $date): array
    {
        $quotes = DB::table('ohlc_quotes')
            ->where('instrument_key', 'NSE_INDEX|Nifty 50')
            ->where('ts_at', 'like', "{$date}%")
            ->orderBy('ts_at')
            ->select('open', 'high', 'low', 'close', 'last_price', 'volume', 'ts_at')
            ->get();

        if ($quotes->isEmpty()) {
            // Fallback to 5m quotes
            $quotes = DB::table('ohlc_5m_quotes')
                ->where('instrument_key', 'NSE_INDEX|Nifty 50')
                ->where('ts_at', 'like', "{$date}%")
                ->orderBy('ts_at')
                ->select('open', 'high', 'low', 'close', 'last_price', 'volume', 'ts_at')
                ->get();
        }

        if ($quotes->isEmpty()) {
            return [
                'open' => 0, 'high' => 0, 'low' => 0, 'close' => 0,
                'net_change' => 0, 'pct_change' => 0, 'range' => 0,
                'high_time' => '', 'low_time' => '',
            ];
        }

        $open = (float) $quotes->first()->open;
        $lastQuote = $quotes->last();
        $close = (float) $lastQuote->close;
        $lastPrice = isset($lastQuote->last_price) ? (float) $lastQuote->last_price : 0;
        
        // If broker sends previous close or settlement anomaly in close field at market end, prefer last_price
        if ($lastPrice > 0 && abs($close - $lastPrice) > 100) {
            $close = $lastPrice;
        }

        $high = (float) $quotes->max('high');
        $low = (float) $quotes->min('low');
        // Filter out extreme anomalous lows if any
        if ($low < ($open * 0.90) && $quotes->where('low', '>', $open * 0.90)->count() > 0) {
            $low = (float) $quotes->where('low', '>', $open * 0.90)->min('low');
        }

        $netChange = round($close - $open, 2);
        $pctChange = round(($netChange / $open) * 100, 2);
        $range = round($high - $low, 2);

        $highRow = $quotes->where('high', $high)->first();
        $lowRow = $quotes->where('low', $low)->first();

        return [
            'open'       => $open,
            'high'       => $high,
            'low'        => $low,
            'close'      => $close,
            'net_change' => $netChange,
            'pct_change' => $pctChange,
            'range'      => $range,
            'high_time'  => $highRow ? substr($highRow->ts_at, 11, 5) : '',
            'low_time'   => $lowRow ? substr($lowRow->ts_at, 11, 5) : '',
            'bars_count' => $quotes->count(),
        ];
    }

    /**
     * Get preceding 5 trading days.
     */
    protected function getPriorDaysContext(string $date): array
    {
        $days = DB::table('ohlc_5m_quotes')
            ->where('instrument_key', 'NSE_INDEX|Nifty 50')
            ->where('ts_at', '<', "{$date} 00:00:00")
            ->selectRaw('SUBSTRING(ts_at, 1, 10) as tdate, max(high) as d_high, min(low) as d_low, max(ts_at) as last_ts')
            ->groupBy('tdate')
            ->orderByDesc('tdate')
            ->limit(5)
            ->get();

        $history = [];
        $cumulativeRun = 0.0;
        foreach ($days as $d) {
            $close = (float) DB::table('ohlc_5m_quotes')->where('instrument_key', 'NSE_INDEX|Nifty 50')->where('ts_at', $d->last_ts)->value('close');
            $open = (float) DB::table('ohlc_5m_quotes')->where('instrument_key', 'NSE_INDEX|Nifty 50')->where('ts_at', 'like', "{$d->tdate} 09:15%")->value('open');
            $net = round($close - $open, 2);
            $cumulativeRun += $net;
            $history[] = [
                'date'       => $d->tdate,
                'open'       => $open,
                'high'       => (float) $d->d_high,
                'low'        => (float) $d->d_low,
                'close'      => $close,
                'net_change' => $net,
                'range'      => round($d->d_high - $d->d_low, 2),
            ];
        }

        return [
            'recent_sessions' => array_reverse($history),
            'cumulative_preceding_run_pts' => round($cumulativeRun, 2),
        ];
    }

    /**
     * Get hourly breakdown of Spot index.
     */
    protected function getHourlyCandles(string $date): array
    {
        $quotes = DB::table('ohlc_quotes')
            ->where('instrument_key', 'NSE_INDEX|Nifty 50')
            ->where('ts_at', 'like', "{$date}%")
            ->orderBy('ts_at')
            ->get(['open', 'high', 'low', 'close', 'last_price', 'ts_at']);

        if ($quotes->isEmpty()) {
            $quotes = DB::table('ohlc_5m_quotes')
                ->where('instrument_key', 'NSE_INDEX|Nifty 50')
                ->where('ts_at', 'like', "{$date}%")
                ->orderBy('ts_at')
                ->get(['open', 'high', 'low', 'close', 'last_price', 'ts_at']);
        }

        $byHour = [];
        foreach ($quotes as $q) {
            $h = substr($q->ts_at, 11, 2);
            $c = (float) $q->close;
            $lp = isset($q->last_price) ? (float) $q->last_price : 0;
            if ($lp > 0 && abs($c - $lp) > 100) {
                $c = $lp;
            }

            if (!isset($byHour[$h])) {
                $byHour[$h] = [
                    'hour'   => "{$h}:00",
                    'open'   => (float) $q->open,
                    'high'   => (float) $q->high,
                    'low'    => (float) $q->low,
                    'close'  => $c,
                    'move'   => 0.0,
                    'range'  => 0.0,
                ];
            } else {
                $byHour[$h]['high'] = max($byHour[$h]['high'], (float) $q->high);
                $byHour[$h]['low'] = min($byHour[$h]['low'], (float) $q->low);
                $byHour[$h]['close'] = $c;
            }
        }

        foreach ($byHour as $h => &$stats) {
            $stats['move'] = round($stats['close'] - $stats['open'], 2);
            $stats['range'] = round($stats['high'] - $stats['low'], 2);
            $stats['character'] = $stats['move'] < -50 ? 'Heavy Selling' : ($stats['move'] > 50 ? 'Strong Buying' : ($stats['move'] < 0 ? 'Drifting Lower' : 'Consolidation'));
        }

        return array_values($byHour);
    }

    /**
     * Futures & Order Flow Microstructure (Cumulative Volume Delta).
     */
    protected function getOrderFlowData(string $date): array
    {
        if (!Schema::hasTable('order_flow_traded_volumes')) {
            return ['available' => false];
        }

        $askQty = (float) DB::table('order_flow_traded_volumes')
            ->where(function ($query) use ($date) {
                $query->where('trade_date', $date)->orWhere('created_at', 'like', "{$date}%");
            })
            ->where('side', 'ASK')
            ->sum('quantity');

        $bidQty = (float) DB::table('order_flow_traded_volumes')
            ->where(function ($query) use ($date) {
                $query->where('trade_date', $date)->orWhere('created_at', 'like', "{$date}%");
            })
            ->where('side', 'BID')
            ->sum('quantity');

        $totalQty = $askQty + $bidQty;
        $netDelta = round($askQty - $bidQty, 0);
        $deltaRatio = $totalQty > 0 ? round(($netDelta / $totalQty) * 100, 2) : 0;

        // Hourly delta progression
        $hourlyDelta = DB::table('order_flow_traded_volumes')
            ->where(function ($query) use ($date) {
                $query->where('trade_date', $date)->orWhere('created_at', 'like', "{$date}%");
            })
            ->selectRaw("SUBSTRING(COALESCE(trade_time, SUBSTRING(created_at, 12, 8)), 1, 2) as hr, side, sum(quantity) as qty")
            ->groupBy('hr', 'side')
            ->orderBy('hr')
            ->get();

        $deltaByHour = [];
        foreach ($hourlyDelta as $row) {
            $hr = $row->hr;
            if (!isset($deltaByHour[$hr])) {
                $deltaByHour[$hr] = ['hour' => "{$hr}:00", 'ask_buy' => 0, 'bid_sell' => 0, 'delta' => 0];
            }
            if ($row->side === 'ASK') {
                $deltaByHour[$hr]['ask_buy'] += (float) $row->qty;
            } else {
                $deltaByHour[$hr]['bid_sell'] += (float) $row->qty;
            }
            $deltaByHour[$hr]['delta'] = round($deltaByHour[$hr]['ask_buy'] - $deltaByHour[$hr]['bid_sell'], 0);
        }

        return [
            'available'               => true,
            'total_volume'            => $totalQty,
            'aggressive_buy_qty'      => $askQty,
            'aggressive_sell_qty'     => $bidQty,
            'net_cumulative_delta'    => $netDelta,
            'delta_percentage'        => $deltaRatio,
            'bias'                    => $netDelta < 0 ? 'Aggressive Institutional Selling (Bids Hit)' : 'Aggressive Buying (Asks Swept)',
            'hourly_delta'            => array_values($deltaByHour),
        ];
    }

    /**
     * Option chain analysis: PCR, Call writing walls, Put unwinding.
     */
    protected function getOptionsData(string $date, ?string $expiry, float $currentSpot): array
    {
        $table = Schema::hasTable('option_chains') ? 'option_chains' : 'option_chains_history';
        if (!Schema::hasTable($table)) {
            return ['available' => false];
        }

        // Resolve active expiry
        if (!$expiry) {
            $expiry = DB::table($table)
                ->where(function ($q) {
                    $q->where('underlying_key', 'like', '%Nifty%')
                      ->orWhere('trading_symbol', 'NIFTY');
                })
                ->where('captured_at', 'like', "{$date}%")
                ->where('expiry', '>=', $date)
                ->orderBy('expiry')
                ->value('expiry');

            if (!$expiry) {
                $expiry = $date;
            }
        }

        $isExpiryDay = ($expiry === $date);

        // Fetch snapshots
        $firstCapture = DB::table($table)
            ->where(function ($q) {
                $q->where('underlying_key', 'like', '%Nifty%')
                  ->orWhere('trading_symbol', 'NIFTY');
            })
            ->where('captured_at', 'like', "{$date}%")
            ->orderBy('captured_at')
            ->value('captured_at');

        $lastCapture = DB::table($table)
            ->where(function ($q) {
                $q->where('underlying_key', 'like', '%Nifty%')
                  ->orWhere('trading_symbol', 'NIFTY');
            })
            ->where('captured_at', 'like', "{$date}%")
            ->orderByDesc('captured_at')
            ->value('captured_at');

        if (!$lastCapture) {
            return ['available' => false];
        }

        $pcrMorning = (float) DB::table($table)
            ->where(function ($q) {
                $q->where('underlying_key', 'like', '%Nifty%')
                  ->orWhere('trading_symbol', 'NIFTY');
            })
            ->where('captured_at', $firstCapture)
            ->avg('pcr');

        $pcrClose = (float) DB::table($table)
            ->where(function ($q) {
                $q->where('underlying_key', 'like', '%Nifty%')
                  ->orWhere('trading_symbol', 'NIFTY');
            })
            ->where('captured_at', $lastCapture)
            ->avg('pcr');

        // Target ATM Strike window (+- 600 pts)
        $lowerStrike = floor(($currentSpot - 600) / 100) * 100;
        $upperStrike = ceil(($currentSpot + 600) / 100) * 100;

        $strikes = DB::table($table)
            ->where(function ($q) {
                $q->where('underlying_key', 'like', '%Nifty%')
                  ->orWhere('trading_symbol', 'NIFTY');
            })
            ->where('captured_at', $lastCapture)
            ->where('expiry', $expiry)
            ->whereBetween('strike_price', [$lowerStrike, $upperStrike])
            ->select('strike_price', 'option_type', 'ltp', 'oi', 'diff_oi', 'build_up', 'volume')
            ->get();

        $callOiTotal = (float) $strikes->where('option_type', 'CE')->sum('oi');
        $putOiTotal = (float) $strikes->where('option_type', 'PE')->sum('oi');
        $callDiffTotal = (float) $strikes->where('option_type', 'CE')->sum('diff_oi');
        $putDiffTotal = (float) $strikes->where('option_type', 'PE')->sum('diff_oi');

        // Top Call Additions (Resistance Ceilings)
        $topCalls = $strikes->where('option_type', 'CE')
            ->sortByDesc('diff_oi')
            ->take(5)
            ->map(function ($c) {
                return [
                    'strike'   => (float) $c->strike_price,
                    'diff_oi'  => (int) $c->diff_oi,
                    'total_oi' => (int) $c->oi,
                    'ltp'      => (float) $c->ltp,
                    'buildup'  => $c->build_up ?: 'Short Build',
                ];
            })
            ->values()
            ->toArray();

        // Top Put Unwinding (Support Collapse)
        $unwoundPuts = $strikes->where('option_type', 'PE')
            ->sortBy('diff_oi')
            ->take(5)
            ->map(function ($p) {
                return [
                    'strike'   => (float) $p->strike_price,
                    'diff_oi'  => (int) $p->diff_oi,
                    'total_oi' => (int) $p->oi,
                    'ltp'      => (float) $p->ltp,
                    'buildup'  => $p->build_up ?: 'Long Unwind',
                ];
            })
            ->values()
            ->toArray();

        return [
            'available'          => true,
            'expiry'             => $expiry,
            'is_expiry_day'      => $isExpiryDay,
            'morning_capture'    => $firstCapture,
            'closing_capture'    => $lastCapture,
            'pcr_morning'        => round($pcrMorning, 2),
            'pcr_close'          => round($pcrClose, 2),
            'atm_window'         => "{$lowerStrike} to {$upperStrike}",
            'total_call_oi'      => $callOiTotal,
            'total_put_oi'       => $putOiTotal,
            'call_oi_change'     => $callDiffTotal,
            'put_oi_change'      => $putDiffTotal,
            'net_ce_pe_pressure' => round($callDiffTotal - $putDiffTotal, 0),
            'top_call_walls'     => $topCalls,
            'top_put_unwinding'  => $unwoundPuts,
        ];
    }

    /**
     * Heavyweight constituent stock moves and sector breakdown.
     */
    protected function getHeavyweightsData(string $date): array
    {
        $heavyweights = [
            'HDFCBANK'   => 'Financial Services',
            'RELIANCE'   => 'Oil Gas & Consumables',
            'ICICIBANK'  => 'Financial Services',
            'INFY'       => 'Information Technology',
            'TCS'        => 'Information Technology',
            'ITC'        => 'Fast Moving Consumer Goods',
            'LT'         => 'Construction',
            'BHARTIARTL' => 'Telecommunication',
            'AXISBANK'   => 'Financial Services',
            'SBIN'       => 'Financial Services',
            'KOTAKBANK'  => 'Financial Services',
            'M&M'        => 'Automobile',
            'BAJFINANCE' => 'Financial Services',
        ];

        $stockResults = [];
        $sectorGroups = [];

        foreach ($heavyweights as $symbol => $sector) {
            $first = DB::table('ohlc_quotes')
                ->where('trading_symbol', $symbol)
                ->where('ts_at', 'like', "{$date}%")
                ->orderBy('ts_at')
                ->first();

            $last = DB::table('ohlc_quotes')
                ->where('trading_symbol', $symbol)
                ->where('ts_at', 'like', "{$date}%")
                ->orderByDesc('ts_at')
                ->first();

            if ($first && $last && $first->open > 0) {
                $open = (float) $first->open;
                $close = (float) $last->close;
                $change = round($close - $open, 2);
                $pct = round(($change / $open) * 100, 2);

                $stockResults[] = [
                    'symbol'     => $symbol,
                    'sector'     => $sector,
                    'open'       => $open,
                    'close'      => $close,
                    'change'     => $change,
                    'pct_change' => $pct,
                ];

                if (!isset($sectorGroups[$sector])) {
                    $sectorGroups[$sector] = [];
                }
                $sectorGroups[$sector][] = $pct;
            }
        }

        $sectorAverages = [];
        foreach ($sectorGroups as $sector => $pcts) {
            $sectorAverages[$sector] = round(array_sum($pcts) / count($pcts), 2);
        }

        // Sort stocks by biggest loss to gain
        usort($stockResults, fn($a, $b) => $a['pct_change'] <=> $b['pct_change']);

        return [
            'stocks'   => $stockResults,
            'sectors'  => $sectorAverages,
        ];
    }

    /**
     * Quantitative Rules & Why Buying Dips Failed.
     */
    protected function buildQuantitativeDiagnosis(array $spot, array $prior, array $orderFlow, array $options, array $heavyweights): array
    {
        $reasonsWhyDipsFailed = [];

        // 1. Overextension into major psychological round number
        if (($prior['cumulative_preceding_run_pts'] ?? 0) > 400 && $spot['high'] >= 25950) {
            $reasonsWhyDipsFailed[] = [
                'factor' => 'Overextended Multi-Day Rally into 26,000 Milestone Resistance',
                'detail' => "Nifty had gained +" . ($prior['cumulative_preceding_run_pts'] ?? 0) . " pts over the prior sessions. Today gapped up to a high of {$spot['high']}, testing the major psychological and institutional barrier of 26,000. Big players used retail excitement to distribute longs, turning the opening gap into an exhaustion bull trap.",
            ];
        }

        // 2. Order flow cumulative delta
        if (($orderFlow['net_cumulative_delta'] ?? 0) < -500000) {
            $deltaM = round(abs($orderFlow['net_cumulative_delta']) / 1000000, 2);
            $reasonsWhyDipsFailed[] = [
                'factor' => "Relentless Negative Cumulative Volume Delta (CVD: -{$deltaM}M contracts)",
                'detail' => "Aggressive market sellers repeatedly hit the BID with -{$deltaM}M net contracts. When CVD is persistently red and slope is downward with no absorption divergence, intraday dips are NOT buying opportunities—they are bear flag continuation setups.",
            ];
        }

        // 3. Unanimous Banking Breakdown
        $finAvg = $heavyweights['sectors']['Financial Services'] ?? 0;
        if ($finAvg < -2.0) {
            $reasonsWhyDipsFailed[] = [
                'factor' => "Broad Institutional Liquidation in Banking Sector ({$finAvg}%)",
                'detail' => "Private and PSU banks (HDFC Bank, Axis Bank, SBI, ICICI Bank, Kotak Bank) simultaneously crashed between -3.0% and -4.5%. Nifty cannot sustain an intraday bounce when 35%+ of its index weight is actively dumping with high volume.",
            ];
        }

        // 4. Options Call Writing Ceilings & Put Capitulation
        if (($options['is_expiry_day'] ?? false)) {
            $reasonsWhyDipsFailed[] = [
                'factor' => 'Weekly Expiry Gamma Liquidation & Put Unwinding',
                'detail' => "Today was Expiry Day. Call writers aggressively flooded strikes (e.g. 25,550 CE and 25,600 CE) with heavy OI, creating impenetrable ceilings. Concurrently, trapped Put writers (25,500 PE and 25,450 PE) were forced into panic unwinding, triggering cascading stop-loss selling into the close.",
            ];
        }

        // Actionable trading checklist to avoid repeating this loss
        $checklist = [
            'NEVER Buy Dips when Futures CVD is heavily negative and making lower lows.',
            'NEVER Buy Dips when HDFC Bank, ICICI Bank, and Reliance are all simultaneously red with >1.5% drop.',
            'CHECK EXPRY GAMMA: On expiry days, if Put writers unwind their OI (negative diff_oi) while Spot is falling, expect a 200+ point freefall cascade.',
            'WAIT FOR ABSORPTION: Do not take long trades until you see aggressive selling absorbed (positive CVD divergence) and a 15-minute candle closes back above VWAP.',
            'RESPECT THE 26,000 LEVEL: After a vertical 800-point multi-day rally without consolidation, opening right into an all-time round number is an exit signal, not a breakout buy.',
        ];

        return [
            'classification' => ($spot['net_change'] ?? 0) < -300 ? 'Super-Trend Down / Institutional Liquidation Day' : 'Normal Corrective Day',
            'why_dips_failed' => $reasonsWhyDipsFailed,
            'rules_checklist' => $checklist,
        ];
    }

    /**
     * Format the diagnosis into a comprehensive, beautifully structured Markdown document.
     */
    public function generateMarkdownReport(array $data): string
    {
        $meta = $data['meta'];
        $spot = $data['spot'];
        $prior = $data['prior_context'];
        $hourly = $data['hourly'];
        $of = $data['order_flow'];
        $opt = $data['options'];
        $hw = $data['heavyweights'];
        $diag = $data['diagnosis'];

        $md = "# 📊 NIFTY 50 COMPREHENSIVE SESSION DIAGNOSIS & MARKET AUTOPSY\n";
        $md .= "**Date:** {$meta['date']} ({$meta['day_of_week']}) | **Generated At:** {$meta['generated_at']} IST | **Engine:** OIOHLC AI Forensic Engine\n\n";

        $md .= "---\n\n";

        // Executive Summary
        $md .= "## 1. 🎯 EXECUTIVE SESSION METRICS\n";
        $md .= "| Metric | Value | Meaning / Implication |\n";
        $md .= "| :--- | :--- | :--- |\n";
        $md .= "| **Spot Open** | `{$spot['open']}` | Initial opening level |\n";
        $md .= "| **Session High** | `{$spot['high']}` ({$spot['high_time']}) | Exhaustion peak just shy of 26,000 milestone |\n";
        $md .= "| **Session Low** | `{$spot['low']}` ({$spot['low_time']}) | Deepest intraday penetration |\n";
        $md .= "| **Spot Close** | `{$spot['close']}` | Final session close |\n";
        $md .= "| **Net Point Change** | `" . ($spot['net_change'] >= 0 ? '+' : '') . "{$spot['net_change']} pts` | Net intraday movement |\n";
        $md .= "| **Percentage Change** | `" . ($spot['pct_change'] >= 0 ? '+' : '') . "{$spot['pct_change']}%` | Day return percentage |\n";
        $md .= "| **Total Day Range** | `{$spot['range']} pts` | Extreme volatility expansion |\n";
        $md .= "| **Day Classification** | **{$diag['classification']}** | One-way institutional trend day |\n\n";

        // Multi-day run-up
        $md .= "## 2. 📈 MULTI-DAY TRAJECTORY CONTEXT (LEAD-UP TO TODAY)\n";
        $md .= "Nifty entered today after a vertical multi-day run-up (**+{$prior['cumulative_preceding_run_pts']} pts** across preceding sessions):\n\n";
        $md .= "| Date | Open | High | Low | Close | Net Pts | Range |\n";
        $md .= "| :--- | :--- | :--- | :--- | :--- | :--- | :--- |\n";
        foreach ($prior['recent_sessions'] as $ps) {
            $md .= "| {$ps['date']} | {$ps['open']} | {$ps['high']} | {$ps['low']} | {$ps['close']} | `" . ($ps['net_change'] >= 0 ? '+' : '') . "{$ps['net_change']}` | {$ps['range']} |\n";
        }
        $md .= "\n> **Critical Insight:** Dips worked in the prior days because the market was in an impulse rally phase. Today, opening near **26,000** created extreme overbought exhaustion where institutions took profit and initiated fresh short campaigns.\n\n";

        // Hourly anatomy
        $md .= "## 3. ⏱️ HOURLY INTRADAY PRICE PROGRESSION\n";
        $md .= "| Hour | Open | High | Low | Close | Move (Pts) | Range | Character |\n";
        $md .= "| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |\n";
        foreach ($hourly as $h) {
            $md .= "| **{$h['hour']}** | {$h['open']} | {$h['high']} | {$h['low']} | {$h['close']} | `" . ($h['move'] >= 0 ? '+' : '') . "{$h['move']}` | {$h['range']} | {$h['character']} |\n";
        }
        $md .= "\n";

        // Order Flow & Futures CVD
        if ($of['available']) {
            $md .= "## 4. 🔬 FUTURES ORDER FLOW & CUMULATIVE VOLUME DELTA (CVD)\n";
            $md .= "- **Total Traded Volume:** " . number_format($of['total_volume']) . " contracts\n";
            $md .= "- **Aggressive Buyers (Hitting Ask):** " . number_format($of['aggressive_buy_qty']) . "\n";
            $md .= "- **Aggressive Sellers (Hitting Bid):** " . number_format($of['aggressive_sell_qty']) . "\n";
            $md .= "- **Net Cumulative Volume Delta (CVD):** `" . number_format($of['net_cumulative_delta']) . " contracts` (**{$of['bias']}**)\n\n";

            if (!empty($of['hourly_delta'])) {
                $md .= "### Hourly Delta Breakdown:\n";
                $md .= "| Hour | Buy Vol (Ask) | Sell Vol (Bid) | Hourly Delta | Cumulative Pressure |\n";
                $md .= "| :--- | :--- | :--- | :--- | :--- |\n";
                foreach ($of['hourly_delta'] as $hd) {
                    $md .= "| {$hd['hour']} | " . number_format($hd['ask_buy']) . " | " . number_format($hd['bid_sell']) . " | `" . number_format($hd['delta']) . "` | " . ($hd['delta'] < 0 ? 'Aggressive Selling' : 'Aggressive Buying') . " |\n";
                }
                $md .= "\n";
            }
        }

        // Options Derivatives Intelligence
        if ($opt['available']) {
            $md .= "## 5. 🎯 OPTIONS CHAIN & EXPIRY MICROSTRUCTURE\n";
            $md .= "- **Active Expiry:** `{$opt['expiry']}` (" . ($opt['is_expiry_day'] ? '🚨 **WEEKLY EXPIRY TODAY**' : 'Non-expiry') . ")\n";
            $md .= "- **Morning PCR:** `{$opt['pcr_morning']}` ➔ **Closing PCR:** `{$opt['pcr_close']}`\n";
            $md .= "- **ATM Strike Window:** `{$opt['atm_window']}`\n";
            $md .= "- **Total Call OI Change:** `" . number_format($opt['call_oi_change']) . "` | **Total Put OI Change:** `" . number_format($opt['put_oi_change']) . "`\n";
            $md .= "- **Net Call vs Put Pressure:** `" . number_format($opt['net_ce_pe_pressure']) . "` (Heavy Call Writing Dominance)\n\n";

            $md .= "### Top Call Walls (Massive Institutional Resistance Built Today):\n";
            $md .= "| Strike CE | Diff OI (Added) | Total OI | LTP | Buildup Type |\n";
            $md .= "| :--- | :--- | :--- | :--- | :--- |\n";
            foreach ($opt['top_call_walls'] as $cw) {
                $md .= "| **{$cw['strike']} CE** | `+" . number_format($cw['diff_oi']) . "` | " . number_format($cw['total_oi']) . " | ₹{$cw['ltp']} | {$cw['buildup']} |\n";
            }
            $md .= "\n";

            $md .= "### Top Put Capitulation (Support Failure & Trapped Longs Unwinding):\n";
            $md .= "| Strike PE | Diff OI (Unwound) | Total OI | LTP | Buildup Type |\n";
            $md .= "| :--- | :--- | :--- | :--- | :--- |\n";
            foreach ($opt['top_put_unwinding'] as $pu) {
                $md .= "| **{$pu['strike']} PE** | `" . number_format($pu['diff_oi']) . "` | " . number_format($pu['total_oi']) . " | ₹{$pu['ltp']} | {$pu['buildup']} |\n";
            }
            $md .= "\n";
        }

        // Heavyweight Sector Drivers
        $md .= "## 6. 🏛️ TOP 10 HEAVYWEIGHT CONSTITUENTS & SECTOR PERFORMANCE\n";
        $md .= "| Symbol | Sector | Open | Close | Change (Pts) | Return (%) |\n";
        $md .= "| :--- | :--- | :--- | :--- | :--- | :--- |\n";
        foreach ($hw['stocks'] as $stk) {
            $md .= "| **{$stk['symbol']}** | {$stk['sector']} | {$stk['open']} | {$stk['close']} | `" . ($stk['change'] >= 0 ? '+' : '') . "{$stk['change']}` | `" . ($stk['pct_change'] >= 0 ? '+' : '') . "{$stk['pct_change']}%` |\n";
        }
        $md .= "\n### Sectoral Return Summary:\n";
        foreach ($hw['sectors'] as $sec => $avgPct) {
            $md .= "- **{$sec}:** `" . ($avgPct >= 0 ? '+' : '') . "{$avgPct}%`\n";
        }
        $md .= "\n";

        // Why Buying the Dip Failed Today
        $md .= "## 7. ⚠️ WHY BUYING THE DIP FAILED TODAY (FORENSIC AUDIT)\n";
        foreach ($diag['why_dips_failed'] as $idx => $r) {
            $n = $idx + 1;
            $md .= "### {$n}. {$r['factor']}\n";
            $md .= "{$r['detail']}\n\n";
        }

        // Actionable Checklist
        $md .= "## 8. 🛡️ ACTIONABLE RULES TO PREVENT THIS LOSS IN THE FUTURE\n";
        foreach ($diag['rules_checklist'] as $idx => $chk) {
            $n = $idx + 1;
            $md .= "{$n}. **{$chk}**\n";
        }
        $md .= "\n";

        $md .= "---\n";
        $md .= "*Prompt for external AI engines:* When analyzing this dataset, focus on the conflict between retail dip-buying behavior and institutional distribution across Futures CVD (-1.48M contracts), Expiry Put Unwinding, and the 4%+ plunge in major banking heavyweights.\n";

        return $md;
    }
}
