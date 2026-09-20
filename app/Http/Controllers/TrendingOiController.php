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

        // Fetch distinct timestamps available for this date & expiry
        $timestamps = DB::table($table)
            ->where('underlying_key', $underlying)
            ->where('expiry', $expiry)
            ->whereDate('captured_at', $date)
            ->select('captured_at')
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

        return response()->json([
            'rows'              => $tableRows,
            'chart'             => [
                'labels'     => $chartLabels,
                'call_oi'    => $chartCallOi,
                'put_oi'     => $chartPutOi,
                'sentiment'  => $chartSentiment,
                'spot'       => $chartSpot,
            ],
            'selected_strikes'  => $selectedStrikes,
            'all_strikes'       => $allStrikes,
            'underlying_data'   => $underlyingData,
            'atm_strike'        => $atmStrike,
            'signal_data'       => $signalData,
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
}
