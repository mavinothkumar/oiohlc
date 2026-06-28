<?php

namespace App\Http\Controllers;

use App\Models\OptionChain;
use App\Models\NseExpiry;
use App\Models\NseWorkingDay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OiAnalysisController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'date'   => 'nullable|date',
            'expiry' => 'nullable|date',
            'time'   => 'nullable|date_format:H:i',
        ]);

        // ── Resolve Date ──────────────────────────────────────────────
        if (!empty($validated['date'])) {
            $date = $validated['date'];
        } else {
            $wd = DB::table('nse_working_days')->where('current', 1)->first()
                  ?? DB::table('nse_working_days')->where('previous', 1)->orderByDesc('working_date')->first();
            $date = $wd ? $wd->working_date->format('Y-m-d') : now()->format('Y-m-d');
        }

        // ── Resolve Expiry ────────────────────────────────────────────
        if (!empty($validated['expiry'])) {
            $expiry = $validated['expiry'];
        } else {
            $exp = DB::table('nse_expiries')->where('is_current', 1)
                            ->where('instrument_type', 'OPTIDX')
                            ->first();
            $expiry = $exp ? $exp->expiry_date->format('Y-m-d') : $date;
        }

        // ── Resolve End Time ──────────────────────────────────────────
        $endTime = $validated['time'] ?? '15:25';

        // ── Get Initial Spot Price → ATM ─────────────────────────────
        $initialRow = OptionChain::where('underlying_key', 'NIFTY')
                                 ->where('expiry', $expiry)
                                 ->whereDate('captured_at', $date)
                                 ->whereTime('captured_at', '>=', '09:15:00')
                                 ->whereTime('captured_at', '<=', '09:20:00')
                                 ->where('option_type', 'CE')
                                 ->first();

        if (!$initialRow) {
            $initialRow = OptionChain::where('underlying_key', 'NIFTY')
                                     ->where('expiry', $expiry)
                                     ->whereDate('captured_at', $date)
                                     ->orderBy('captured_at')
                                     ->where('option_type', 'CE')
                                     ->first();
        }

        $spotPrice = $initialRow ? (float) $initialRow->underlying_spot_price : 24000;
        $atmStrike = round($spotPrice / 50) * 50;
        $lowerStrike = $atmStrike - 1000;
        $upperStrike = $atmStrike + 1000;

        // ── Fetch All Relevant Rows ──────────────────────────────────
        $allRows = OptionChain::where('underlying_key', 'NIFTY')
                              ->where('expiry', $expiry)
                              ->whereDate('captured_at', $date)
                              ->whereTime('captured_at', '>=', '09:15:00')
                              ->whereTime('captured_at', '<=', $endTime . ':59')
                              ->whereBetween('strike_price', [$lowerStrike, $upperStrike])
                              ->orderBy('captured_at')
                              ->orderBy('strike_price')
                              ->orderBy('option_type')
                              ->get();

        if ($allRows->isEmpty()) {
            return view('oi-analysis', $this->emptyPayload($date, $expiry, $endTime, $spotPrice, $atmStrike));
        }

        // ── Extract Time Slots & Strikes ─────────────────────────────
        $timeSlots = $allRows->pluck('captured_at')
                             ->map(fn($t) => $t->format('H:i'))
                             ->unique()->sort()->values();

        $strikes = $allRows->pluck('strike_price')
                           ->unique()->sort()->values()
                           ->map(fn($s) => $this->fmtStrike($s));

        // ── Build Heatmap Data Structure ─────────────────────────────
        $heatmap = [];
        foreach ($allRows as $row) {
            $t = $row->captured_at->format('H:i');
            $s = $this->fmtStrike($row->strike_price);
            $type = $row->option_type;

            $heatmap[$s][$t][$type] = [
                'diff_oi'  => (int) ($row->diff_oi ?? 0),
                'oi'       => (int) ($row->oi ?? 0),
                'build_up' => $row->build_up,
                'ltp'      => (float) ($row->ltp ?? 0),
                'volume'   => (int) ($row->volume ?? 0),
            ];
        }

        // ── Latest Spot ─────────────────────────────────────────────
        $latestRow = $allRows->last();
        $latestSpot = (float) ($latestRow->underlying_spot_price ?? $spotPrice);
        $latestTime = $timeSlots->last();

        // ── Top OI (Latest Slot) ────────────────────────────────────
        $topOiCe = $topOiPe = [];
        foreach ($strikes as $s) {
            $ce = $heatmap[$s][$latestTime]['CE'] ?? null;
            $pe = $heatmap[$s][$latestTime]['PE'] ?? null;
            if ($ce && $ce['oi'] > 0) {
                $topOiCe[] = ['strike' => $s, 'oi' => $ce['oi'], 'diff_oi' => $ce['diff_oi'], 'build_up' => $ce['build_up']];
            }
            if ($pe && $pe['oi'] > 0) {
                $topOiPe[] = ['strike' => $s, 'oi' => $pe['oi'], 'diff_oi' => $pe['diff_oi'], 'build_up' => $pe['build_up']];
            }
        }
        usort($topOiCe, fn($a, $b) => $b['oi'] <=> $a['oi']);
        usort($topOiPe, fn($a, $b) => $b['oi'] <=> $a['oi']);
        $topOiCe = array_slice($topOiCe, 0, 10);
        $topOiPe = array_slice($topOiPe, 0, 10);

        // ── Recent Massive Changes ──────────────────────────────────
        $recentChanges = $this->getRecentChanges($heatmap, $strikes, $timeSlots);

        // ── Pattern Reversals ───────────────────────────────────────
        $reversals = $this->detectReversals($heatmap, $strikes, $timeSlots);

        // ── Direction Signal ────────────────────────────────────────
        $direction = $this->calcDirection($heatmap, $strikes, $timeSlots, $atmStrike);

        // ── Net OI Flow Per Time Slot ───────────────────────────────
        $netFlow = $this->calcNetFlow($heatmap, $strikes, $timeSlots);

        // ── Current Expiry for Filter ───────────────────────────────
        $currentExpiry = NseExpiry::where('is_current', 1)
                                  ->where('instrument_type', 'OPTIDX')
                                  ->first();

        return view('oi-analysis', compact(
            'date', 'expiry', 'endTime', 'timeSlots', 'strikes',
            'heatmap', 'topOiCe', 'topOiPe', 'recentChanges',
            'reversals', 'direction', 'spotPrice', 'latestSpot',
            'atmStrike', 'netFlow', 'currentExpiry'
        ));
    }

    /* ───────────── Helpers ───────────── */

    private function fmtStrike($val): string
    {
        $f = (float) $val;
        return $f == floor($f) ? (string) (int) $f : (string) $f;
    }

    private function emptyPayload($date, $expiry, $endTime, $spot, $atm): array
    {
        return [
            'date' => $date, 'expiry' => $expiry, 'endTime' => $endTime,
            'timeSlots' => collect(), 'strikes' => collect(),
            'heatmap' => [], 'topOiCe' => [], 'topOiPe' => [],
            'recentChanges' => [], 'reversals' => [],
            'direction' => ['signal' => 'NO_DATA', 'confidence' => 0, 'bullish' => 0, 'bearish' => 0, 'ce_sb' => 0, 'pe_sb' => 0],
            'spotPrice' => $spot, 'latestSpot' => $spot, 'atmStrike' => $atm,
            'netFlow' => [], 'currentExpiry' => null,
        ];
    }

    private function getRecentChanges($heatmap, $strikes, $timeSlots): array
    {
        $result = [];
        $tArr = $timeSlots->toArray();

        foreach ([5, 10, 15] as $window) {
            $numSlots = intdiv($window, 5);
            $startIdx = max(0, count($tArr) - $numSlots);
            $windowSlots = array_slice($tArr, $startIdx);
            if (count($windowSlots) < 2) { $result[$window . 'min'] = []; continue; }

            $changes = [];
            foreach ($strikes as $s) {
                foreach (['CE', 'PE'] as $type) {
                    $totalDiff = 0;
                    $builds = [];
                    $firstOi = $lastOi = 0;
                    foreach ($windowSlots as $i => $t) {
                        $cell = $heatmap[$s][$t][$type] ?? null;
                        if ($cell) {
                            $totalDiff += $cell['diff_oi'];
                            if ($cell['build_up']) $builds[] = $cell['build_up'];
                            if ($i === 0) $firstOi = $cell['oi'];
                            if ($i === count($windowSlots) - 1) $lastOi = $cell['oi'];
                        }
                    }
                    if (abs($totalDiff) < 5000) continue;

                    $bc = array_count_values($builds);
                    arsort($bc);
                    $changes[] = [
                        'strike'    => $s,
                        'type'      => $type,
                        'total_diff'=> $totalDiff,
                        'dominant'  => array_key_first($bc) ?: null,
                        'oi_change' => $lastOi - $firstOi,
                    ];
                }
            }
            usort($changes, fn($a, $b) => abs($b['total_diff']) <=> abs($a['total_diff']));
            $result[$window . 'min'] = array_slice($changes, 0, 8);
        }
        return $result;
    }

    private function detectReversals($heatmap, $strikes, $timeSlots): array
    {
        $tArr = $timeSlots->toArray();
        $earlySlots = array_values(array_filter($tArr, fn($t) => $t >= '09:20' && $t < '09:45'));
        $lateSlots  = array_values(array_filter($tArr, fn($t) => $t >= '09:45' && $t <= '10:00'));
        $reversals = [];

        if (empty($earlySlots) || empty($lateSlots)) return $reversals;

        $bullish = ['Long Build', 'Short Cover'];
        $bearish = ['Short Build', 'Long Unwind'];

        foreach ($strikes as $s) {
            foreach (['CE', 'PE'] as $type) {
                $eBuilds = []; $lBuilds = [];
                foreach ($earlySlots as $t) {
                    $c = $heatmap[$s][$t][$type] ?? null;
                    if ($c && $c['build_up']) $eBuilds[] = $c['build_up'];
                }
                foreach ($lateSlots as $t) {
                    $c = $heatmap[$s][$t][$type] ?? null;
                    if ($c && $c['build_up']) $lBuilds[] = $c['build_up'];
                }
                if (empty($eBuilds) || empty($lBuilds)) continue;

                $ec = array_count_values($eBuilds); arsort($ec);
                $lc = array_count_values($lBuilds); arsort($lc);
                $eDom = array_key_first($ec);
                $lDom = array_key_first($lc);

                if (in_array($eDom, $bearish) && in_array($lDom, $bullish)) {
                    $reversals[] = ['strike' => $s, 'type' => $type, 'from' => $eDom, 'to' => $lDom, 'dir' => 'BULLISH'];
                }
                if (in_array($eDom, $bullish) && in_array($lDom, $bearish)) {
                    $reversals[] = ['strike' => $s, 'type' => $type, 'from' => $eDom, 'to' => $lDom, 'dir' => 'BEARISH'];
                }
            }
        }
        return $reversals;
    }

    private function calcDirection($heatmap, $strikes, $timeSlots, $atm): array
    {
        $tArr = $timeSlots->toArray();
        $analysisSlots = array_values(array_filter($tArr, fn($t) => $t >= '09:30' && $t <= '10:00'));
        if (count($analysisSlots) < 2) {
            return ['signal' => 'INSUFFICIENT', 'confidence' => 0, 'bull' => 0, 'bear' => 0, 'ceSb' => 0, 'peSb' => 0];
        }

        $bull = $bear = $ceSb = $peSb = 0;
        $focusStrikes = $strikes->filter(fn($s) => abs((float)$s - $atm) <= 500);

        foreach ($focusStrikes as $s) {
            foreach ($analysisSlots as $t) {
                $ce = $heatmap[$s][$t]['CE'] ?? null;
                $pe = $heatmap[$s][$t]['PE'] ?? null;

                if ($ce && $ce['build_up']) {
                    $w = abs($ce['diff_oi']);
                    if ($ce['build_up'] === 'Long Build') {
                        $bull += $w;
                    } elseif ($ce['build_up'] === 'Short Cover') {
                        $bull += $w * 0.5;
                    } elseif ($ce['build_up'] === 'Short Build') {
                        $bear += $w;
                        $ceSb++;
                    } elseif ($ce['build_up'] === 'Long Unwind') {
                        $bear += $w * 0.5;
                    }
                }

                if ($pe && $pe['build_up']) {
                    $w = abs($pe['diff_oi']);
                    if ($pe['build_up'] === 'Long Build') {
                        $bear += $w;
                    } elseif ($pe['build_up'] === 'Short Cover') {
                        $bear += $w * 0.5;
                    } elseif ($pe['build_up'] === 'Short Build') {
                        $bull += $w;
                        $peSb++;
                    } elseif ($pe['build_up'] === 'Long Unwind') {
                        $bull += $w * 0.5;
                    }
                }
            }
        }

        $total = $bull + $bear;
        $conf = $total > 0 ? round(abs($bull - $bear) / $total * 100) : 0;
        $sideways = ($ceSb > 3 && $peSb > 3 && $conf < 30);

        $signal = 'SIDEWAYS';
        if (!$sideways) {
            if ($bull > $bear * 1.3) {
                $signal = 'BULLISH';
            } elseif ($bear > $bull * 1.3) {
                $signal = 'BEARISH';
            }
        }

        return [
            'signal' => $signal,
            'conf'   => $conf,
            'bull'   => $bull,
            'bear'   => $bear,
            'ceSb'   => $ceSb,
            'peSb'   => $peSb,
        ];
    }

    private function calcNetFlow($heatmap, $strikes, $timeSlots): array
    {
        $flow = [];
        foreach ($timeSlots as $t) {
            $ceSum = $peSum = 0;
            foreach ($strikes as $s) {
                $ce = $heatmap[$s][$t]['CE'] ?? null;
                $pe = $heatmap[$s][$t]['PE'] ?? null;
                if ($ce) {
                    $w = match($ce['build_up']) {
                        'Long Build', 'Short Cover' => 1,
                        'Short Build', 'Long Unwind' => -1,
                        default => 0,
                    };
                    $ceSum += $w * log(abs($ce['diff_oi']) + 1);
                }
                if ($pe) {
                    $w = match($pe['build_up']) {
                        'Long Build', 'Short Cover' => -1,
                        'Short Build', 'Long Unwind' => 1,
                        default => 0,
                    };
                    $peSum += $w * log(abs($pe['diff_oi']) + 1);
                }
            }
            $flow[] = ['time' => $t, 'net' => $ceSum + $peSum];
        }
        return $flow;
    }
}
