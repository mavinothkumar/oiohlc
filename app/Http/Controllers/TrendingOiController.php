<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TrendingOiController extends Controller
{
    /**
     * Resolve the database table name based on mode.
     */
    private function resolveTable(string $mode): string
    {
        if ($mode === 'history') {
            return 'option_chains_history';
        }
        return getTableName('option_chains');
    }

    /**
     * Main Trending OI view.
     */
    public function index(Request $request)
    {
        $workingDay = DB::table('nse_working_days')->where('current', 1)->first()
            ?? DB::table('nse_working_days')->where('previous', 1)->orderByDesc('working_date')->first();

        $today = $workingDay ? $workingDay->working_date : today()->toDateString();

        $defaultExpiry = DB::table('nse_expiries')
            ->where('trading_symbol', 'NIFTY')
            ->where('instrument_type', 'OPT')
            ->where('is_current', 1)
            ->value('expiry_date') ?? $today;

        $table = getTableName('option_chains');
        $expiries = DB::table($table)
            ->where('underlying_key', 'NSE_INDEX|Nifty 50')
            ->select('expiry')
            ->distinct()
            ->orderBy('expiry')
            ->pluck('expiry');

        if ($expiries->isEmpty()) {
            $expiries = DB::table('nse_expiries')
                ->where('trading_symbol', 'NIFTY')
                ->where('instrument_type', 'OPT')
                ->orderBy('expiry_date')
                ->pluck('expiry_date');
        }

        $selectedDate   = $request->input('date', $today);
        $selectedExpiry = $request->input('expiry', $defaultExpiry);
        $mode           = $request->input('mode', 'live');
        $interval       = $request->input('interval', '5');

        return view('trending-oi', compact(
            'today',
            'selectedDate',
            'selectedExpiry',
            'expiries',
            'mode',
            'interval'
        ));
    }

    /**
     * AJAX: Get time series data and summary for Trending OI.
     */
    public function getData(Request $request)
    {
        $mode       = $request->input('mode', 'live');
        $expiry     = $request->input('expiry');
        $date       = $request->input('date', today()->toDateString());
        $underlying = $request->input('underlying', 'NSE_INDEX|Nifty 50');
        $interval   = (int) $request->input('interval', 5); // 1, 3, 5, 15
        $customStrikes = $request->input('strikes'); // optional comma separated or array

        if (!$expiry) {
            return response()->json(['error' => 'Expiry is required'], 422);
        }

        $table = $this->resolveTable($mode);
        $endTime = $request->input('end_time');

        // Fetch distinct timestamps available for this date & expiry
        $tsQuery = DB::table($table)
            ->where('underlying_key', $underlying)
            ->where('expiry', $expiry)
            ->whereDate('captured_at', $date);

        if (!empty($endTime)) {
            $tsQuery->whereTime('captured_at', '<=', $endTime . ':59');
        }

        $timestamps = $tsQuery->select('captured_at')
            ->distinct()
            ->orderBy('captured_at', 'asc')
            ->pluck('captured_at')
            ->toArray();

        if (empty($timestamps)) {
            return response()->json([
                'rows'              => [],
                'chart'             => ['labels' => [], 'call_oi_chg' => [], 'put_oi_chg' => [], 'sentiment' => [], 'spot' => []],
                'selected_strikes'  => [],
                'all_strikes'       => [],
                'underlying_data'   => null,
                'atm_strike'        => null,
            ]);
        }

        // Get latest available snapshot for current spot price & available strikes
        $latestTimestamp = end($timestamps);
        $latestRows = DB::table($table)
            ->where('underlying_key', $underlying)
            ->where('expiry', $expiry)
            ->where('captured_at', $latestTimestamp)
            ->get(['strike_price', 'underlying_spot_price', 'option_type', 'ltp', 'oi']);

        $spot = (float) ($latestRows->first()->underlying_spot_price ?? 0);
        $atmStrike = (int) (round($spot / 50) * 50);

        // All available strikes for this expiry
        $allStrikes = DB::table($table)
            ->where('underlying_key', $underlying)
            ->where('expiry', $expiry)
            ->whereDate('captured_at', $date)
            ->select('strike_price')
            ->distinct()
            ->orderBy('strike_price')
            ->pluck('strike_price')
            ->map(fn($s) => (int) $s)
            ->toArray();

        // Determine selected strikes (15 strikes around ATM by default)
        if (!empty($customStrikes)) {
            if (is_string($customStrikes)) {
                $selectedStrikes = array_map('intval', explode(',', $customStrikes));
            } else {
                $selectedStrikes = array_map('intval', (array) $customStrikes);
            }
            sort($selectedStrikes);
        } else {
            // Find ATM index in allStrikes
            $atmIdx = array_search($atmStrike, $allStrikes);
            if ($atmIdx === false) {
                // Nearest strike
                $closestIdx = 0;
                $minDiff = PHP_INT_MAX;
                foreach ($allStrikes as $idx => $s) {
                    $diff = abs($s - $atmStrike);
                    if ($diff < $minDiff) {
                        $minDiff = $diff;
                        $closestIdx = $idx;
                    }
                }
                $atmIdx = $closestIdx;
            }

            $startIdx = max(0, $atmIdx - 7);
            $selectedStrikes = array_slice($allStrikes, $startIdx, 15);
        }

        // Filter timestamps according to selected interval (if interval > 1, sample appropriately)
        $sampledTimestamps = [];
        $lastMinute = -1;
        foreach ($timestamps as $ts) {
            $carbon = Carbon::parse($ts);
            $minute = (int) $carbon->format('i');
            if ($interval <= 1 || ($minute % $interval === 0 && $minute !== $lastMinute) || $ts === end($timestamps) || $ts === reset($timestamps)) {
                $sampledTimestamps[] = $ts;
                $lastMinute = $minute;
            }
        }
        if (empty($sampledTimestamps)) {
            $sampledTimestamps = $timestamps;
        }

        // Fetch all rows for selected strikes at the sampled timestamps
        $dataRows = DB::table($table)
            ->where('underlying_key', $underlying)
            ->where('expiry', $expiry)
            ->whereDate('captured_at', $date)
            ->whereIn('captured_at', $sampledTimestamps)
            ->whereIn('strike_price', $selectedStrikes)
            ->get([
                'captured_at',
                'strike_price',
                'option_type',
                'oi',
                'diff_oi',
                'ltp',
                'diff_ltp',
                'underlying_spot_price',
                'close_price'
            ]);

        // Group rows by captured_at
        $grouped = [];
        foreach ($dataRows as $r) {
            $ts = $r->captured_at;
            if (!isset($grouped[$ts])) {
                $grouped[$ts] = [
                    'captured_at' => $ts,
                    'spot'        => (float) $r->underlying_spot_price,
                    'CE'          => ['oi' => 0, 'ltp' => 0],
                    'PE'          => ['oi' => 0, 'ltp' => 0],
                ];
            }
            $type = $r->option_type;
            if (isset($grouped[$ts][$type])) {
                $grouped[$ts][$type]['oi']  += (int) ($r->oi ?? 0);
                $grouped[$ts][$type]['ltp'] += (float) ($r->ltp ?? 0);
            }
        }

        if (empty($grouped)) {
            return response()->json([
                'rows'             => [],
                'chart'            => ['labels' => [], 'call_oi_chg' => [], 'put_oi_chg' => [], 'sentiment' => [], 'spot' => []],
                'selected_strikes' => $selectedStrikes,
                'all_strikes'      => $allStrikes,
                'underlying_data'  => null,
                'atm_strike'       => $atmStrike,
            ]);
        }

        // Sort grouped data chronologically (ascending) for base and cumulative calculations
        ksort($grouped);

        // Determine base snapshot (09:15:00 or first available)
        $firstEntry = reset($grouped);
        $baseCeOi   = $firstEntry['CE']['oi'];
        $basePeOi   = $firstEntry['PE']['oi'];
        $baseCeLtp  = $firstEntry['CE']['ltp'];
        $basePeLtp  = $firstEntry['PE']['ltp'];
        $baseSpot   = $firstEntry['spot'];

        // Track running Day High & Low for Spot and for Diff in OI
        $runningDayHighSpot = $baseSpot;
        $runningDayLowSpot  = $baseSpot;
        $runningMaxDiffOi   = null;
        $runningMinDiffOi   = null;

        $previousDiffOi = null;
        $calculatedRows = [];

        foreach ($grouped as $ts => $g) {
            $spotVal = $g['spot'];
            $curCeOi = $g['CE']['oi'];
            $curPeOi = $g['PE']['oi'];
            $curCeLtp = round($g['CE']['ltp'], 2);
            $curPeLtp = round($g['PE']['ltp'], 2);

            // 1. Chng in Call & Put OI
            $chngCallOi = $curCeOi - $baseCeOi;
            $chngPutOi  = $curPeOi - $basePeOi;

            // 2. Diff in OI (Put Chng - Call Chng)
            $diffOi = $chngPutOi - $chngCallOi;

            // 3. Direction of Chng & Chng in Direction
            $chngInDirection = ($previousDiffOi !== null) ? ($diffOi - $previousDiffOi) : 0;
            $directionPct = 0;
            if ($previousDiffOi !== null && $previousDiffOi != 0) {
                $directionPct = round(($chngInDirection / abs($previousDiffOi)) * 100, 2);
            }

            // 4. Strength: Difference / (abs(Call Chng) + abs(Put Chng)) * 100
            $totalChngOi = abs($chngCallOi) + abs($chngPutOi);
            $strengthPct = $totalChngOi > 0 ? round(($diffOi / $totalChngOi) * 100) : 0;

            // 5. LTP metrics
            $callLtpChng = round($curCeLtp - $baseCeLtp, 2);
            $putLtpChng  = round($curPeLtp - $basePeLtp, 2);
            $cePeLtpChng = round($callLtpChng + $putLtpChng, 2);

            // 6. Net PCR
            $netPcr = $chngCallOi != 0 ? round($chngPutOi / $chngCallOi, 2) : ($curCeOi > 0 ? round($curPeOi / $curCeOi, 2) : 0);

            // 7. Day H/L Break for Spot
            $dayHlBreak = '-';
            if ($spotVal > $runningDayHighSpot) {
                $runningDayHighSpot = $spotVal;
                $dayHlBreak = 'D.H.B. (' . number_format($spotVal, 2) . ')';
            } elseif ($spotVal < $runningDayLowSpot) {
                $runningDayLowSpot = $spotVal;
                $dayHlBreak = 'D.L.B. (' . number_format($spotVal, 2) . ')';
            }

            // 8. Day H/L Diff in OI
            $dayHlDiffOi = '-';
            if ($runningMaxDiffOi === null || $diffOi > $runningMaxDiffOi) {
                $runningMaxDiffOi = $diffOi;
                if ($previousDiffOi !== null) {
                    $dayHlDiffOi = 'D.H.B';
                }
            }
            if ($runningMinDiffOi === null || $diffOi < $runningMinDiffOi) {
                $runningMinDiffOi = $diffOi;
                if ($previousDiffOi !== null) {
                    $dayHlDiffOi = 'D.L.B';
                }
            }

            // 9. Sentiment
            $sentiment = $diffOi >= 0 ? 'Bullish' : 'Bearish';

            $cTime = Carbon::parse($ts);

            $calculatedRows[] = [
                'date'              => $cTime->format('d-m-Y'),
                'time'              => $cTime->format('H:i:s'),
                'time_short'        => $cTime->format('H:i'),
                'timestamp'         => $ts,
                'spot'              => $spotVal,
                'day_hl_break'      => $dayHlBreak,
                'chng_call_oi'      => $chngCallOi,
                'chng_put_oi'       => $chngPutOi,
                'diff_oi'           => $diffOi,
                'strength'          => $strengthPct,
                'direction_pct'     => $directionPct,
                'chng_in_direction' => $chngInDirection,
                'total_call_ltp'    => $curCeLtp,
                'call_ltp_chng'     => $callLtpChng,
                'ce_pe_ltp_chng'    => $cePeLtpChng,
                'put_ltp_chng'      => $putLtpChng,
                'total_put_ltp'     => $curPeLtp,
                'net_pcr'           => $netPcr,
                'day_hl_diff_oi'    => $dayHlDiffOi,
                'sentiment'         => $sentiment,
            ];

            $previousDiffOi = $diffOi;
        }

        $lookback   = $request->input('lookback', 'auto');

        // Add pattern detection to calculated rows
        $this->tagRowPatterns($calculatedRows, $lookback);

        // Prepare chart payload (in chronological ascending order)
        $chartLabels    = [];
        $chartCallOi    = [];
        $chartPutOi     = [];
        $chartSentiment = [];
        $chartSpot      = [];

        foreach ($calculatedRows as $row) {
            $chartLabels[]    = $row['time_short'];
            $chartCallOi[]    = $row['chng_call_oi'];
            $chartPutOi[]     = $row['chng_put_oi'];
            $chartSentiment[] = $row['diff_oi'];
            $chartSpot[]      = $row['spot'];
        }

        // Prepare table rows (in descending order: latest time at the top)
        $tableRows = array_reverse($calculatedRows);

        // Calculate spot change relative to first bar
        $latestSpot = end($calculatedRows)['spot'] ?? 0;
        $spotChange = $latestSpot - $baseSpot;
        $spotChangePct = $baseSpot > 0 ? round(($spotChange / $baseSpot) * 100, 2) : 0;

        $underlyingData = [
            'name'       => 'NIFTY 50',
            'spot'       => $latestSpot,
            'change'     => round($spotChange, 2),
            'change_pct' => $spotChangePct,
            'time'       => Carbon::parse($latestTimestamp)->format('d M Y, H:i:s') . ' IST',
        ];

        // Run Predictive OI Signal & Market State Engine
        $signalData = $this->analyzeOiSignals($calculatedRows, $latestSpot, $atmStrike, $lookback);

        // Run Daily High-Conviction Strategy Station (Option Seller Cockpit)
        $strategyStation = $this->evaluateDailyStrategyStation(
            $calculatedRows,
            $latestSpot,
            $atmStrike,
            $latestTimestamp,
            $table,
            $underlying,
            $expiry
        );

        // Consolidate chronological signals for the session into strategy_call_logs
        $this->syncChronologicalStrategyCalls(
            $calculatedRows,
            $table,
            $underlying,
            $expiry,
            $strategyStation
        );

        // Calculate Multi-Timeframe Strike OI Buildup (5M, 15M, 30M, Today)
        $strikeBuildup = $this->calculateMultiTimeframeBuildup(
            $table,
            $underlying,
            $expiry,
            $timestamps,
            $atmStrike
        );

        $tradeDate = Carbon::parse($latestTimestamp)->toDateString();
        $strategyCallsCount = DB::table('strategy_call_logs')
            ->where('trade_date', $tradeDate)
            ->where('underlying', $underlying)
            ->count();

        return response()->json([
            'rows'                   => $tableRows,
            'chart'                  => [
                'labels'     => $chartLabels,
                'call_oi'    => $chartCallOi,
                'put_oi'     => $chartPutOi,
                'sentiment'  => $chartSentiment,
                'spot'       => $chartSpot,
            ],
            'selected_strikes'       => $selectedStrikes,
            'all_strikes'            => $allStrikes,
            'underlying_data'        => $underlyingData,
            'atm_strike'             => $atmStrike,
            'signal_data'            => $signalData,
            'daily_strategy_station' => $strategyStation,
            'strategy_calls_count'   => $strategyCallsCount,
            'strike_buildup'         => $strikeBuildup,
            'strike_buildup_5m'      => $strikeBuildup['5m'] ?? null,
        ]);
    }

    /**
     * Tag individual rows with pattern recognition markers (Dynamic multi-bar and configurable threshold).
     */
    private function tagRowPatterns(array &$rows, string $lookback = 'auto'): void
    {
        $count = count($rows);
        if ($count === 0) return;

        $minThresholdBars = ($lookback !== 'auto' && is_numeric($lookback)) ? (int) $lookback : 3;
        $runningMaxCe = -PHP_INT_MAX;
        $runningMaxPe = -PHP_INT_MAX;

        for ($i = 0; $i < $count; $i++) {
            $r = &$rows[$i];
            $curCe = $r['chng_call_oi'];
            $curPe = $r['chng_put_oi'];

            $tags = [];

            // Track rolling highs
            $isCeNewHigh = false;
            $isPeNewHigh = false;
            if ($curCe > $runningMaxCe) {
                $runningMaxCe = $curCe;
                if ($i > 1 && $curCe > 1000000) {
                    $tags[] = 'CE New High';
                    $isCeNewHigh = true;
                }
            }
            if ($curPe > $runningMaxPe) {
                $runningMaxPe = $curPe;
                if ($i > 1 && $curPe > 1000000) {
                    $tags[] = 'PE New High';
                    $isPeNewHigh = true;
                }
            }

            if ($i >= 3) {
                // Calculate CE Low Break depth: how many consecutive preceding bars had higher CE OI
                $ceLowDepth = 0;
                $maxPrecedingCe = $curCe;
                for ($k = $i - 1; $k >= 0; $k--) {
                    if ($rows[$k]['chng_call_oi'] > $curCe) {
                        $ceLowDepth++;
                        if ($rows[$k]['chng_call_oi'] > $maxPrecedingCe) {
                            $maxPrecedingCe = $rows[$k]['chng_call_oi'];
                        }
                    } else {
                        break;
                    }
                }

                // Calculate CE High Break depth: how many consecutive preceding bars had lower CE OI
                $ceHighDepth = 0;
                $minPrecedingCe = $curCe;
                for ($k = $i - 1; $k >= 0; $k--) {
                    if ($rows[$k]['chng_call_oi'] < $curCe) {
                        $ceHighDepth++;
                        if ($rows[$k]['chng_call_oi'] < $minPrecedingCe) {
                            $minPrecedingCe = $rows[$k]['chng_call_oi'];
                        }
                    } else {
                        break;
                    }
                }

                // Calculate PE Low Break depth: how many consecutive preceding bars had higher PE OI
                $peLowDepth = 0;
                $maxPrecedingPe = $curPe;
                for ($k = $i - 1; $k >= 0; $k--) {
                    if ($rows[$k]['chng_put_oi'] > $curPe) {
                        $peLowDepth++;
                        if ($rows[$k]['chng_put_oi'] > $maxPrecedingPe) {
                            $maxPrecedingPe = $rows[$k]['chng_put_oi'];
                        }
                    } else {
                        break;
                    }
                }

                // Calculate PE High Break depth: how many consecutive preceding bars had lower PE OI
                $peHighDepth = 0;
                $minPrecedingPe = $curPe;
                for ($k = $i - 1; $k >= 0; $k--) {
                    if ($rows[$k]['chng_put_oi'] < $curPe) {
                        $peHighDepth++;
                        if ($rows[$k]['chng_put_oi'] < $minPrecedingPe) {
                            $minPrecedingPe = $rows[$k]['chng_put_oi'];
                        }
                    } else {
                        break;
                    }
                }

                // Tag CE breaks
                if ($ceLowDepth >= $minThresholdBars && ($maxPrecedingCe - $curCe) >= 750000) {
                    $label = $ceLowDepth >= 8 ? "⚡ CE 8+ Bar Major Low Break" : "⚡ CE {$ceLowDepth}-Bar Low Break";
                    $tags[] = $label;
                } elseif ($ceHighDepth >= $minThresholdBars && ($curCe - $minPrecedingCe) >= 750000 && !$isCeNewHigh) {
                    $label = $ceHighDepth >= 8 ? "CE 8+ Bar Major High Break" : "CE {$ceHighDepth}-Bar High Break";
                    $tags[] = $label;
                }

                // Tag PE breaks
                if ($peHighDepth >= $minThresholdBars && ($curPe - $minPrecedingPe) >= 750000 && !$isPeNewHigh) {
                    $label = $peHighDepth >= 8 ? "PE 8+ Bar Major High Break" : "PE {$peHighDepth}-Bar High Break";
                    $tags[] = $label;
                } elseif ($peLowDepth >= $minThresholdBars && ($maxPrecedingPe - $curPe) >= 750000) {
                    $label = $peLowDepth >= 8 ? "⚠️ PE 8+ Bar Major Low Break" : "⚠️ PE {$peLowDepth}-Bar Low Break";
                    $tags[] = $label;
                }
            }

            $r['pattern_tag'] = !empty($tags) ? implode(' | ', $tags) : '-';

            // Evaluate Forward Sentiment / 30m-1h Market Outlook & Option Seller Playbook
            $tagStr = $r['pattern_tag'];
            $hasCeLowBreak  = str_contains($tagStr, 'CE') && str_contains($tagStr, 'Low Break');
            $hasCeHighBreak = str_contains($tagStr, 'CE') && (str_contains($tagStr, 'High Break') || str_contains($tagStr, 'CE New High'));
            $hasPeLowBreak  = str_contains($tagStr, 'PE') && str_contains($tagStr, 'Low Break');
            $hasPeHighBreak = str_contains($tagStr, 'PE') && (str_contains($tagStr, 'High Break') || str_contains($tagStr, 'PE New High'));

            $ceDelta3 = ($i >= 3) ? ($curCe - $rows[$i - 3]['chng_call_oi']) : ($curCe - $rows[0]['chng_call_oi']);
            $peDelta3 = ($i >= 3) ? ($curPe - $rows[$i - 3]['chng_put_oi']) : ($curPe - $rows[0]['chng_put_oi']);

            // 1. Dual Writing (Both CE and PE hitting highs / building range tunnel)
            if ($hasCeHighBreak && $hasPeHighBreak) {
                $outlookKey    = 'DUAL_WRITING';
                $outlookLabel  = '⚖️ Dual Writing (Strangle)';
                $outlookReason = 'Both Call and Put writers expanding aggressively. Market entering non-directional strangle tunnel.';
                $outlookAction = 'Sell Short Strangle / Iron Condor';
                $outlookBadge  = 'dual_writing';
            }
            // 2. Dual Unwinding (Both CE and PE breaking lows / end of day square-off)
            elseif ($hasCeLowBreak && $hasPeLowBreak) {
                $outlookKey    = 'DUAL_UNWINDING';
                $outlookLabel  = '⚡ Dual Unwind (Square-off)';
                $outlookReason = 'Both Call and Put writers liquidating positions. High volatility or intraday squaring off active.';
                $outlookAction = 'Book Profits / Avoid Fresh Selling';
                $outlookBadge  = 'dual_unwind';
            }
            // 3. Short Squeeze Trigger (Call unwinding while Put holds/adds)
            elseif ($hasCeLowBreak || ($ceDelta3 <= -1000000 && $peDelta3 >= -250000)) {
                $outlookKey    = 'BULLISH_SQUEEZE';
                $outlookLabel  = '🚀 Squeeze Up (30m-1h)';
                $outlookReason = 'Call writers unwinding sharply (' . ($ceDelta3 >= 0 ? '+' : '') . number_format($ceDelta3) . ') while Put writers hold support (' . ($peDelta3 >= 0 ? '+' : '') . number_format($peDelta3) . '). Upward squeeze expected.';
                $outlookAction = 'Sell OTM PE on Dips / Avoid CE Sells';
                $outlookBadge  = 'squeeze_up';
            }
            // 4. Bearish Breakdown Trigger (Put unwinding while Call holds/adds)
            elseif ($hasPeLowBreak || ($peDelta3 <= -1000000 && $ceDelta3 >= -250000)) {
                $outlookKey    = 'BEARISH_BREAKDOWN';
                $outlookLabel  = '💥 Breakdown (30m-1h)';
                $outlookReason = 'Put writers panicking/exiting (' . ($peDelta3 >= 0 ? '+' : '') . number_format($peDelta3) . ') under Call resistance (' . ($ceDelta3 >= 0 ? '+' : '') . number_format($ceDelta3) . '). Downside expansion expected.';
                $outlookAction = 'Sell OTM CE on Rallies / Avoid PE Sells';
                $outlookBadge  = 'breakdown_down';
            }
            // 5. PE Absorption (Pre-Squeeze Warning)
            elseif ($curCe > 1.4 * max(1, $curPe) && $ceDelta3 > 800000 && $peDelta3 >= 0 && ($r['put_ltp_chng'] ?? 0) <= 0) {
                $outlookKey    = 'PE_ABSORPTION';
                $outlookLabel  = '⚠️ PE Absorption';
                $outlookReason = 'Call OI expanding heavily but Put OI refusing to drop. Institutional absorption active; high squeeze vulnerability.';
                $outlookAction = 'Caution on CE Sells / Prepare Bullish Reversal';
                $outlookBadge  = 'absorption';
            }
            // 6. Bullish Dip-Buy / Support Expansion
            elseif ($hasPeHighBreak || ($peDelta3 > 1000000 && $peDelta3 > $ceDelta3 * 1.3 && ($r['net_pcr'] ?? 1) >= 0.9)) {
                $outlookKey    = 'BULLISH_TREND';
                $outlookLabel  = '🟢 Bullish (Sell PE)';
                $outlookReason = 'Put writers expanding strong base (PE Δ ' . ($peDelta3 >= 0 ? '+' : '') . number_format($peDelta3) . '). Upward support intact.';
                $outlookAction = 'Sell OTM PE at Support Strikes';
                $outlookBadge  = 'bullish_sell_pe';
            }
            // 7. Bearish Rise-Sell / Resistance Ceiling
            elseif ($hasCeHighBreak || ($ceDelta3 > 1000000 && $ceDelta3 > $peDelta3 * 1.3 && ($r['net_pcr'] ?? 1) <= 0.85)) {
                $outlookKey    = 'BEARISH_TREND';
                $outlookLabel  = '🔴 Bearish (Sell CE)';
                $outlookReason = 'Call writers building firm ceiling (CE Δ ' . ($ceDelta3 >= 0 ? '+' : '') . number_format($ceDelta3) . '). Rallies capped.';
                $outlookAction = 'Sell OTM CE at Resistance Strikes';
                $outlookBadge  = 'bearish_sell_ce';
            }
            // 8. Default Rangebound Theta Zone
            else {
                $outlookKey    = 'NEUTRAL_DECAY';
                $outlookLabel  = '⚖️ Theta Decay Zone';
                $outlookReason = 'Balanced Call & Put activity. Stable strikes and non-directional premium decay favored.';
                $outlookAction = 'Short Strangle / Iron Condor';
                $outlookBadge  = 'neutral_decay';
            }

            $r['forward_sentiment']        = $outlookLabel;
            $r['forward_sentiment_key']    = $outlookKey;
            $r['forward_sentiment_badge']  = $outlookBadge;
            $r['forward_sentiment_reason'] = $outlookReason;
            $r['forward_sentiment_action'] = $outlookAction;
            $r['sentiment']                = $outlookLabel;
        }
    }

    /**
     * Predictive OI Signal & Market State Engine with Option Seller Playbook.
     */
    private function analyzeOiSignals(array $rows, float $latestSpot, int $atmStrike, string $lookback = 'auto'): array
    {
        $count = count($rows);
        if ($count < 2) {
            return [
                'state_key'        => 'INSUFFICIENT_DATA',
                'badge_color'      => 'gray',
                'state_title'      => 'Awaiting Data',
                'confidence'       => 0,
                'bias'             => 'Neutral',
                'headline'         => 'Accumulating initial market snapshots...',
                'reasons'          => ['Need at least 2 time intervals to evaluate momentum and OI acceleration.'],
                'seller_action'    => 'Wait & Watch',
                'seller_strategy'  => 'Observe first 15–30 minutes of open for institutional footprint.',
                'seller_strikes'   => 'N/A',
                'absorption_score' => 'Neutral',
                'metrics'          => [],
            ];
        }

        $latest = $rows[$count - 1];
        $prev1  = $rows[$count - 2];
        $prev3  = ($count >= 4) ? $rows[$count - 4] : $prev1;
        $prev5  = ($count >= 6) ? $rows[$count - 6] : $rows[0];

        // Rolling slice for trailing 5-6 bars
        $windowLen = min(6, $count - 1);
        $trailingSlice = array_slice($rows, $count - 1 - $windowLen, $windowLen);
        $prevCeList = array_column($trailingSlice, 'chng_call_oi');
        $prevPeList = array_column($trailingSlice, 'chng_put_oi');

        $minTrailingCe = min($prevCeList);
        $maxTrailingCe = max($prevCeList);
        $minTrailingPe = min($prevPeList);
        $maxTrailingPe = max($prevPeList);

        $curCeOi = $latest['chng_call_oi'];
        $curPeOi = $latest['chng_put_oi'];
        $curDiffOi = $latest['diff_oi'];
        $curPcr = $latest['net_pcr'];
        $directionPct = $latest['direction_pct'];
        $chngInDir = $latest['chng_in_direction'];

        // Short-term delta (1-bar and 3-bar)
        $ceDelta1 = $curCeOi - $prev1['chng_call_oi'];
        $peDelta1 = $curPeOi - $prev1['chng_put_oi'];
        $ceDelta3 = $curCeOi - $prev3['chng_call_oi'];
        $peDelta3 = $curPeOi - $prev3['chng_put_oi'];
        $pcrDelta3 = round($curPcr - $prev3['net_pcr'], 2);

        // Day Peak metrics
        $allCe = array_column($rows, 'chng_call_oi');
        $allPe = array_column($rows, 'chng_put_oi');
        $dayMaxCe = max($allCe);
        $dayMaxPe = max($allPe);

        // Absorption Assessment: Put OI resilience during Call expansion
        $peAbsorptionStatus = 'Normal';
        if ($dayMaxCe > 2000000) {
            $peAtCeMax = 0;
            foreach ($rows as $r) {
                if ($r['chng_call_oi'] === $dayMaxCe) {
                    $peAtCeMax = $r['chng_put_oi'];
                    break;
                }
            }
            if ($peAtCeMax > 0 && ($peAtCeMax / $dayMaxCe) >= 0.55) {
                $peAbsorptionStatus = 'High (Strong PE Support Base)';
            } elseif ($peAtCeMax > 0 && ($peAtCeMax / $dayMaxCe) >= 0.40) {
                $peAbsorptionStatus = 'Moderate (PE Writers Holding)';
            } else {
                $peAbsorptionStatus = 'Low (PE Unwinding Fast)';
            }
        }

        // --- Core Decision Engine ---
        $reasons = [];
        $stateKey = 'NEUTRAL';
        $badgeColor = 'gray';
        $stateTitle = 'Neutral / No Clue';
        $bias = 'Neutral';
        $confidence = 50;
        $sellerAction = 'Wait & Watch';
        $sellerStrategy = 'Market is choppy or consolidating. Avoid directional aggressive selling.';
        $sellerStrikes = "Strangle: Sell " . ($atmStrike + 150) . " CE & " . ($atmStrike - 150) . " PE";

        // Condition 1: Strong Bullish Shift / Call Short Covering (User's Exact Pattern)
        $isCeBreakingDown = ($curCeOi < $minTrailingCe || $curCeOi < ($dayMaxCe * 0.93));
        $isPeRisingOrHolding = ($peDelta3 >= 0 || $curPeOi >= $prev1['chng_put_oi'] || $curPeOi >= ($dayMaxPe * 0.95));
        $isSentimentImproving = ($chngInDir > 0 || $curDiffOi > $prev3['diff_oi']);

        if ($isCeBreakingDown && $isPeRisingOrHolding && $isSentimentImproving) {
            $stateKey = 'STRONG_BULLISH_SHIFT';
            $badgeColor = 'green';
            $stateTitle = 'Strong Bullish Shift (Short Covering / Mean Reversion)';
            $bias = 'Bullish';
            $confidence = ($ceDelta3 < -2000000 && $peDelta3 > 1000000) ? 92 : 86;

            $ceUnwoundLakh = abs(round(($dayMaxCe - $curCeOi) / 100000, 1));
            $peAddedLakh = abs(round($peDelta3 / 100000, 1));

            $headline = "Call writers are unwinding heavily while Put writers are aggressively defending & adding positions.";
            $reasons[] = "Call OI broke below trailing 5-6 interval lows (down {$ceUnwoundLakh} Lakh from peak).";
            $reasons[] = "Put OI is holding firm and expanding (+{$peAddedLakh} Lakh in recent bars).";
            $reasons[] = "Net PCR recovered (+{$pcrDelta3}) and Sentiment curve formed a sharp upward recovery.";

            $sellerAction = 'Sell OTM Puts (Bull Put Spread / Short Put)';
            $sellerStrategy = "Ride the upward short-covering squeeze. Put writing provides strong floor support.";
            $sellerStrikes = "Sell " . ($atmStrike - 50) . " PE or " . ($atmStrike - 100) . " PE (Hedge: Buy " . ($atmStrike - 200) . " PE)";
        }
        // Condition 2: Heavy Bearish Expansion / Call Writing Domination
        elseif ($curCeOi >= $maxTrailingCe && $curCeOi >= ($dayMaxCe * 0.98) && $peDelta3 < 0 && $curDiffOi < $prev3['diff_oi']) {
            $stateKey = 'STRONG_BEARISH_EXPANSION';
            $badgeColor = 'red';
            $stateTitle = 'Strong Bearish Momentum (Call Writing Expansion)';
            $bias = 'Bearish';
            $confidence = 88;

            $ceAddedLakh = round($ceDelta3 / 100000, 1);
            $peUnwoundLakh = abs(round($peDelta3 / 100000, 1));

            $headline = "Aggressive Call writing creating lower ceiling; Put writers are unwinding and retreating.";
            $reasons[] = "Call OI is making consecutive new highs (+{$ceAddedLakh} Lakh added).";
            $reasons[] = "Put OI is breaking trailing lows (-{$peUnwoundLakh} Lakh unwound).";
            $reasons[] = "Diff in OI is expanding downward (Day Low Breakdown).";

            $sellerAction = 'Sell OTM Calls (Bear Call Spread / Short Call)';
            $sellerStrategy = "Heavy overhead supply cap. Capitalize on rapid CE theta decay and downward drift.";
            $sellerStrikes = "Sell " . ($atmStrike + 50) . " CE or " . ($atmStrike + 100) . " CE (Hedge: Buy " . ($atmStrike + 200) . " CE)";
        }
        // Condition 3: PE Absorption Alert (CE making highs, but PE is resilient)
        elseif ($curCeOi >= ($dayMaxCe * 0.96) && $peAbsorptionStatus === 'High (Strong PE Support Base)' && $curDiffOi < -10000000) {
            $stateKey = 'PE_ABSORPTION_ALERT';
            $badgeColor = 'yellow';
            $stateTitle = 'PE Absorption Alert (Trap Risk for Call Sellers)';
            $bias = 'Neutral to Bullish Divergence';
            $confidence = 78;

            $headline = "Call writers pushing down, but Put writers are absorbing supply without panic. Reversal risk high.";
            $reasons[] = "Call OI is testing day highs, but Put OI is NOT unwinding (retaining >55% ratio).";
            $reasons[] = "Market is heavily oversold (PCR ~ {$curPcr}); downside moves likely to face sharp bounce.";
            $reasons[] = "High probability of Call short-covering trap as soon as Call OI stalls.";

            $sellerAction = 'Caution on CE Selling | Prepare for PE Selling';
            $sellerStrategy = "Avoid aggressive Call selling at lows. Wait for Call OI to tick down before initiating Put sells.";
            $sellerStrikes = "Watchlist: " . ($atmStrike - 50) . " PE / " . ($atmStrike - 100) . " PE";
        }
        // Condition 4: Long Unwinding (Put Breakdown)
        elseif ($curPeOi < $minTrailingPe && $curPeOi < ($dayMaxPe * 0.88) && $ceDelta3 >= 0) {
            $stateKey = 'LONG_UNWINDING';
            $badgeColor = 'red';
            $stateTitle = 'Long Unwinding (Support Breakdown)';
            $bias = 'Bearish';
            $confidence = 80;

            $peLostLakh = abs(round(($dayMaxPe - $curPeOi) / 100000, 1));
            $headline = "Put writers have surrendered key support levels, triggering cascading stop losses.";
            $reasons[] = "Put OI broke below trailing 5-6 interval lows (-{$peLostLakh} Lakh lost from high).";
            $reasons[] = "Call writers remain in firm control without covering.";

            $sellerAction = 'Sell OTM Calls / Exit Put Sells';
            $sellerStrategy = "Sell OTM Calls into minor pullbacks. Downside supports broken.";
            $sellerStrikes = "Sell " . ($atmStrike + 100) . " CE";
        }
        // Condition 5: Rangebound / Theta Grind
        elseif (abs($directionPct) < 3.5 && abs($chngInDir) < 500000) {
            $stateKey = 'RANGEBOUND_THETA';
            $badgeColor = 'blue';
            $stateTitle = 'Rangebound Consolidation (High Theta Decay)';
            $bias = 'Non-Directional';
            $confidence = 75;

            $headline = "Call and Put writing are evenly balanced. Premium erosion / time decay environment.";
            $reasons[] = "Diff in OI is moving sideways with direction change under 3.5%.";
            $reasons[] = "Both Call & Put OI are stabilizing without fresh aggressive momentum.";

            $sellerAction = 'Sell Short Strangle / Iron Condor';
            $sellerStrategy = "Collect both CE and PE premium decay while spot oscillates in a narrow band.";
            $sellerStrikes = "Sell " . ($atmStrike + 150) . " CE & Sell " . ($atmStrike - 150) . " PE";
        }
        // Condition 6: Neutral / No Clue
        else {
            $headline = "Mixed OI signals with alternating direction arrows. Awaiting clear institutional trend.";
            $reasons[] = "Direction of change is oscillating without sustained 3-candle momentum.";
            $reasons[] = "PCR and Diff OI show mild divergence.";
            $sellerAction = 'Wait for Setup Confirmation';
            $sellerStrategy = "Hold cash or stay with existing hedged positions until a clear 5-bar high/low break occurs.";
            $sellerStrikes = "No High Probability Strike";
        }

        return [
            'state_key'        => $stateKey,
            'badge_color'      => $badgeColor,
            'state_title'      => $stateTitle,
            'bias'             => $bias,
            'confidence'       => $confidence,
            'headline'         => $headline,
            'reasons'          => $reasons,
            'seller_action'    => $sellerAction,
            'seller_strategy'  => $sellerStrategy,
            'seller_strikes'   => $sellerStrikes,
            'absorption_score' => $peAbsorptionStatus,
            'metrics'          => [
                'ce_delta_3bar'    => $ceDelta3,
                'pe_delta_3bar'    => $peDelta3,
                'pcr_momentum'     => $pcrDelta3,
                'day_max_ce'       => $dayMaxCe,
                'day_max_pe'       => $dayMaxPe,
                'trailing_min_ce'  => $minTrailingCe,
                'trailing_max_ce'  => $maxTrailingCe,
            ],
        ];
    }

    /**
     * AJAX: Get distinct expiries.
     */
    public function getExpiries(Request $request)
    {
        $mode       = $request->input('mode', 'live');
        $date       = $request->input('date', today()->toDateString());
        $underlying = $request->input('underlying', 'NSE_INDEX|Nifty 50');

        $table = $this->resolveTable($mode);

        $expiries = DB::table($table)
            ->where('underlying_key', $underlying)
            ->whereDate('captured_at', $date)
            ->select('expiry')
            ->distinct()
            ->orderBy('expiry')
            ->pluck('expiry');

        if ($expiries->isEmpty()) {
            $expiries = DB::table('nse_expiries')
                ->where('trading_symbol', 'NIFTY')
                ->where('instrument_type', 'OPT')
                ->orderBy('expiry_date')
                ->pluck('expiry_date');
        }

        return response()->json(['expiries' => $expiries]);
    }

    /**
     * AJAX: Get all available strikes for strike selection modal.
     */
    public function getStrikes(Request $request)
    {
        $mode       = $request->input('mode', 'live');
        $expiry     = $request->input('expiry');
        $date       = $request->input('date', today()->toDateString());
        $underlying = $request->input('underlying', 'NSE_INDEX|Nifty 50');

        $table = $this->resolveTable($mode);

        $strikes = DB::table($table)
            ->where('underlying_key', $underlying)
            ->where('expiry', $expiry)
            ->whereDate('captured_at', $date)
            ->select('strike_price')
            ->distinct()
            ->orderBy('strike_price')
            ->pluck('strike_price')
            ->map(fn($s) => (int) $s);

        return response()->json(['strikes' => $strikes]);
    }

    /**
     * Evaluate Daily High-Conviction Strategy Station (Option Seller Decision Cockpit).
     * Strictly limits to 1-2 high-conviction trades per day, exits by 13:20 IST,
     * pulls active strategies from backtest_strategies, and maps Option Chain Support/Resistance.
     */
    private function evaluateDailyStrategyStation(
        array $rows,
        float $latestSpot,
        int $atmStrike,
        string $latestTimestamp,
        string $table,
        string $underlying,
        string $expiry
    ): array {
        // 1. Fetch active strategies from backtest_strategies
        $activeStrategies = \App\Models\BacktestStrategy::where('is_active', true)->get();
        $primaryStrategy = $activeStrategies->firstWhere('id', 3) 
            ?? $activeStrategies->firstWhere('name', 'Daily OAI V2') 
            ?? $activeStrategies->firstWhere('name', 'Daily OAI Copy')
            ?? $activeStrategies->first();

        $strategyName = $primaryStrategy ? $primaryStrategy->name : 'Daily OAI V2';
        $strategyId   = $primaryStrategy ? $primaryStrategy->id : 3;
        $strategyLegs = $primaryStrategy ? ($primaryStrategy->definition['legs'] ?? []) : [];

        // 2. Fetch Option Chain boundary walls (Support & Resistance) from latest snapshot
        $ocRows = DB::table($table)
            ->where('underlying_key', $underlying)
            ->where('expiry', $expiry)
            ->where('captured_at', $latestTimestamp)
            ->get(['strike_price', 'option_type', 'oi', 'diff_oi', 'volume', 'ltp']);

        $majorCeResistance = $atmStrike + 150;
        $buildingCeResistance = $atmStrike + 100;
        $majorPeSupport = $atmStrike - 150;
        $buildingPeSupport = $atmStrike - 100;

        if ($ocRows->isNotEmpty()) {
            $ceRows = $ocRows->where('option_type', 'CE')->where('strike_price', '>=', $atmStrike);
            $peRows = $ocRows->where('option_type', 'PE')->where('strike_price', '<=', $atmStrike);

            if ($ceRows->isNotEmpty()) {
                $maxCeOiRow = $ceRows->sortByDesc('oi')->first();
                $maxCeDiffRow = $ceRows->sortByDesc('diff_oi')->first();
                if ($maxCeOiRow) $majorCeResistance = (int) $maxCeOiRow->strike_price;
                if ($maxCeDiffRow) $buildingCeResistance = (int) $maxCeDiffRow->strike_price;
            }

            if ($peRows->isNotEmpty()) {
                $maxPeOiRow = $peRows->sortByDesc('oi')->first();
                $maxPeDiffRow = $peRows->sortByDesc('diff_oi')->first();
                if ($maxPeOiRow) $majorPeSupport = (int) $maxPeOiRow->strike_price;
                if ($maxPeDiffRow) $buildingPeSupport = (int) $maxPeDiffRow->strike_price;
            }
        }

        // 3. Time Boundary Check (Hard Exit by 13:20 IST)
        $timeStr = Carbon::parse($latestTimestamp)->format('H:i:s');
        $isPastCutoff = ($timeStr >= '13:20:00');

        $count = count($rows);
        if ($count < 2 || $isPastCutoff) {
            return [
                'is_trade_active'       => false,
                'status_badge'          => $isPastCutoff ? 'CLOSED (Past 13:20 IST)' : 'AWAITING DATA',
                'badge_color'           => 'slate',
                'strategy_name'         => $strategyName,
                'strategy_id'           => $strategyId,
                'setup_title'           => $isPastCutoff ? 'Trading Window Closed' : 'Accumulating Morning Data',
                'headline'              => $isPastCutoff 
                    ? 'Hard cutoff reached (13:20 IST). All positions flat to protect against afternoon gamma spikes.'
                    : 'Awaiting market open stabilization before scanning for institutional edge.',
                'recommended_anchor'    => null,
                'anchor_skew'           => 'N/A',
                'safe_spot_range'       => ['min' => $majorPeSupport, 'max' => $majorCeResistance],
                'safe_range_text'       => "Support: {$majorPeSupport} | Resistance: {$majorCeResistance}",
                'support_strike'        => $majorPeSupport,
                'resistance_strike'     => $majorCeResistance,
                'target_pnl'            => 'N/A',
                'stop_loss_pnl'         => 'N/A',
                'expected_duration'     => 'Flat',
                'cutoff_time'           => '13:20 IST',
                'action_label'          => $isPastCutoff ? 'NO NEW TRADE / FLAT' : 'WAIT FOR EDGE',
                'rationale'             => $isPastCutoff ? 'Time-based capital preservation rule active.' : 'Monitoring initial flow.',
                'basket_legs'           => [],
            ];
        }

        $latest = end($rows);
        $prev1  = $rows[$count - 2];
        $prev3  = ($count >= 4) ? $rows[$count - 4] : $prev1;

        $curCeOi      = $latest['chng_call_oi'];
        $curPeOi      = $latest['chng_put_oi'];
        $directionPct = $latest['direction_pct'];
        $chngInDir    = $latest['chng_in_direction'];
        $netPcr       = $latest['net_pcr'];
        $patternTag   = $latest['pattern_tag'] ?? '';
        $forwardKey   = $latest['forward_sentiment_key'] ?? '';

        $ceDelta3 = $curCeOi - $prev3['chng_call_oi'];
        $peDelta3 = $curPeOi - $prev3['chng_put_oi'];

        // Determine Setups based purely on Change in OI direction & pattern
        $isTradeActive = false;
        $setupTitle = 'No Trade (Capital Preservation Mode)';
        $statusBadge = 'NO TRADE (Sitting in Cash)';
        $badgeColor = 'amber';
        $recommendedAnchor = null;
        $anchorSkew = 'N/A';
        $targetPnl = 'N/A';
        $stopLossPnl = 'N/A';
        $expectedDuration = 'No Position';
        $actionLabel = '⚪ NO TRADE (Sit in Cash)';
        $rationale = 'Direction of change is oscillating or risk of whip-saw is high. Capital preservation is priority #1.';

        // Condition 1: Squeeze Drift (Bullish Drift - "2 Strikes Away" Quick Money)
        $hasCeLowBreak = (str_contains($patternTag, 'CE') && str_contains($patternTag, 'Low Break')) || $forwardKey === 'BULLISH_SQUEEZE';
        $isSqueezePattern = ($hasCeLowBreak || ($ceDelta3 <= -1200000 && $peDelta3 >= -200000)) && ($directionPct >= 10 || $chngInDir > 0);

        if ($isSqueezePattern && $timeStr < '13:00:00') {
            $isTradeActive = true;
            $setupTitle = 'Bullish Squeeze Drift (Quick Money Play)';
            $statusBadge = '🟢 ACTIVE TRADE (Setup: Squeeze Drift)';
            $badgeColor = 'emerald';
            
            // Shift anchor 1 or 2 strikes away towards upside
            $shiftPts = (abs($ceDelta3) > 2500000) ? 100 : 50;
            $recommendedAnchor = $atmStrike + $shiftPts;
            $anchorSkew = "+{$shiftPts} pts OTM Skew (Targeting Squeeze Drift)";
            
            $safeMin = round($latestSpot - 20);
            $safeMax = round($recommendedAnchor + 25);
            $targetPnl = '+₹3,200 to +₹4,500';
            $stopLossPnl = '-₹1,900';
            $expectedDuration = '25 to 45 mins (Hard exit by 13:20 IST)';
            $actionLabel = '🚀 ENTER NOW — Bullish Drift Skew';
            
            $ceUnwoundFmt = number_format(abs($ceDelta3));
            $rationale = "Call writers unwound {$ceUnwoundFmt} contracts while Put writers held/added. Spot migrating into shifted basket sweet spot for rapid PE decay.";
        }
        // Condition 2: Breakdown Drift (Bearish Drift - Shifted Downward)
        elseif (((str_contains($patternTag, 'PE') && str_contains($patternTag, 'Low Break')) || $forwardKey === 'BEARISH_BREAKDOWN') && $timeStr < '13:00:00') {
            $isTradeActive = true;
            $setupTitle = 'Bearish Breakdown Drift (Shifted Downward)';
            $statusBadge = '🔴 ACTIVE TRADE (Setup: Breakdown Drift)';
            $badgeColor = 'rose';
            
            $shiftPts = (abs($peDelta3) > 2500000) ? 100 : 50;
            $recommendedAnchor = $atmStrike - $shiftPts;
            $anchorSkew = "-{$shiftPts} pts OTM Skew (Targeting Downward Drift)";
            
            $safeMin = round($recommendedAnchor - 25);
            $safeMax = round($latestSpot + 20);
            $targetPnl = '+₹3,200 to +₹4,500';
            $stopLossPnl = '-₹1,900';
            $expectedDuration = '25 to 45 mins (Hard exit by 13:20 IST)';
            $actionLabel = '💥 ENTER NOW — Bearish Drift Skew';
            
            $peUnwoundFmt = number_format(abs($peDelta3));
            $rationale = "Put writers surrendered support (-{$peUnwoundFmt} contracts). Spot drifting down into shifted basket sweet spot.";
        }
        // Condition 3: Balanced Theta Tunnel (Non-Directional Symmetric Ladder)
        elseif (($forwardKey === 'DUAL_WRITING' || ($forwardKey === 'NEUTRAL_DECAY' && abs($directionPct) < 22 && $netPcr >= 0.85 && $netPcr <= 1.25)) && $timeStr < '13:00:00') {
            $isTradeActive = true;
            $setupTitle = 'Balanced Theta Tunnel (Symmetric Ladder)';
            $statusBadge = '🟢 ACTIVE TRADE (Setup: Theta Tunnel)';
            $badgeColor = 'blue';
            
            $recommendedAnchor = $atmStrike;
            $anchorSkew = 'Exact Spot ATM (Non-Directional Center)';
            
            $safeMin = max($majorPeSupport, $atmStrike - 35);
            $safeMax = min($majorCeResistance, $atmStrike + 35);
            $targetPnl = '+₹2,800 to +₹3,500';
            $stopLossPnl = '-₹1,800';
            $expectedDuration = '40 to 60 mins (Hard exit by 13:20 IST)';
            $actionLabel = '⚖️ ENTER NOW — Symmetric Ladder';
            $rationale = "Both Call and Put writers expanding without depth breaks. Premium decay maximized in middle band ({$safeMin} - {$safeMax}).";
        }
        else {
            // Capital preservation mode
            $safeMin = $majorPeSupport;
            $safeMax = $majorCeResistance;
        }

        // Build concrete 16-leg breakdown if an anchor strike is recommended
        $basketLegs = [];
        if ($recommendedAnchor !== null && !empty($strategyLegs)) {
            foreach ($strategyLegs as $idx => $leg) {
                $type = strtoupper((string) ($leg['option_type'] ?? ''));
                $money = strtoupper((string) ($leg['moneyness'] ?? 'ATM'));
                $offset = (float) ($leg['strike_offset'] ?? 0);
                $lots = (int) ($leg['lots'] ?? 1);
                $side = strtoupper((string) ($leg['side'] ?? 'SELL'));

                // Same logic as BasketBuilderController
                $moneynessAdj = match ($money) {
                    'ITM' => -50 - $offset,
                    'OTM' => 50 + $offset,
                    default => 0,
                };
                $strike = (int) ($recommendedAnchor + $moneynessAdj);

                $basketLegs[] = [
                    'leg_number'  => $idx + 1,
                    'option_type' => $type,
                    'side'        => $side,
                    'strike'      => $strike,
                    'lots'        => $lots,
                ];
            }
        }



        return [
            'is_trade_active'       => $isTradeActive,
            'status_badge'          => $statusBadge,
            'badge_color'           => $badgeColor,
            'strategy_name'         => $strategyName,
            'strategy_id'           => $strategyId,
            'setup_title'           => $setupTitle,
            'headline'              => $rationale,
            'recommended_anchor'    => $recommendedAnchor,
            'anchor_skew'           => $anchorSkew,
            'safe_spot_range'       => ['min' => $safeMin, 'max' => $safeMax],
            'safe_range_text'       => "Safe Band: {$safeMin} – {$safeMax}",
            'support_strike'        => $majorPeSupport,
            'resistance_strike'     => $majorCeResistance,
            'target_pnl'            => $targetPnl,
            'stop_loss_pnl'         => $stopLossPnl,
            'expected_duration'     => $expectedDuration,
            'cutoff_time'           => '13:20 IST',
            'action_label'          => $actionLabel,
            'rationale'             => $rationale,
            'basket_legs'           => $basketLegs,
        ];
    }

    /**
     * Calculate Multi-Timeframe Strike-Level OI Buildup (5M, 15M, 30M, Today).
     * Classifies each strike into SB (Short Build), LB (Long Build), LU (Long Unwind), SC (Short Cover).
     * Ranks top 10 items by absolute OI change magnitude across CE & PE.
     */
    private function calculateMultiTimeframeBuildup(
        string $table,
        string $underlying,
        string $expiry,
        array $timestamps,
        int $atmStrike
    ): array {
        $count = count($timestamps);
        if ($count < 1) {
            return [
                '5m'    => $this->emptyBuildupPayload('5M'),
                '15m'   => $this->emptyBuildupPayload('15M'),
                '30m'   => $this->emptyBuildupPayload('30M'),
                'today' => $this->emptyBuildupPayload('Today'),
            ];
        }

        $latestTimestamp = end($timestamps);
        $carbonLatest = Carbon::parse($latestTimestamp);

        // Find closest timestamps for 5m, 15m, 30m, and today base
        $ts5m = $this->findClosestTimestamp($carbonLatest->copy()->subMinutes(5), $timestamps);
        $ts15m = $this->findClosestTimestamp($carbonLatest->copy()->subMinutes(15), $timestamps);
        $ts30m = $this->findClosestTimestamp($carbonLatest->copy()->subMinutes(30), $timestamps);
        $tsToday = reset($timestamps);

        // Query all required timestamps in one single query
        $neededTimestamps = array_unique(array_filter([$ts5m, $ts15m, $ts30m, $tsToday, $latestTimestamp]));
        $rows = DB::table($table)
            ->where('underlying_key', $underlying)
            ->where('expiry', $expiry)
            ->whereIn('captured_at', $neededTimestamps)
            ->get(['captured_at', 'strike_price', 'option_type', 'oi', 'ltp']);

        // Group rows by captured_at
        $groupedByTs = [];
        foreach ($rows as $r) {
            $key = ((int) $r->strike_price) . '_' . $r->option_type;
            $groupedByTs[$r->captured_at][$key] = $r;
        }

        $latestMap = $groupedByTs[$latestTimestamp] ?? [];

        return [
            '5m'    => $this->buildTimeframeDataset($latestMap, $groupedByTs[$ts5m] ?? null, $ts5m, $latestTimestamp, '5 Min', $atmStrike),
            '15m'   => $this->buildTimeframeDataset($latestMap, $groupedByTs[$ts15m] ?? null, $ts15m, $latestTimestamp, '15 Min', $atmStrike),
            '30m'   => $this->buildTimeframeDataset($latestMap, $groupedByTs[$ts30m] ?? null, $ts30m, $latestTimestamp, '30 Min', $atmStrike),
            'today' => $this->buildTimeframeDataset($latestMap, $groupedByTs[$tsToday] ?? null, $tsToday, $latestTimestamp, 'Today', $atmStrike),
        ];
    }

    /**
     * Build timeframe dataset with (SB, LB, LU, SC) classification.
     */
    private function buildTimeframeDataset(
        array $latestMap,
        ?array $prevMap,
        ?string $prevTs,
        string $latestTs,
        string $tfName,
        int $atmStrike
    ): array {
        $currTimeShort = Carbon::parse($latestTs)->format('H:i');
        $prevTimeShort = $prevTs ? Carbon::parse($prevTs)->format('H:i') : '—';
        $windowLabel = ($prevTs && $prevTs !== $latestTs) ? "{$prevTimeShort} – {$currTimeShort} ({$tfName})" : "Market Open – {$currTimeShort} ({$tfName})";

        if (empty($latestMap) || empty($prevMap) || $prevTs === $latestTs) {
            return [
                'window_label' => $windowLabel,
                'time_curr'    => $currTimeShort,
                'time_prev'    => $prevTimeShort,
                'top_items'    => [],
                'summary'      => [
                    'total_ce_chg_lakh' => '+0.0 L',
                    'total_pe_chg_lakh' => '+0.0 L',
                    'dominant'          => 'Neutral (Opening)',
                ]
            ];
        }

        $items = [];
        $totalCeChg = 0;
        $totalPeChg = 0;

        foreach ($latestMap as $key => $curr) {
            $prev = $prevMap[$key] ?? null;
            $prevOi = $prev ? (int) $prev->oi : 0;
            $prevLtp = $prev ? (float) $prev->ltp : (float) $curr->ltp;

            $diffOi = ((int) $curr->oi) - $prevOi;
            $diffLtp = round(((float) $curr->ltp) - $prevLtp, 2);

            [$strikeStr, $type] = explode('_', $key);
            $strike = (int) $strikeStr;

            // Buildup classification & color mapping:
            // SB - Red (#dc2626)
            // SC - Navy Blue (#1e3a8a)
            // LB - Green (#16a34a)
            // LU - Yellow (#eab308)
            if ($diffOi > 0 && $diffLtp >= 0) {
                $bType = 'LB';
                $bName = 'Long Buildup';
                $barColor = '#16a34a'; // Green
            } elseif ($diffOi > 0 && $diffLtp < 0) {
                $bType = 'SB';
                $bName = 'Short Buildup';
                $barColor = '#dc2626'; // Red
            } elseif ($diffOi < 0 && $diffLtp <= 0) {
                $bType = 'LU';
                $bName = 'Long Unwinding';
                $barColor = '#eab308'; // Yellow
            } elseif ($diffOi < 0 && $diffLtp > 0) {
                $bType = 'SC';
                $bName = 'Short Covering';
                $barColor = '#1e3a8a'; // Navy Blue
            } else {
                $bType = $diffOi >= 0 ? 'SB' : 'LU';
                $bName = $diffOi >= 0 ? 'Short Buildup' : 'Long Unwinding';
                $barColor = $diffOi >= 0 ? '#dc2626' : '#eab308';
            }

            if ($type === 'CE') {
                $totalCeChg += $diffOi;
            } else {
                $totalPeChg += $diffOi;
            }

            if ($diffOi != 0) {
                $items[] = [
                    'strike'           => $strike,
                    'option_type'      => $type,
                    'label'            => "{$strike} {$type}",
                    'label_full'       => "{$strike} {$type}",
                    'buildup_type'     => $bType,
                    'buildup_name'     => $bName,
                    'diff_oi'          => $diffOi,
                    'abs_diff_oi'      => abs($diffOi),
                    'diff_oi_val_lakh' => round(abs($diffOi) / 100000, 2),
                    'signed_val_lakh'  => round($diffOi / 100000, 2),
                    'diff_oi_lakh'     => ($diffOi >= 0 ? '+' : '') . round($diffOi / 100000, 1) . ' L',
                    'diff_ltp'         => $diffLtp,
                    'bar_color'        => $barColor,
                    'dist_atm'         => abs($strike - $atmStrike),
                ];
            }
        }

        // Sort by magnitude (abs_diff_oi) descending across ALL CE and PE (top 8)
        usort($items, fn($a, $b) => $b['abs_diff_oi'] <=> $a['abs_diff_oi']);

        if ($totalCeChg > $totalPeChg * 1.35) {
            $dominant = 'Call Writing / CE Buildup Dominant (Ceiling Resistance)';
        } elseif ($totalPeChg > $totalCeChg * 1.35) {
            $dominant = 'Put Writing / PE Buildup Dominant (Floor Support)';
        } else {
            $dominant = 'Dual Flow (Rangebound Momentum)';
        }

        return [
            'window_label' => $windowLabel,
            'time_curr'    => $currTimeShort,
            'time_prev'    => $prevTimeShort,
            'top_items'    => array_slice($items, 0, 8), // Top 8 items
            'summary'      => [
                'total_ce_chg_lakh' => ($totalCeChg >= 0 ? '+' : '') . round($totalCeChg / 100000, 1) . ' L',
                'total_pe_chg_lakh' => ($totalPeChg >= 0 ? '+' : '') . round($totalPeChg / 100000, 1) . ' L',
                'dominant'          => $dominant,
            ]
        ];
    }

    private function findClosestTimestamp(Carbon $targetTime, array $timestamps): ?string
    {
        $closest = null;
        $minDiff = PHP_INT_MAX;
        foreach ($timestamps as $ts) {
            $c = Carbon::parse($ts);
            $diff = abs($c->diffInSeconds($targetTime));
            if ($diff < $minDiff) {
                $minDiff = $diff;
                $closest = $ts;
            }
        }
        return $closest;
    }

    private function emptyBuildupPayload(string $tfName): array
    {
        return [
            'window_label' => "Market Open ({$tfName})",
            'time_curr'    => '—',
            'time_prev'    => '—',
            'top_items'    => [],
            'summary'      => [
                'total_ce_chg_lakh' => '0.0 L',
                'total_pe_chg_lakh' => '0.0 L',
                'dominant'          => 'Neutral',
            ]
        ];
    }

    /**
     * Consolidate chronological signals for the session into distinct signal waves/episodes.
     * Prevents repetitive 5-minute row duplication and cleanly tracks when each new signal was raised,
     * its duration, anchor skew, spot migration, and why/how flow rationale.
     */
    private function syncChronologicalStrategyCalls(
        array $rows,
        string $table,
        string $underlying,
        string $expiry,
        array $latestStation
    ): void {
        $count = count($rows);
        if ($count < 2) return;

        try {
            // 1. Fetch active primary strategy once
            $activeStrategies = \App\Models\BacktestStrategy::where('is_active', true)->get();
            $primaryStrategy = $activeStrategies->firstWhere('id', 3) 
                ?? $activeStrategies->firstWhere('name', 'Daily OAI V2') 
                ?? $activeStrategies->firstWhere('name', 'Daily OAI Copy')
                ?? $activeStrategies->first();

            $strategyName = $primaryStrategy ? $primaryStrategy->name : 'Daily OAI V2';
            $strategyId   = $primaryStrategy ? $primaryStrategy->id : 3;
            $strategyLegs = $primaryStrategy ? ($primaryStrategy->definition['legs'] ?? []) : [];

            $mode = ($table === 'option_chains_history') ? 'history' : 'live';
            $firstRow = $rows[0];
            $latestRow = end($rows);
            $tradeDate = Carbon::parse($latestRow['timestamp'])->toDateString();
            $isHistoricalSession = ($mode === 'history');

            // 2. Iterate chronologically and group consecutive active bars into waves
            $waves = [];
            $currentWave = null;

            for ($i = 0; $i < $count; $i++) {
                $subRows = array_slice($rows, 0, $i + 1);
                $subCount = count($subRows);
                $curRow = $rows[$i];
                $timeStr = $curRow['time'];

                if ($subCount < 2 || $timeStr >= '13:20:00') {
                    if ($currentWave !== null) {
                        $currentWave['outcome_status'] = 'CONCLUDED';
                        $waves[] = $currentWave;
                        $currentWave = null;
                    }
                    continue;
                }

                $latest = $curRow;
                $prev1  = $subRows[$subCount - 2];
                $prev3  = ($subCount >= 4) ? $subRows[$subCount - 4] : $prev1;

                $curCeOi      = $latest['chng_call_oi'];
                $curPeOi      = $latest['chng_put_oi'];
                $directionPct = $latest['direction_pct'];
                $chngInDir    = $latest['chng_in_direction'];
                $netPcr       = $latest['net_pcr'];
                $patternTag   = $latest['pattern_tag'] ?? '';
                $forwardKey   = $latest['forward_sentiment_key'] ?? '';

                $ceDelta3 = $curCeOi - $prev3['chng_call_oi'];
                $peDelta3 = $curPeOi - $prev3['chng_put_oi'];

                $isTradeActive = false;
                $setupTitle = '';
                $statusBadge = '';
                $badgeColor = 'amber';
                $actionLabel = '';
                $recommendedAnchor = null;
                $anchorSkew = '';
                $safeMin = null;
                $safeMax = null;
                $targetPnl = '';
                $stopLossPnl = '';
                $expectedDuration = '';
                $rationale = '';

                $barAtmStrike = (int) (round($latest['spot'] / 50) * 50);

                // Condition 1: Squeeze Drift
                $hasCeLowBreak = (str_contains($patternTag, 'CE') && str_contains($patternTag, 'Low Break')) || $forwardKey === 'BULLISH_SQUEEZE';
                $isSqueezePattern = ($hasCeLowBreak || ($ceDelta3 <= -1200000 && $peDelta3 >= -200000)) && ($directionPct >= 10 || $chngInDir > 0);

                if ($isSqueezePattern && $timeStr < '13:00:00') {
                    $isTradeActive = true;
                    $setupTitle = 'Bullish Squeeze Drift (Quick Money Play)';
                    $statusBadge = '🟢 ACTIVE TRADE (Setup: Squeeze Drift)';
                    $badgeColor = 'emerald';
                    $shiftPts = (abs($ceDelta3) > 2500000) ? 100 : 50;
                    $recommendedAnchor = $barAtmStrike + $shiftPts;
                    $anchorSkew = "+{$shiftPts} pts OTM Skew (Targeting Squeeze Drift)";
                    $safeMin = round($latest['spot'] - 20);
                    $safeMax = round($recommendedAnchor + 25);
                    $targetPnl = '+₹3,200 to +₹4,500';
                    $stopLossPnl = '-₹1,900';
                    $expectedDuration = '25 to 45 mins (Hard exit by 13:20 IST)';
                    $actionLabel = '🚀 ENTER NOW — Bullish Drift Skew';
                    $ceUnwoundFmt = number_format(abs($ceDelta3));
                    $rationale = "Call writers unwound {$ceUnwoundFmt} contracts while Put writers held/added. Spot migrating into shifted basket sweet spot for rapid PE decay.";
                }
                // Condition 2: Breakdown Drift
                elseif (((str_contains($patternTag, 'PE') && str_contains($patternTag, 'Low Break')) || $forwardKey === 'BEARISH_BREAKDOWN') && $timeStr < '13:00:00') {
                    $isTradeActive = true;
                    $setupTitle = 'Bearish Breakdown Drift (Shifted Downward)';
                    $statusBadge = '🔴 ACTIVE TRADE (Setup: Breakdown Drift)';
                    $badgeColor = 'rose';
                    $shiftPts = (abs($peDelta3) > 2500000) ? 100 : 50;
                    $recommendedAnchor = $barAtmStrike - $shiftPts;
                    $anchorSkew = "-{$shiftPts} pts OTM Skew (Targeting Downward Drift)";
                    $safeMin = round($recommendedAnchor - 25);
                    $safeMax = round($latest['spot'] + 20);
                    $targetPnl = '+₹3,200 to +₹4,500';
                    $stopLossPnl = '-₹1,900';
                    $expectedDuration = '25 to 45 mins (Hard exit by 13:20 IST)';
                    $actionLabel = '💥 ENTER NOW — Bearish Drift Skew';
                    $peUnwoundFmt = number_format(abs($peDelta3));
                    $rationale = "Put writers surrendered support (-{$peUnwoundFmt} contracts). Spot drifting down into shifted basket sweet spot.";
                }
                // Condition 3: Balanced Theta Tunnel
                elseif (($forwardKey === 'DUAL_WRITING' || ($forwardKey === 'NEUTRAL_DECAY' && abs($directionPct) < 22 && $netPcr >= 0.85 && $netPcr <= 1.25)) && $timeStr < '13:00:00') {
                    $isTradeActive = true;
                    $setupTitle = 'Balanced Theta Tunnel (Symmetric Ladder)';
                    $statusBadge = '🟢 ACTIVE TRADE (Setup: Theta Tunnel)';
                    $badgeColor = 'blue';
                    $recommendedAnchor = $barAtmStrike;
                    $anchorSkew = 'Exact Spot ATM (Non-Directional Center)';
                    $safeMin = round($barAtmStrike - 40);
                    $safeMax = round($barAtmStrike + 40);
                    $targetPnl = '+₹2,800 to +₹3,500';
                    $stopLossPnl = '-₹1,800';
                    $expectedDuration = '40 to 60 mins (Hard exit by 13:20 IST)';
                    $actionLabel = '⚖️ ENTER NOW — Symmetric Ladder';
                    $rationale = "Both Call and Put writers expanding without depth breaks. Premium decay maximized in middle band ({$safeMin} – {$safeMax}).";
                }

                if ($isTradeActive) {
                    if ($currentWave !== null && $currentWave['setup_title'] === $setupTitle) {
                        // Ongoing continuation of the same wave
                        $currentWave['end_time'] = $curRow['time'];
                        $currentWave['last_captured_at'] = $curRow['timestamp'];
                        $currentWave['bars_count']++;
                        $currentWave['duration_minutes'] = $currentWave['bars_count'] * 5;
                        $currentWave['last_spot'] = (float) $curRow['spot'];
                        $currentWave['spot_change'] = round((float) $curRow['spot'] - $currentWave['entry_spot'], 2);
                    } else {
                        // Conclude previous wave if different setup
                        if ($currentWave !== null) {
                            $currentWave['outcome_status'] = 'CONCLUDED';
                            $waves[] = $currentWave;
                        }

                        // Build 16-leg structure for this wave's recommended anchor
                        $basketLegs = [];
                        if ($recommendedAnchor !== null && !empty($strategyLegs)) {
                            foreach ($strategyLegs as $idx => $leg) {
                                $type = strtoupper((string) ($leg['option_type'] ?? ''));
                                $money = strtoupper((string) ($leg['moneyness'] ?? 'ATM'));
                                $offset = (float) ($leg['strike_offset'] ?? 0);
                                $lots = (int) ($leg['lots'] ?? 1);
                                $side = strtoupper((string) ($leg['side'] ?? 'SELL'));

                                $moneynessAdj = match ($money) {
                                    'ITM' => -50 - $offset,
                                    'OTM' => 50 + $offset,
                                    default => 0,
                                };
                                $strike = (int) ($recommendedAnchor + $moneynessAdj);

                                $basketLegs[] = [
                                    'leg_number'  => $idx + 1,
                                    'option_type' => $type,
                                    'side'        => $side,
                                    'strike'      => $strike,
                                    'lots'        => $lots,
                                ];
                            }
                        }

                        // Start new signal wave
                        $currentWave = [
                            'signal_time'        => $curRow['time'],
                            'end_time'           => $curRow['time'],
                            'captured_at'        => $curRow['timestamp'],
                            'last_captured_at'   => $curRow['timestamp'],
                            'duration_minutes'   => 5,
                            'bars_count'         => 1,
                            'strategy_name'      => $strategyName,
                            'strategy_id'        => $strategyId,
                            'setup_title'        => $setupTitle,
                            'status_badge'       => $statusBadge,
                            'badge_color'        => $badgeColor,
                            'action_label'       => $actionLabel,
                            'recommended_anchor' => $recommendedAnchor,
                            'anchor_skew'        => $anchorSkew,
                            'entry_spot'         => (float) $curRow['spot'],
                            'last_spot'          => (float) $curRow['spot'],
                            'spot_change'        => 0.00,
                            'safe_spot_min'      => $safeMin,
                            'safe_spot_max'      => $safeMax,
                            'support_strike'     => $safeMin,
                            'resistance_strike'  => $safeMax,
                            'target_pnl'         => $targetPnl,
                            'stop_loss_pnl'      => $stopLossPnl,
                            'expected_duration'  => $expectedDuration,
                            'headline'           => $rationale,
                            'rationale'          => $rationale,
                            'flow_metrics'       => [
                                'ce_delta_3bar'     => $ceDelta3,
                                'pe_delta_3bar'     => $peDelta3,
                                'net_pcr'           => $netPcr,
                                'direction_pct'     => $directionPct,
                                'chng_in_direction' => $chngInDir,
                                'chng_call_oi'      => $curCeOi,
                                'chng_put_oi'       => $curPeOi,
                                'pattern_tag'       => $patternTag,
                            ],
                            'basket_legs'        => $basketLegs,
                            'outcome_status'     => 'ACTIVE',
                        ];
                    }
                } else {
                    // Market is inactive or in capital preservation
                    if ($currentWave !== null) {
                        $currentWave['outcome_status'] = 'CONCLUDED';
                        $waves[] = $currentWave;
                        $currentWave = null;
                    }
                }
            }

            // Final check on ongoing wave at end of session
            if ($currentWave !== null) {
                // If historical session or past cutoff, finalize as CONCLUDED
                if ($isHistoricalSession || $latestRow['time'] >= '13:20:00') {
                    $currentWave['outcome_status'] = 'CONCLUDED';
                } else {
                    $currentWave['outcome_status'] = 'ACTIVE';
                }
                $waves[] = $currentWave;
            }

            // 3. Batch sync waves into strategy_call_logs
            foreach ($waves as $wave) {
                DB::table('strategy_call_logs')->updateOrInsert(
                    [
                        'trade_date'  => $tradeDate,
                        'signal_time' => $wave['signal_time'],
                        'setup_title' => $wave['setup_title'],
                        'underlying'  => $underlying,
                    ],
                    [
                        'mode'               => $mode,
                        'end_time'           => $wave['end_time'],
                        'captured_at'        => $wave['captured_at'],
                        'last_captured_at'   => $wave['last_captured_at'],
                        'duration_minutes'   => $wave['duration_minutes'],
                        'bars_count'         => $wave['bars_count'],
                        'expiry'             => $expiry,
                        'strategy_name'      => $wave['strategy_name'],
                        'strategy_id'        => $wave['strategy_id'],
                        'status_badge'       => $wave['status_badge'],
                        'badge_color'        => $wave['badge_color'],
                        'action_label'       => $wave['action_label'],
                        'recommended_anchor' => $wave['recommended_anchor'],
                        'anchor_skew'        => $wave['anchor_skew'],
                        'entry_spot'         => $wave['entry_spot'],
                        'last_spot'          => $wave['last_spot'],
                        'spot_change'        => $wave['spot_change'],
                        'safe_spot_min'      => $wave['safe_spot_min'],
                        'safe_spot_max'      => $wave['safe_spot_max'],
                        'support_strike'     => $wave['support_strike'],
                        'resistance_strike'  => $wave['resistance_strike'],
                        'target_pnl'         => $wave['target_pnl'],
                        'stop_loss_pnl'      => $wave['stop_loss_pnl'],
                        'expected_duration'  => $wave['expected_duration'],
                        'cutoff_time'        => '13:20 IST',
                        'headline'           => $wave['headline'],
                        'rationale'          => $wave['rationale'],
                        'flow_metrics'       => json_encode($wave['flow_metrics']),
                        'basket_legs'        => json_encode($wave['basket_legs']),
                        'outcome_status'     => $wave['outcome_status'],
                        'updated_at'         => now(),
                        'created_at'         => now(),
                    ]
                );
            }
        } catch (\Throwable $e) {
            \Log::error("Failed to sync chronological strategy calls: " . $e->getMessage());
        }
    }

    /**
     * AJAX: Get recorded strategy call logs with pagination and wave metrics.
     */
    public function getStrategyCalls(Request $request)
    {
        $date = $request->input('date');
        $underlying = $request->input('underlying', 'NSE_INDEX|Nifty 50');
        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(1, min(50, (int) $request->input('per_page', 5)));

        $query = DB::table('strategy_call_logs')
            ->where('underlying', $underlying);

        if (!empty($date)) {
            $query->where('trade_date', $date);
        }

        $totalCount = $query->count();
        $totalPages = $totalCount > 0 ? (int) ceil($totalCount / $perPage) : 1;
        $offset = ($page - 1) * $perPage;

        $logs = $query->orderByDesc('trade_date')
            ->orderByDesc('signal_time')
            ->skip($offset)
            ->take($perPage)
            ->get();

        return response()->json([
            'date'         => $date,
            'total_count'  => $totalCount,
            'current_page' => $page,
            'per_page'     => $perPage,
            'total_pages'  => $totalPages,
            'from'         => $totalCount > 0 ? $offset + 1 : 0,
            'to'           => min($offset + $perPage, $totalCount),
            'calls'        => $logs->map(function ($log) {
                $signalTimeShort = substr($log->signal_time, 0, 5);
                $endTimeShort = $log->end_time ? substr($log->end_time, 0, 5) : $signalTimeShort;
                $entrySpot = (float) $log->entry_spot;
                $lastSpot = $log->last_spot ? (float) $log->last_spot : $entrySpot;
                $spotChange = (float) ($log->spot_change ?? ($lastSpot - $entrySpot));
                $spotChangeFormatted = ($spotChange >= 0 ? '+' : '') . number_format($spotChange, 2);

                return [
                    'id'                 => $log->id,
                    'trade_date'         => $log->trade_date,
                    'signal_time'        => $signalTimeShort,
                    'end_time'           => $endTimeShort,
                    'active_time_range'  => "{$signalTimeShort} – {$endTimeShort} IST",
                    'duration_minutes'   => (int) $log->duration_minutes,
                    'bars_count'         => (int) $log->bars_count,
                    'duration_text'      => "{$log->duration_minutes}m • {$log->bars_count} " . ($log->bars_count === 1 ? 'bar' : 'bars'),
                    'captured_at'        => $log->captured_at,
                    'setup_title'        => $log->setup_title,
                    'status_badge'       => $log->status_badge,
                    'badge_color'        => $log->badge_color,
                    'action_label'       => $log->action_label,
                    'recommended_anchor' => $log->recommended_anchor,
                    'anchor_skew'        => $log->anchor_skew,
                    'entry_spot'         => $entrySpot,
                    'last_spot'          => $lastSpot,
                    'spot_change'        => $spotChange,
                    'spot_change_text'   => $spotChangeFormatted,
                    'safe_range_text'    => "{$log->safe_spot_min} – {$log->safe_spot_max}",
                    'target_pnl'         => $log->target_pnl,
                    'stop_loss_pnl'      => $log->stop_loss_pnl,
                    'expected_duration'  => $log->expected_duration,
                    'cutoff_time'        => $log->cutoff_time,
                    'rationale'          => $log->rationale,
                    'flow_metrics'       => json_decode($log->flow_metrics, true) ?: [],
                    'basket_legs'        => json_decode($log->basket_legs ?? '[]', true) ?: [],
                    'basket_legs_count'  => count(json_decode($log->basket_legs ?? '[]', true) ?: []),
                    'outcome_status'     => $log->outcome_status,
                ];
            }),
        ]);
    }
}
