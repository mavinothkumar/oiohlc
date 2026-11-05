<?php

namespace App\Services\Options;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GammaExposureService
{
    protected int $lotSize = 75; // Standard Nifty Lot Size

    /**
     * Resolve which table to query: option_chains or option_chains_history
     */
    public function resolveTable(string $date, string $symbol = 'NIFTY'): string
    {
        $today = Carbon::today('Asia/Kolkata')->toDateString();
        if ($date === $today) {
            $nowStr = Carbon::now('Asia/Kolkata')->toTimeString();
            if ($nowStr >= '09:15:00' && $nowStr <= '15:40:00') {
                $count = DB::table('option_chains')->where('trading_symbol', $symbol)->count();
                if ($count > 0) {
                    return 'option_chains';
                }
            }
        }
        return 'option_chains_history';
    }

    /**
     * Get available dates using fast indexed nse_working_days
     */
    public function getAvailableDates(string $symbol = 'NIFTY'): array
    {
        $dates = DB::table('nse_working_days')
            ->where('working_date', '<=', Carbon::today('Asia/Kolkata')->toDateString())
            ->orderByDesc('working_date')
            ->take(60)
            ->pluck('working_date')
            ->toArray();

        $today = Carbon::today('Asia/Kolkata')->toDateString();
        if (!in_array($today, $dates)) {
            array_unshift($dates, $today);
        }
        return array_values($dates);
    }

    /**
     * Get latest date with actual snapshot records
     */
    public function getLatestDateWithData(string $symbol = 'NIFTY'): string
    {
        $today = Carbon::today('Asia/Kolkata')->toDateString();
        $nowStr = Carbon::now('Asia/Kolkata')->toTimeString();

        // If currently during or after market hours today, check if live option_chains or history has records
        if ($nowStr >= '09:15:00') {
            $todayLive = DB::table('option_chains')->where('trading_symbol', $symbol)->count();
            if ($todayLive > 0) {
                return $today;
            }
            $todayHist = DB::table('option_chains_history')
                ->where('trading_symbol', $symbol)
                ->where('captured_at', '>=', "{$today} 00:00:00")
                ->count();
            if ($todayHist > 0) {
                return $today;
            }
        }

        $latestHist = DB::table('option_chains_history')
            ->where('trading_symbol', $symbol)
            ->max('captured_at');

        if ($latestHist) {
            return substr($latestHist, 0, 10);
        }

        return $today;
    }

    /**
     * Get available expiries for a date
     */
    public function getExpiries(string $date, string $symbol = 'NIFTY'): array
    {
        $table = $this->resolveTable($date, $symbol);

        $expiries = DB::table($table)
            ->where('trading_symbol', $symbol)
            ->where('captured_at', '>=', "{$date} 00:00:00")
            ->where('captured_at', '<=', "{$date} 23:59:59")
            ->where('expiry', '>=', $date)
            ->select('expiry')
            ->distinct()
            ->orderBy('expiry')
            ->pluck('expiry')
            ->toArray();

        if (empty($expiries)) {
            // Fallback to nse_expiries table
            $expiries = DB::table('nse_expiries')
                ->where('trading_symbol', $symbol)
                ->where('instrument_type', 'OPT')
                ->where('expiry_date', '>=', $date)
                ->orderBy('expiry_date')
                ->take(5)
                ->pluck('expiry_date')
                ->toArray();
        }

        return array_values($expiries);
    }

    /**
     * Calculate Gamma Exposure Matrix and Blast Readiness
     */
    public function calculateGex(string $date, ?string $expiry = null, string $symbol = 'NIFTY', ?string $time = null): array
    {
        $table = $this->resolveTable($date, $symbol);

        // Resolve Expiry if not provided
        if (empty($expiry)) {
            $expiries = $this->getExpiries($date, $symbol);
            $expiry = $expiries[0] ?? $date;
        }

        // Get available snapshot times for this date & expiry using indexed range
        $allTimes = DB::table($table)
            ->where('trading_symbol', $symbol)
            ->where('expiry', $expiry)
            ->where('captured_at', '>=', "{$date} 00:00:00")
            ->where('captured_at', '<=', "{$date} 23:59:59")
            ->select('captured_at')
            ->distinct()
            ->orderBy('captured_at')
            ->pluck('captured_at')
            ->toArray();

        if (empty($allTimes)) {
            return [
                'success'         => false,
                'error'           => "No option chain snapshots found for {$symbol} on {$date} (expiry: {$expiry}). Live snapshots stream after 09:15:00 IST.",
                'date'            => $date,
                'expiry'          => $expiry,
                'timestamp'       => "{$date} 09:15:00",
                'time'            => '09:15',
                'available_times' => [],
                'spot'            => 0.0,
                'lot_size'        => $this->lotSize,
                'summary'         => [
                    'total_call_gex_cr' => 0.0,
                    'total_put_gex_cr'  => 0.0,
                    'total_net_gex_cr'  => 0.0,
                    'call_wall'         => ['strike' => 0, 'gex_cr' => 0.0, 'label' => 'Awaiting Market Open'],
                    'put_wall'          => ['strike' => 0, 'gex_cr' => 0.0, 'label' => 'Awaiting Market Open'],
                    'zero_gamma_flip'   => 0.0,
                    'max_gamma_strike'  => 0,
                    'regime'            => 'NEUTRAL',
                    'regime_title'      => 'AWAITING LIVE MARKET SNAPSHOTS',
                    'regime_desc'       => 'Live Option Chain snapshots begin streaming at 09:15:00 IST. Select previous date to inspect historical gamma matrix.',
                    'regime_color'      => 'text-slate-400',
                    'regime_bg'         => 'bg-slate-800/40 border-slate-700/50',
                ],
                'top_blast_setups' => [],
                'ladder'          => [],
                'timeline'        => [],
            ];
        }

        // Selected Snapshot Timestamp
        $selectedTs = $time ? "{$date} {$time}" : end($allTimes);
        // Normalize time if HH:mm was sent without :00
        if (strlen($selectedTs) === 16) {
            $selectedTs .= ':00';
        }
        if (!in_array($selectedTs, $allTimes)) {
            $selectedTs = end($allTimes);
        }

        // Retrieve all rows for this specific snapshot
        $rows = DB::table($table)
            ->where('trading_symbol', $symbol)
            ->where('expiry', $expiry)
            ->where('captured_at', $selectedTs)
            ->get();

        if ($rows->isEmpty()) {
            return [
                'success'         => false,
                'error'           => "Snapshot data empty for {$selectedTs}",
                'date'            => $date,
                'expiry'          => $expiry,
                'timestamp'       => $selectedTs,
                'time'            => substr($selectedTs, 11, 5),
                'available_times' => array_map(fn($t) => substr($t, 11, 5), $allTimes),
                'spot'            => 0.0,
                'lot_size'        => $this->lotSize,
                'summary'         => [
                    'total_call_gex_cr' => 0.0,
                    'total_put_gex_cr'  => 0.0,
                    'total_net_gex_cr'  => 0.0,
                    'call_wall'         => ['strike' => 0, 'gex_cr' => 0.0, 'label' => 'No Data'],
                    'put_wall'          => ['strike' => 0, 'gex_cr' => 0.0, 'label' => 'No Data'],
                    'zero_gamma_flip'   => 0.0,
                    'max_gamma_strike'  => 0,
                    'regime'            => 'NEUTRAL',
                    'regime_title'      => 'NO DATA FOR SNAPSHOT',
                    'regime_desc'       => 'Snapshot contains 0 records for selected timestamp.',
                    'regime_color'      => 'text-slate-400',
                    'regime_bg'         => 'bg-slate-800/40 border-slate-700/50',
                ],
                'top_blast_setups' => [],
                'ladder'          => [],
                'timeline'        => [],
            ];
        }

        // Parse strikes
        $spot = 0;
        $strikesMap = [];

        foreach ($rows as $r) {
            $strike = (float) $r->strike_price;
            $type = strtoupper($r->option_type);
            if ($spot == 0 && !empty($r->underlying_spot_price) && (float)$r->underlying_spot_price > 0) {
                $spot = (float) $r->underlying_spot_price;
            }

            if (!isset($strikesMap[$strike])) {
                $strikesMap[$strike] = [
                    'strike' => $strike,
                    'CE'     => ['oi' => 0, 'gamma' => 0.0, 'delta' => 0.0, 'ltp' => 0.0, 'iv' => 0.0, 'vol' => 0, 'diff_oi' => 0, 'diff_vol' => 0],
                    'PE'     => ['oi' => 0, 'gamma' => 0.0, 'delta' => 0.0, 'ltp' => 0.0, 'iv' => 0.0, 'vol' => 0, 'diff_oi' => 0, 'diff_vol' => 0],
                ];
            }

            $strikesMap[$strike][$type] = [
                'oi'       => (int) ($r->oi ?? 0),
                'gamma'    => (float) ($r->gamma ?? 0),
                'delta'    => (float) ($r->delta ?? 0),
                'ltp'      => (float) ($r->ltp ?? 0),
                'iv'       => (float) ($r->iv ?? 0),
                'vol'      => (int) ($r->volume ?? 0),
                'diff_oi'  => (int) ($r->diff_oi ?? 0),
                'diff_vol' => (int) ($r->diff_volume ?? 0),
            ];
        }

        ksort($strikesMap);

        // Fallback for spot if 0
        if ($spot == 0) {
            $minDiff = PHP_FLOAT_MAX;
            foreach ($strikesMap as $strk => $stData) {
                $diff = abs($stData['CE']['ltp'] - $stData['PE']['ltp']);
                if ($diff < $minDiff && $stData['CE']['ltp'] > 0 && $stData['PE']['ltp'] > 0) {
                    $minDiff = $diff;
                    $spot = $strk;
                }
            }
        }

        // Metrics Accumulator
        $totalCallGex = 0;
        $totalPutGex = 0;
        $totalNetGex = 0;
        $callWallStrike = 0;
        $callWallVal = -PHP_FLOAT_MAX;
        $putWallStrike = 0;
        $putWallVal = -PHP_FLOAT_MAX;
        $maxTotalGammaStrike = 0;
        $maxTotalGammaVal = -PHP_FLOAT_MAX;

        $ladder = [];
        $maxGammaInChain = 0.0001;
        $maxOiInChain = 1;

        // First pass: GEX and max values
        foreach ($strikesMap as $strike => $data) {
            $callGamma = $data['CE']['gamma'];
            $putGamma  = $data['PE']['gamma'];
            $callOi    = $data['CE']['oi'];
            $putOi     = $data['PE']['oi'];

            if ($callGamma > $maxGammaInChain) $maxGammaInChain = $callGamma;
            if ($putGamma > $maxGammaInChain)  $maxGammaInChain = $putGamma;
            if ($callOi > $maxOiInChain)       $maxOiInChain = $callOi;
            if ($putOi > $maxOiInChain)        $maxOiInChain = $putOi;

            // GEX in INR Crores: (OI * Gamma * Spot * LotSize) / 10^7
            $callGexCr = ($callOi * $callGamma * $spot * $this->lotSize) / 10000000;
            $putGexCr  = - ($putOi * $putGamma * $spot * $this->lotSize) / 10000000;
            $netGexCr  = $callGexCr + $putGexCr;

            $totalCallGex += $callGexCr;
            $totalPutGex  += $putGexCr;
            $totalNetGex  += $netGexCr;

            if ($callGexCr > $callWallVal) {
                $callWallVal = $callGexCr;
                $callWallStrike = $strike;
            }
            if (abs($putGexCr) > $putWallVal) {
                $putWallVal = abs($putGexCr);
                $putWallStrike = $strike;
            }

            $combGamma = $callGamma + $putGamma;
            if ($combGamma > $maxTotalGammaVal) {
                $maxTotalGammaVal = $combGamma;
                $maxTotalGammaStrike = $strike;
            }

            $ladder[] = [
                'strike'      => $strike,
                'spot_diff'   => round($strike - $spot, 2),
                'call_oi'     => $callOi,
                'put_oi'      => $putOi,
                'call_gamma'  => $callGamma,
                'put_gamma'   => $putGamma,
                'call_gex_cr' => round($callGexCr, 2),
                'put_gex_cr'  => round($putGexCr, 2),
                'net_gex_cr'  => round($netGexCr, 2),
                'ce_ltp'      => $data['CE']['ltp'],
                'pe_ltp'      => $data['PE']['ltp'],
                'ce_delta'    => $data['CE']['delta'],
                'pe_delta'    => $data['PE']['delta'],
                'ce_iv'       => $data['CE']['iv'],
                'pe_iv'       => $data['PE']['iv'],
                'ce_vol'      => $data['CE']['vol'],
                'pe_vol'      => $data['PE']['vol'],
                'ce_diff_oi'  => $data['CE']['diff_oi'],
                'pe_diff_oi'  => $data['PE']['diff_oi'],
                'blast_score' => 0,
                'blast_type'  => 'NONE',
            ];
        }

        // Calculate Zero Gamma Flip Level (Interpolate where Net GEX crosses 0 closest to Spot)
        $flipPoint = $this->calculateZeroGammaFlip($ladder, $spot);

        // Second pass: Calculate Strike Blast Readiness Scores (for strikes within +- 350 pts of spot)
        $blastCandidates = [];

        foreach ($ladder as &$stk) {
            $dist = abs($stk['spot_diff']);
            if ($dist <= 350) {
                $distScore = max(0, 100 - ($dist / 350 * 100));
                $gammaRatio = max($stk['call_gamma'], $stk['put_gamma']) / $maxGammaInChain;
                $gammaScore = min(100, $gammaRatio * 100);
                $oiRatio = max($stk['call_oi'], $stk['put_oi']) / $maxOiInChain;
                $oiScore = min(100, $oiRatio * 100);

                $totalVol = $stk['ce_vol'] + $stk['pe_vol'];
                $volScore = min(100, log10(max(1, $totalVol)) * 20);

                $composite = (0.35 * $distScore) + (0.25 * $gammaScore) + (0.25 * $oiScore) + (0.15 * $volScore);
                $stk['blast_score'] = round(min(99, max(15, $composite)), 1);

                if ($stk['strike'] >= $spot) {
                    $stk['blast_type'] = 'BULL_CALL_SQUEEZE';
                    $blastCandidates[] = [
                        'strike'       => $stk['strike'],
                        'option'       => "NIFTY {$stk['strike']} CE",
                        'direction'    => 'BULLISH',
                        'type'         => 'Call Gamma Squeeze (Short Covering Fuel)',
                        'score'        => $stk['blast_score'],
                        'ltp'          => $stk['ce_ltp'],
                        'delta'        => $stk['ce_delta'],
                        'gamma'        => $stk['call_gamma'],
                        'trigger'      => "Spot breaks above ₹" . number_format($spot + 15, 0),
                        'target_spot'  => round($stk['strike'] + 45, 0),
                        'target_opt'   => round($stk['ce_ltp'] * 1.55, 1),
                        'stop_loss'    => round($stk['ce_ltp'] * 0.70, 1),
                        'spot_sl'      => round($spot - 25, 0),
                        'description'  => "Heavy Call writing at ₹{$stk['strike']} is vulnerable to short-squeeze. Delta expansion forces aggressive automated hedging.",
                    ];
                } else {
                    $stk['blast_type'] = 'BEAR_PUT_SQUEEZE';
                    $blastCandidates[] = [
                        'strike'       => $stk['strike'],
                        'option'       => "NIFTY {$stk['strike']} PE",
                        'direction'    => 'BEARISH',
                        'type'         => 'Put Gamma Squeeze (Long Unwinding / Flush)',
                        'score'        => $stk['blast_score'],
                        'ltp'          => $stk['pe_ltp'],
                        'delta'        => $stk['pe_delta'],
                        'gamma'        => $stk['put_gamma'],
                        'trigger'      => "Spot drops below ₹" . number_format($spot - 15, 0),
                        'target_spot'  => round($stk['strike'] - 45, 0),
                        'target_opt'   => round($stk['pe_ltp'] * 1.55, 1),
                        'stop_loss'    => round($stk['pe_ltp'] * 0.70, 1),
                        'spot_sl'      => round($spot + 25, 0),
                        'description'  => "Heavy Put writing at ₹{$stk['strike']} is vulnerable to long liquidation. A drop beneath this level triggers a liquidity cascade.",
                    ];
                }
            }
        }
        unset($stk);

        // Sort Blast candidates by score desc
        usort($blastCandidates, fn($a, $b) => $b['score'] <=> $a['score']);
        $topBlastSetups = array_slice($blastCandidates, 0, 3);

        // Determine Market Regime
        $regime = 'NEUTRAL';
        $regimeTitle = 'BALANCED GAMMA ZONE';
        $regimeDesc = 'Net Gamma is balanced. Expect standard mean-reversion within value area.';
        $regimeColor = 'text-amber-400';
        $regimeBg = 'bg-amber-500/10 border-amber-500/30';

        if ($totalNetGex > 500) {
            $regime = 'POSITIVE_GAMMA';
            $regimeTitle = 'POSITIVE GAMMA REGIME (+GEX) — VOLATILITY DAMPENER';
            $regimeDesc = 'Market Makers are Long Gamma. They sell into rallies and buy into dips. Expect compressed ranges, sticky strikes, and false breakouts. Best strategy: Fade extremes / Sell premium.';
            $regimeColor = 'text-emerald-400';
            $regimeBg = 'bg-emerald-500/10 border-emerald-500/30';
        } elseif ($totalNetGex < -500) {
            $regime = 'NEGATIVE_GAMMA';
            $regimeTitle = 'NEGATIVE GAMMA REGIME (-GEX) — VOLATILITY ACCELERATOR';
            $regimeDesc = 'Market Makers are Short Gamma. They are forced to buy into rallies and sell into declines. Volatility expands aggressively. Best strategy: Breakout scalps / Momentum option buying.';
            $regimeColor = 'text-rose-400';
            $regimeBg = 'bg-rose-500/10 border-rose-500/30';
        }

        // Intraday Timeline / History
        $timeline = $this->getIntradayTimeline($table, $date, $expiry, $symbol);

        return [
            'success' => true,
            'date' => $date,
            'expiry' => $expiry,
            'timestamp' => $selectedTs,
            'time' => substr($selectedTs, 11, 5),
            'available_times' => array_map(fn($t) => substr($t, 11, 5), $allTimes),
            'spot' => $spot,
            'lot_size' => $this->lotSize,
            'summary' => [
                'total_call_gex_cr' => round($totalCallGex, 2),
                'total_put_gex_cr'  => round($totalPutGex, 2),
                'total_net_gex_cr'  => round($totalNetGex, 2),
                'call_wall'         => [
                    'strike' => $callWallStrike,
                    'gex_cr' => round($callWallVal, 2),
                    'label'  => 'Major Ceiling / Pin Resistance',
                ],
                'put_wall'          => [
                    'strike' => $putWallStrike,
                    'gex_cr' => round($putWallVal, 2),
                    'label'  => 'Major Floor / Absorption Support',
                ],
                'zero_gamma_flip'   => round($flipPoint, 2),
                'max_gamma_strike'  => $maxTotalGammaStrike,
                'regime'            => $regime,
                'regime_title'      => $regimeTitle,
                'regime_desc'       => $regimeDesc,
                'regime_color'      => $regimeColor,
                'regime_bg'         => $regimeBg,
            ],
            'top_blast_setups' => $topBlastSetups,
            'ladder' => $ladder,
            'timeline' => $timeline,
        ];
    }

    /**
     * Calculate Zero Gamma Flip Point closest to Spot
     */
    protected function calculateZeroGammaFlip(array $ladder, float $spot): float
    {
        if (empty($ladder)) return $spot;

        // Filter ladder around spot (+- 600 points) to avoid far OTM noise
        $activeStrikes = array_filter($ladder, fn($item) => abs($item['spot_diff']) <= 600);
        if (empty($activeStrikes)) {
            $activeStrikes = $ladder;
        }

        $flip = $spot;
        $minDist = PHP_FLOAT_MAX;
        $prev = null;

        foreach ($activeStrikes as $curr) {
            if ($prev !== null) {
                if (($prev['net_gex_cr'] <= 0 && $curr['net_gex_cr'] > 0) ||
                    ($prev['net_gex_cr'] >= 0 && $curr['net_gex_cr'] < 0)) {
                    
                    $denom = abs($curr['net_gex_cr'] - $prev['net_gex_cr']);
                    if ($denom > 0) {
                        $factor = abs($prev['net_gex_cr']) / $denom;
                        $strikeSpan = $curr['strike'] - $prev['strike'];
                        $interpolated = $prev['strike'] + ($factor * $strikeSpan);
                        $dist = abs($interpolated - $spot);
                        if ($dist < $minDist) {
                            $minDist = $dist;
                            $flip = $interpolated;
                        }
                    }
                }
            }
            $prev = $curr;
        }

        return $flip;
    }

    /**
     * Intraday GEX Timeline using fast index range scan
     */
    protected function getIntradayTimeline(string $table, string $date, string $expiry, string $symbol): array
    {
        $rawRows = DB::table($table)
            ->where('trading_symbol', $symbol)
            ->where('expiry', $expiry)
            ->where('captured_at', '>=', "{$date} 00:00:00")
            ->where('captured_at', '<=', "{$date} 23:59:59")
            ->selectRaw("
                captured_at,
                underlying_spot_price,
                SUM(CASE WHEN option_type = 'CE' THEN oi * gamma * underlying_spot_price * {$this->lotSize} / 10000000 ELSE 0 END) as call_gex,
                SUM(CASE WHEN option_type = 'PE' THEN - oi * gamma * underlying_spot_price * {$this->lotSize} / 10000000 ELSE 0 END) as put_gex
            ")
            ->groupBy('captured_at', 'underlying_spot_price')
            ->orderBy('captured_at')
            ->get();

        $timeline = [];
        foreach ($rawRows as $r) {
            $timeStr = substr($r->captured_at, 11, 5);
            $callGex = (float) $r->call_gex;
            $putGex = (float) $r->put_gex;
            $netGex = $callGex + $putGex;

            $timeline[] = [
                'captured_at' => $r->captured_at,
                'time'        => $timeStr,
                'spot'        => (float) $r->underlying_spot_price,
                'call_gex'    => round($callGex, 2),
                'put_gex'     => round($putGex, 2),
                'net_gex'     => round($netGex, 2),
            ];
        }

        return $timeline;
    }
}
