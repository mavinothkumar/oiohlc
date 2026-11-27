<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ScalperS4LabController extends Controller
{
    public function index(): View
    {
        $today = Carbon::now('Asia/Kolkata')->toDateString();
        $defaultFrom = Carbon::parse($today)->subMonths(6)->toDateString();
        if ($defaultFrom < '2026-05-14') {
            $defaultFrom = '2026-05-14';
        }

        return view('scalper-s4-lab.index', [
            'today'       => $today,
            'minDate'     => '2026-05-14',
            'defaultFrom' => $defaultFrom,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $today = Carbon::now('Asia/Kolkata')->toDateString();
        $from = (string) $request->input('from', '2026-05-14');
        $to = (string) $request->input('to', $today);
        $revTh = max(40, min(95, (float) $request->input('rev_th', 60)));
        $tgtPts = max(10, min(60, (float) $request->input('tgt', 25)));
        $slPts = max(10, min(60, (float) $request->input('sl', 25)));

        $dates = DB::table('nse_working_days')
            ->whereBetween('working_date', [$from, $to])
            ->where('working_date', '<=', $today)
            ->orderByDesc('working_date')
            ->pluck('working_date')
            ->toArray();

        $allSignals = [];
        $daysWithData = 0;

        foreach ($dates as $d) {
            $daySignals = $this->analyzeDay((string) $d, $revTh, $tgtPts, $slPts);
            if ($daySignals !== null) {
                $daysWithData++;
                foreach ($daySignals as $sig) {
                    $allSignals[] = $sig;
                }
            }
        }

        return response()->json([
            'success'        => true,
            'days_scanned'   => $daysWithData,
            'total_signals'  => count($allSignals),
            'rev_th'         => $revTh,
            'tgt_pts'        => $tgtPts,
            'sl_pts'         => $slPts,
            'signals'        => $allSignals,
        ]);
    }

    protected function analyzeDay(string $date, float $revTh, float $tgtPts, float $slPts): ?array
    {
        $today = Carbon::now('Asia/Kolkata')->toDateString();
        $cacheKey = "s4lab_v1_{$date}_{$revTh}_{$tgtPts}_{$slPts}";
        $ttl = ($date === $today) ? 45 : 86400;

        return Cache::remember($cacheKey, $ttl, function () use ($date, $revTh, $tgtPts, $slPts) {
            $wCtrl = app(NiftyWeightageMonitorController::class);
            $oCtrl = app(OptionsBreadthMonitorController::class);
            $tCtrl = app(TrendingOiController::class);

            $wJson = $wCtrl->getData(Request::create('/api/nifty-weightage-monitor/data', 'GET', [
                'date' => $date, 'timeframe' => '5m',
            ]))->getData(true);
            $candles = $wJson['nifty_candles'] ?? [];
            $stkB = $wJson['breadth_by_time'] ?? [];
            if (count($candles) < 20) {
                return null;
            }

            try {
                $oJson = $oCtrl->getData(Request::create('/api/options-breadth-monitor/data', 'GET', [
                    'date' => $date, 'timeframe' => '5m',
                ]))->getData(true);
                $optB = $oJson['breadth_by_time'] ?? [];
            } catch (\Throwable $e) {
                return null;
            }
            if (empty($optB)) {
                return null;
            }

            $expiry = DB::table('nse_expiries')
                ->where('trading_symbol', 'NIFTY')
                ->where('instrument_type', 'OPT')
                ->where('expiry_date', '>=', $date)
                ->orderBy('expiry_date')
                ->value('expiry_date');
            if (!$expiry) {
                return null;
            }

            try {
                $tJson = $tCtrl->getData(Request::create('/api/trending-oi/data', 'GET', [
                    'date' => $date, 'expiry' => $expiry, 'interval' => 5,
                ]))->getData(true);
            } catch (\Throwable $e) {
                return null;
            }

            $labels = $tJson['chart']['labels'] ?? [];
            $callOi = $tJson['chart']['call_oi'] ?? [];
            $putOi  = $tJson['chart']['put_oi'] ?? [];
            $sent   = $tJson['chart']['sentiment'] ?? [];

            $toiMap = [];
            foreach ($labels as $idx => $lbl) {
                $tKey = substr((string) $lbl, 0, 5);
                $ce = (float) ($callOi[$idx] ?? 0);
                $pe = (float) ($putOi[$idx] ?? 0);
                $toiMap[$tKey] = isset($sent[$idx]) ? (float) $sent[$idx] : ($pe - $ce);
            }
            if (empty($toiMap)) {
                return null;
            }

            $dayName = Carbon::parse($date)->format('D');
            $cumTpVol = 0.0;
            $cumVol = 0.0;
            $n = count($candles);
            $prevOptBull = false;
            $prevOptBear = false;
            $out = [];

            for ($i = 0; $i < $n - 2; $i++) {
                $c = $candles[$i];
                $vol = max(1.0, (float) ($c['volume'] ?? 1));
                $tp = ((float) $c['high'] + (float) $c['low'] + (float) $c['close']) / 3.0;
                $cumTpVol += $tp * $vol;
                $cumVol += $vol;
                $vwap = $cumVol > 0 ? ($cumTpVol / $cumVol) : (float) $c['close'];

                $tStr = (string) ($c['time_str'] ?? '');
                $cClose = (float) ($c['close'] ?? 0);
                $sb = $stkB[$tStr] ?? null;
                $ob = $optB[$tStr] ?? null;
                if (!$ob) {
                    continue;
                }

                $gWt = (float) ($sb['green_weight'] ?? 0);
                $rWt = (float) ($sb['red_weight'] ?? 0);
                $oUp = (float) ($ob['up_weight'] ?? 0);
                $oDn = (float) ($ob['down_weight'] ?? 0);

                $isFreshBull = ($oUp >= 70 && !$prevOptBull);
                $isFreshBear = ($oDn >= 70 && !$prevOptBear);
                $prevOptBull = ($oUp >= 70);
                $prevOptBear = ($oDn >= 70);

                if ($tStr < '09:50' || $tStr > '14:35') {
                    continue;
                }
                if (!isset($toiMap[$tStr])) {
                    continue;
                }
                $oiDiff = $toiMap[$tStr];

                foreach (['BULL', 'BEAR'] as $dir) {
                    $isFresh = ($dir === 'BULL') ? $isFreshBull : $isFreshBear;
                    $stkWt = ($dir === 'BULL') ? $gWt : $rWt;
                    $optWt = ($dir === 'BULL') ? $oUp : $oDn;
                    $vwapDist = ($dir === 'BULL') ? ($cClose - $vwap) : ($vwap - $cClose);
                    $oiOk = ($dir === 'BULL') ? ($oiDiff > 0) : ($oiDiff < 0);
                    if (!($isFresh && $vwapDist >= 0 && $stkWt >= 60 && $oiOk)) {
                        continue;
                    }

                    // Capture next 3 candles (Bar +1, +2, +3) & check reverse OPT >= $revTh
                    $bars3 = [];
                    $hasRevOpt = false;
                    for ($bIdx = 1; $bIdx <= 3; $bIdx++) {
                        if ($i + $bIdx >= $n) {
                            break;
                        }
                        $bc = $candles[$i + $bIdx];
                        $bt = (string) ($bc['time_str'] ?? '');
                        $bClose = (float) ($bc['close'] ?? $cClose);
                        $bMove = round(($dir === 'BULL') ? ($bClose - $cClose) : ($cClose - $bClose), 1);
                        $bob = $optB[$bt] ?? null;
                        $sameOpt = $bob ? round((float) ($dir === 'BULL' ? ($bob['up_weight'] ?? 0) : ($bob['down_weight'] ?? 0)), 0) : 0;
                        $oppOpt = $bob ? round((float) ($dir === 'BULL' ? ($bob['down_weight'] ?? 0) : ($bob['up_weight'] ?? 0)), 0) : 0;
                        if ($oppOpt >= $revTh) {
                            $hasRevOpt = true;
                        }
                        $bars3[] = [
                            'time'     => $bt,
                            'move'     => $bMove,
                            'same_opt' => $sameOpt,
                            'opp_opt'  => $oppOpt,
                            'is_rev'   => ($oppOpt >= $revTh),
                        ];
                    }

                    // Simulate outcome over next 12 bars (60m)
                    $outcome = 'TIMEOUT';
                    $mfe = 0.0;
                    $mae = 0.0;
                    $endJ = min($n - 1, $i + 12);
                    for ($j = $i + 1; $j <= $endJ; $j++) {
                        $h = (float) $candles[$j]['high'];
                        $l = (float) $candles[$j]['low'];
                        $fav = ($dir === 'BULL') ? ($h - $cClose) : ($cClose - $l);
                        $adv = ($dir === 'BULL') ? ($cClose - $l) : ($h - $cClose);
                        $mfe = max($mfe, $fav);
                        $mae = max($mae, $adv);
                        if ($outcome === 'TIMEOUT') {
                            if ($adv >= $slPts) {
                                $outcome = 'LOSS';
                            } elseif ($fav >= $tgtPts) {
                                $outcome = 'WIN';
                            }
                        }
                    }

                    $endClose = (float) ($candles[$endJ]['close'] ?? $cClose);
                    $timeoutPts = round(($dir === 'BULL') ? ($endClose - $cClose) : ($cClose - $endClose), 1);
                    $netPts = ($outcome === 'WIN') ? $tgtPts : (($outcome === 'LOSS') ? -$slPts : $timeoutPts);

                    $out[] = [
                        'date'       => $date,
                        'day_name'   => $dayName,
                        'expiry'     => $expiry,
                        'time'       => $tStr,
                        'dir'        => $dir,
                        'spot'       => round($cClose, 2),
                        'vwap_dist'  => round($vwapDist, 1),
                        'opt_pct'    => round($optWt, 1),
                        'stk_pct'    => round($stkWt, 1),
                        'oi_diff_l'  => round($oiDiff / 100000, 1),
                        'clean_3bar' => !$hasRevOpt,
                        'bars3'      => $bars3,
                        'outcome'    => $outcome,
                        'net_pts'    => $netPts,
                        'mfe'        => round($mfe, 1),
                        'mae'        => round($mae, 1),
                    ];
                }
            }

            return $out;
        });
    }
}
