<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BreadthImpulseCalendarController extends Controller
{
    public function index(Request $request): View
    {
        $today = Carbon::now('Asia/Kolkata')->toDateString();
        $minDate = '2024-01-01';
        $maxDate = DB::table('nse_working_days')
            ->where('current', 1)
            ->value('working_date') ?: $today;

        return view('breadth-impulse-calendar.index', [
            'today'   => $today,
            'minDate' => $minDate,
            'maxDate' => $maxDate,
        ]);
    }

    /**
     * Return day-wise summary for the last N working days or a custom date/range.
     */
    public function summary(Request $request): JsonResponse
    {
        $daysCount = max(1, min(60, (int) $request->input('days', 10)));
        $stkTh = max(50, min(100, (float) $request->input('stk_th', 80)));
        $optTh = max(50, min(100, (float) $request->input('opt_th', 70)));
        $fromDate = $request->input('from');
        $toDate = $request->input('to');
        $singleDate = $request->input('date');

        $today = Carbon::now('Asia/Kolkata')->toDateString();

        if ($singleDate) {
            $dates = [$singleDate];
        } elseif ($fromDate && $toDate) {
            $dates = DB::table('nse_working_days')
                ->whereBetween('working_date', [$fromDate, $toDate])
                ->orderByDesc('working_date')
                ->limit(60)
                ->pluck('working_date')
                ->toArray();
        } else {
            $dates = DB::table('nse_working_days')
                ->where('working_date', '<=', $today)
                ->orderByDesc('working_date')
                ->limit($daysCount)
                ->pluck('working_date')
                ->toArray();
        }

        $rows = [];
        foreach ($dates as $d) {
            $summary = $this->getDayAnalysis((string) $d, $stkTh, $optTh, false);
            if ($summary) {
                $rows[] = $summary;
            }
        }

        return response()->json([
            'success' => true,
            'stk_th'  => $stkTh,
            'opt_th'  => $optTh,
            'days'    => $rows,
        ]);
    }

    /**
     * Return full 5-minute signal timeline & chart series for a single selected day.
     */
    public function dayDetail(Request $request): JsonResponse
    {
        $date = (string) $request->input('date', Carbon::now('Asia/Kolkata')->toDateString());
        $stkTh = max(50, min(100, (float) $request->input('stk_th', 80)));
        $optTh = max(50, min(100, (float) $request->input('opt_th', 70)));

        $detail = $this->getDayAnalysis($date, $stkTh, $optTh, true);
        if (!$detail) {
            return response()->json([
                'success' => false,
                'message' => "No 5-minute candle data found for {$date}.",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'detail'  => $detail,
        ]);
    }

    /**
     * Compute (and cache for historical dates) day-level OHLC + STK/OPT/BOTH impulse metrics.
     */
    protected function getDayAnalysis(string $date, float $stkTh, float $optTh, bool $includeTimeline): ?array
    {
        $today = Carbon::now('Asia/Kolkata')->toDateString();
        $cacheKey = "bic_v3_{$date}_{$stkTh}_{$optTh}_" . ($includeTimeline ? 'full' : 'sum');
        $ttl = ($date === $today) ? 45 : 86400;

        return Cache::remember($cacheKey, $ttl, function () use ($date, $stkTh, $optTh, $includeTimeline) {
            $wCtrl = app(NiftyWeightageMonitorController::class);
            $oCtrl = app(OptionsBreadthMonitorController::class);

            $wReq = Request::create('/api/nifty-weightage-monitor/data', 'GET', [
                'date'      => $date,
                'timeframe' => '5m',
            ]);
            $wJson = $wCtrl->getData($wReq)->getData(true);
            $candles = $wJson['nifty_candles'] ?? $wJson['candles'] ?? [];
            $stkBreadth = $wJson['breadth_by_time'] ?? [];

            if (empty($candles)) {
                return null;
            }

            $oReq = Request::create('/api/options-breadth-monitor/data', 'GET', [
                'date'      => $date,
                'timeframe' => '5m',
            ]);
            $oJson = [];
            try {
                $oJson = $oCtrl->getData($oReq)->getData(true);
            } catch (\Throwable $e) {
                $oJson = [];
            }
            $optBreadth = $oJson['breadth_by_time'] ?? [];

            // Load Trending OI sentiment (PE_OI - CE_OI) per 5m candle
            $toiMap = [];
            try {
                $expiry = DB::table('nse_expiries')
                    ->where('trading_symbol', 'NIFTY')
                    ->where('instrument_type', 'OPT')
                    ->where('expiry_date', '>=', $date)
                    ->orderBy('expiry_date')
                    ->value('expiry_date');
                if ($expiry) {
                    $tCtrl = app(TrendingOiController::class);
                    $tJson = $tCtrl->getData(Request::create('/api/trending-oi/data', 'GET', [
                        'date'     => $date,
                        'expiry'   => $expiry,
                        'interval' => 5,
                    ]))->getData(true);
                    $tLabels = $tJson['chart']['labels'] ?? [];
                    $tCallOi = $tJson['chart']['call_oi'] ?? [];
                    $tPutOi  = $tJson['chart']['put_oi'] ?? [];
                    $tSent   = $tJson['chart']['sentiment'] ?? [];
                    foreach ($tLabels as $idx => $lbl) {
                        $tKey = substr((string) $lbl, 0, 5);
                        $ce = (float) ($tCallOi[$idx] ?? 0);
                        $pe = (float) ($tPutOi[$idx] ?? 0);
                        $toiMap[$tKey] = isset($tSent[$idx]) ? (float) $tSent[$idx] : ($pe - $ce);
                    }
                }
            } catch (\Throwable $e) {
                $toiMap = [];
            }

            // Day OHLC & Open-to-High / Open-to-Low / Total Range
            $dayOpen = (float) ($candles[0]['open'] ?? 0);
            $dayClose = (float) ($candles[count($candles) - 1]['close'] ?? 0);
            $dayHigh = max(array_map(fn($c) => (float) ($c['high'] ?? 0), $candles));
            $dayLow = min(array_map(fn($c) => (float) ($c['low'] ?? 0), $candles));

            $openToHigh = round($dayHigh - $dayOpen, 2);
            $openToLow = round($dayLow - $dayOpen, 2); // negative or 0
            $totalRange = round($dayHigh - $dayLow, 2);
            $netMove = round($dayClose - $dayOpen, 2);
            $netMovePct = $dayOpen > 0 ? round(($netMove / $dayOpen) * 100, 2) : 0;

            $counts = [
                'bull_stk'   => 0,
                'bull_opt'   => 0,
                'bull_both'  => 0,
                'bull_total' => 0,
                'bear_stk'   => 0,
                'bear_opt'   => 0,
                'bear_both'  => 0,
                'bear_total' => 0,
                'worth_hits' => 0,
                'total_sigs' => 0,
                's4_total'   => 0,
                's4_wins'    => 0,
                's4_losses'  => 0,
            ];

            $mfeSum = 0.0;
            $signals = [];
            $chartBars = [];
            $n = count($candles);
            $cumTpVol = 0.0;
            $cumVol = 0.0;
            $prevOptBull = false;
            $prevOptBear = false;

            for ($i = 0; $i < $n; $i++) {
                $c = $candles[$i];
                $tStr = (string) ($c['time_str'] ?? '');
                $cClose = (float) ($c['close'] ?? 0);
                $cVol = max(1.0, (float) ($c['volume'] ?? 1));
                $cTp = ((float) ($c['high'] ?? $cClose) + (float) ($c['low'] ?? $cClose) + $cClose) / 3.0;
                $cumTpVol += $cTp * $cVol;
                $cumVol += $cVol;
                $vwap = $cumVol > 0 ? ($cumTpVol / $cumVol) : $cClose;

                $sb = $stkBreadth[$tStr] ?? null;
                $ob = $optBreadth[$tStr] ?? null;

                $gCnt = (float) ($sb['green_count_pct'] ?? 0);
                $rCnt = (float) ($sb['red_count_pct'] ?? 0);
                $gWt  = (float) ($sb['green_weight'] ?? 0);
                $rWt  = (float) ($sb['red_weight'] ?? 0);

                $oUpWt = (float) ($ob['up_weight'] ?? 0);
                $oDnWt = (float) ($ob['down_weight'] ?? 0);

                $stkBull = ($gCnt >= $stkTh && $gWt >= $stkTh);
                $stkBear = ($rCnt >= $stkTh && $rWt >= $stkTh);
                $optBull = ($oUpWt >= $optTh);
                $optBear = ($oDnWt >= $optTh);

                $isFreshOptBull = ($optBull && !$prevOptBull);
                $isFreshOptBear = ($optBear && !$prevOptBear);
                $prevOptBull = $optBull;
                $prevOptBear = $optBear;

                $bullType = null;
                if ($stkBull && $optBull) {
                    $bullType = 'BOTH';
                    $counts['bull_both']++;
                } elseif ($stkBull) {
                    $bullType = 'STK';
                    $counts['bull_stk']++;
                } elseif ($optBull) {
                    $bullType = 'OPT';
                    $counts['bull_opt']++;
                }

                $bearType = null;
                if ($stkBear && $optBear) {
                    $bearType = 'BOTH';
                    $counts['bear_both']++;
                } elseif ($stkBear) {
                    $bearType = 'STK';
                    $counts['bear_stk']++;
                } elseif ($optBear) {
                    $bearType = 'OPT';
                    $counts['bear_opt']++;
                }

                if ($bullType) {
                    $counts['bull_total']++;
                    $sigEval = $this->evaluateSignalOutcome($candles, $i, 'BULL', $cClose);
                    $counts['total_sigs']++;
                    if ($sigEval['worth_it']) $counts['worth_hits']++;
                    $mfeSum += $sigEval['mfe'];

                    $isS4 = ($isFreshOptBull && $cClose >= $vwap && $gWt >= 60.0 && $tStr >= '09:50' && $tStr <= '14:35');
                    $oiDiff = $toiMap[$tStr] ?? null;
                    $oiAligned = ($oiDiff !== null) ? ($oiDiff > 0) : null;
                    $s4Res = $isS4 ? $this->evaluateFixedTargetSl($candles, $i, 'BULL', $cClose, 15.0, 25.0) : null;
                    if ($isS4) {
                        $counts['s4_total']++;
                        if ($s4Res === 'WIN') $counts['s4_wins']++;
                        elseif ($s4Res === 'LOSS') $counts['s4_losses']++;
                    }

                    if ($includeTimeline) {
                        $signals[] = array_merge([
                            'time'       => $tStr,
                            'direction'  => 'BULL',
                            'type'       => $bullType,
                            'spot'       => round($cClose, 2),
                            'stk_pct'    => round(max($gCnt, $gWt), 1),
                            'stk_cnt'    => round($gCnt, 1),
                            'stk_wt'     => round($gWt, 1),
                            'opt_pct'    => round($oUpWt, 1),
                            'is_s4'      => $isS4,
                            's4_result'  => $s4Res,
                            'oi_aligned' => $oiAligned,
                            'oi_diff_l'  => $oiDiff !== null ? round($oiDiff / 100000, 1) : null,
                        ], $sigEval);
                    }
                }

                if ($bearType) {
                    $counts['bear_total']++;
                    $sigEval = $this->evaluateSignalOutcome($candles, $i, 'BEAR', $cClose);
                    $counts['total_sigs']++;
                    if ($sigEval['worth_it']) $counts['worth_hits']++;
                    $mfeSum += $sigEval['mfe'];

                    $isS4 = ($isFreshOptBear && $cClose <= $vwap && $rWt >= 60.0 && $tStr >= '09:50' && $tStr <= '14:35');
                    $oiDiff = $toiMap[$tStr] ?? null;
                    $oiAligned = ($oiDiff !== null) ? ($oiDiff < 0) : null;
                    $s4Res = $isS4 ? $this->evaluateFixedTargetSl($candles, $i, 'BEAR', $cClose, 15.0, 25.0) : null;
                    if ($isS4) {
                        $counts['s4_total']++;
                        if ($s4Res === 'WIN') $counts['s4_wins']++;
                        elseif ($s4Res === 'LOSS') $counts['s4_losses']++;
                    }

                    if ($includeTimeline) {
                        $signals[] = array_merge([
                            'time'       => $tStr,
                            'direction'  => 'BEAR',
                            'type'       => $bearType,
                            'spot'       => round($cClose, 2),
                            'stk_pct'    => round(max($rCnt, $rWt), 1),
                            'stk_cnt'    => round($rCnt, 1),
                            'stk_wt'     => round($rWt, 1),
                            'opt_pct'    => round($oDnWt, 1),
                            'is_s4'      => $isS4,
                            's4_result'  => $s4Res,
                            'oi_aligned' => $oiAligned,
                            'oi_diff_l'  => $oiDiff !== null ? round($oiDiff / 100000, 1) : null,
                        ], $sigEval);
                    }
                }

                if ($includeTimeline) {
                    $chartBars[] = [
                        'time'     => $tStr,
                        'open'     => (float) ($c['open'] ?? 0),
                        'high'     => (float) ($c['high'] ?? 0),
                        'low'      => (float) ($c['low'] ?? 0),
                        'close'    => $cClose,
                        'stk_bull' => round($gWt, 1),
                        'stk_bear' => round($rWt, 1),
                        'opt_bull' => round($oUpWt, 1),
                        'opt_bear' => round($oDnWt, 1),
                        'bull_sig' => $bullType,
                        'bear_sig' => $bearType,
                    ];
                }
            }

            $worthRate = $counts['total_sigs'] > 0 ? round(($counts['worth_hits'] / $counts['total_sigs']) * 100) : 0;
            $avgMfe = $counts['total_sigs'] > 0 ? round($mfeSum / $counts['total_sigs'], 1) : 0;

            $diff = $counts['bull_total'] - $counts['bear_total'];
            if ($counts['total_sigs'] === 0) {
                $pressure = 'QUIET';
            } elseif ($diff >= 3 || ($counts['bull_total'] > 0 && $counts['bear_total'] === 0)) {
                $pressure = 'HEAVY_BUYING';
            } elseif ($diff <= -3 || ($counts['bear_total'] > 0 && $counts['bull_total'] === 0)) {
                $pressure = 'HEAVY_SELLING';
            } else {
                $pressure = 'TWO_WAY_CHOP';
            }

            $res = [
                'date'         => $date,
                'day_name'     => Carbon::parse($date)->format('D'),
                'open'         => round($dayOpen, 2),
                'high'         => round($dayHigh, 2),
                'low'          => round($dayLow, 2),
                'close'        => round($dayClose, 2),
                'net_move'     => $netMove,
                'net_move_pct' => $netMovePct,
                'open_to_high' => $openToHigh,
                'open_to_low'  => $openToLow,
                'total_range'  => $totalRange,
                'counts'       => $counts,
                'worth_rate'   => $worthRate,
                'avg_mfe'      => $avgMfe,
                'pressure'     => $pressure,
                'candles_cnt'  => $n,
            ];

            if ($includeTimeline) {
                $res['signals'] = $signals;
                $res['chart_bars'] = $chartBars;
            }

            return $res;
        });
    }

    /**
     * Evaluate post-signal follow-through over the next 6 candles (30 mins) and rest of day.
     */
    protected function evaluateSignalOutcome(array $candles, int $idx, string $dir, float $entrySpot): array
    {
        $n = count($candles);
        $isBull = ($dir === 'BULL');

        $move15m = null;
        if ($idx + 3 < $n) {
            $c15 = (float) ($candles[$idx + 3]['close'] ?? $entrySpot);
            $move15m = round($isBull ? ($c15 - $entrySpot) : ($entrySpot - $c15), 1);
        }

        $move30m = null;
        if ($idx + 6 < $n) {
            $c30 = (float) ($candles[$idx + 6]['close'] ?? $entrySpot);
            $move30m = round($isBull ? ($c30 - $entrySpot) : ($entrySpot - $c30), 1);
        }

        // Look ahead up to 12 bars (1 hour) for MFE / MAE
        $endIdx = min($n - 1, $idx + 12);
        $mfe = 0.0;
        $mae = 0.0;
        for ($j = $idx + 1; $j <= $endIdx; $j++) {
            $h = (float) ($candles[$j]['high'] ?? $entrySpot);
            $l = (float) ($candles[$j]['low'] ?? $entrySpot);
            if ($isBull) {
                $mfe = max($mfe, $h - $entrySpot);
                $mae = max($mae, $entrySpot - $l);
            } else {
                $mfe = max($mfe, $entrySpot - $l);
                $mae = max($mae, $h - $entrySpot);
            }
        }
        $mfe = round($mfe, 1);
        $mae = round($mae, 1);

        $worthIt = ($mfe >= 25.0 && $mfe >= ($mae * 0.9));
        if ($idx >= $n - 1) {
            $verdict = 'LIVE_BAR';
            $reason = 'Signal on latest candle — awaiting follow-through.';
        } elseif ($worthIt) {
            $verdict = 'WORTH_IT';
            $reason = "Delivered +{$mfe}p favorable move vs -{$mae}p drawdown in 60m.";
        } elseif ($mfe >= 15.0) {
            $verdict = 'SCALP_CHOP';
            $reason = "Modest +{$mfe}p push with -{$mae}p pullback (scalp only).";
        } else {
            $verdict = 'FAILED';
            $reason = "Weak follow-through (+{$mfe}p MFE vs -{$mae}p adverse move).";
        }

        return [
            'move_15m' => $move15m,
            'move_30m' => $move30m,
            'mfe'      => $mfe,
            'mae'      => $mae,
            'worth_it' => $worthIt,
            'verdict'  => $verdict,
            'reason'   => $reason,
        ];
    }

    protected function evaluateFixedTargetSl(array $candles, int $idx, string $dir, float $entrySpot, float $tgtPts, float $slPts): string
    {
        $n = count($candles);
        if ($idx >= $n - 1) return 'LIVE';
        $endIdx = min($n - 1, $idx + 12);
        $isBull = ($dir === 'BULL');
        for ($j = $idx + 1; $j <= $endIdx; $j++) {
            $h = (float) ($candles[$j]['high'] ?? $entrySpot);
            $l = (float) ($candles[$j]['low'] ?? $entrySpot);
            $fav = $isBull ? ($h - $entrySpot) : ($entrySpot - $l);
            $adv = $isBull ? ($entrySpot - $l) : ($h - $entrySpot);
            if ($adv >= $slPts) return 'LOSS';
            if ($fav >= $tgtPts) return 'WIN';
        }
        return 'TIMEOUT';
    }
}
