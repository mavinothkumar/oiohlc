<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\DB;
use App\TableName;
use Carbon\Carbon;

class OIChainController extends Controller
{
    public function index(Request $request)
    {
        // Resolve working date
        $workingDate = $this->resolveWorkingDate($request->input('date'));

        // Resolve expiry
        $currentExpiry = DB::get(TableName::NSE_EXPIRIES)
                           ->where('is_current', 1)
                           ->first();

        $expiry = $request->input('expiry', $currentExpiry['expiry_date'] ?? null);

        // Time filter (default: 09:15 to 10:15 session)
        $fromTime = $request->input('from_time', '09:15');
        $toTime   = $request->input('to_time', '10:15');

        $fromDt = Carbon::parse("{$workingDate} {$fromTime}");
        $toDt   = Carbon::parse("{$workingDate} {$toTime}");

        // ── Top OI by Strike (CE & PE separately) ──────────────────────────
        $topOiRaw = DB::get(TableName::OPTION_CHAINS)
                      ->select('strike_price', 'option_type', 'oi', 'ltp', 'build_up', 'captured_at')
                      ->where('expiry', $expiry)
                      ->where('underlying_key', $request->input('underlying', 'NSE_INDEX|Nifty 50'))
                      ->where('captured_at', '>=', $fromDt->toDateTimeString())
                      ->where('captured_at', '<=', $toDt->toDateTimeString())
                      ->orderBy('captured_at', 'DESC')
                      ->get();

        // Group: latest snapshot per strike+option_type
        $latestOi = [];
        foreach ($topOiRaw as $row) {
            $key = $row['strike_price'] . '_' . $row['option_type'];
            if (!isset($latestOi[$key])) {
                $latestOi[$key] = $row;
            }
        }

        // Top 15 CE OI and top 15 PE OI
        $ceOi = array_filter($latestOi, fn($r) => $r['option_type'] === 'CE');
        $peOi = array_filter($latestOi, fn($r) => $r['option_type'] === 'PE');

        usort($ceOi, fn($a, $b) => $b['oi'] <=> $a['oi']);
        usort($peOi, fn($a, $b) => $b['oi'] <=> $a['oi']);

        $topCe = array_slice(array_values($ceOi), 0, 15);
        $topPe = array_slice(array_values($peOi), 0, 15);

        // ── OI Change Timeline (per candle slot, per option_type) ───────────
        // Group by captured_at → strike_price → option_type
        $timelineRaw = DB::get(TableName::OPTION_CHAINS)
                         ->select('strike_price', 'option_type', 'diff_oi', 'oi', 'build_up', 'captured_at')
                         ->where('expiry', $expiry)
                         ->where('underlying_key', $request->input('underlying', 'NSE_INDEX|Nifty 50'))
                         ->where('captured_at', '>=', $fromDt->toDateTimeString())
                         ->where('captured_at', '<=', $toDt->toDateTimeString())
                         ->orderBy('captured_at', 'ASC')
                         ->get();

        // Build timeline slots
        $timelineSlots = [];
        foreach ($timelineRaw as $row) {
            $slot = Carbon::parse($row['captured_at'])->format('H:i');
            $timelineSlots[$slot][$row['option_type']][] = $row;
        }

        // ── Build-up count per time slot ────────────────────────────────────
        $buildupTimeline = [];
        $buildTypes = ['Long Build', 'Short Build', 'Short Cover', 'Long Unwind'];
        foreach ($timelineSlots as $slot => $types) {
            $buildupTimeline[$slot] = [];
            foreach (['CE', 'PE'] as $ot) {
                $counts = array_fill_keys($buildTypes, 0);
                $totalDiffOi = 0;
                foreach ($types[$ot] ?? [] as $r) {
                    if ($r['build_up']) $counts[$r['build_up']]++;
                    $totalDiffOi += (int)$r['diff_oi'];
                }
                $buildupTimeline[$slot][$ot] = [
                    'counts'      => $counts,
                    'total_diff_oi' => $totalDiffOi,
                    'dominant'    => array_search(max($counts), $counts),
                ];
            }
        }

        // ── Recent Massive OI Change (last 2 candles = 10 mins) ─────────────
        $recentSlots = array_slice(array_keys($timelineSlots), -2, 2);
        $massiveChanges = [];
        foreach ($recentSlots as $slot) {
            foreach (['CE', 'PE'] as $ot) {
                $rows = $timelineSlots[$slot][$ot] ?? [];
                usort($rows, fn($a, $b) => abs($b['diff_oi']) <=> abs($a['diff_oi']));
                foreach (array_slice($rows, 0, 5) as $r) {
                    if (abs($r['diff_oi']) > 0) {
                        $massiveChanges[] = array_merge($r, ['slot' => $slot]);
                    }
                }
            }
        }

        // ── Market Bias ──────────────────────────────────────────────────────
        $bias = $this->computeBias($buildupTimeline);

        // ── Underlying Spot ──────────────────────────────────────────────────
        $spotRow = DB::get(TableName::OPTION_CHAINS)
                     ->select('underlying_spot_price', 'captured_at')
                     ->where('underlying_key', $request->input('underlying', 'NSE_INDEX|Nifty 50'))
                     ->where('captured_at', '>=', $fromDt->toDateTimeString())
                     ->where('captured_at', '<=', $toDt->toDateTimeString())
                     ->orderBy('captured_at', 'DESC')
                     ->first();

        return view('oi-chain.index', compact(
            'workingDate', 'expiry', 'fromTime', 'toTime',
            'topCe', 'topPe',
            'buildupTimeline', 'massiveChanges',
            'bias', 'spotRow',
            'currentExpiry'
        ));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function resolveWorkingDate(?string $inputDate): string
    {
        if ($inputDate) {
            return $inputDate;
        }

        $today = Carbon::today()->toDateString();

        $current = DB::get(TableName::NSE_WORKING_DAYS)
                     ->where('current', 1)
                     ->first();

        if ($current) {
            return $current['working_date'];
        }

        // Fallback: check if today is a working day
        $row = DB::get(TableName::NSE_WORKING_DAYS)
                 ->where('working_date', $today)
                 ->first();

        if ($row) return $today;

        // Get previous working day
        $prev = DB::get(TableName::NSE_WORKING_DAYS)
                  ->where('working_date', '<', $today)
                  ->orderBy('working_date', 'DESC')
                  ->first();

        return $prev['working_date'] ?? $today;
    }

    private function computeBias(array $buildupTimeline): array
    {
        $ceBuildup  = 0; $ceCover = 0;
        $peBuildup  = 0; $peCover = 0;
        $ceSb = 0; $peSb = 0;

        foreach ($buildupTimeline as $slot => $types) {
            $ceSb  += $types['CE']['counts']['Short Build']  ?? 0;
            $peSb  += $types['PE']['counts']['Short Build']  ?? 0;
            $ceBuildup += ($types['CE']['counts']['Long Build'] ?? 0)
                          + ($types['CE']['counts']['Short Build'] ?? 0);
            $peBuildup += ($types['PE']['counts']['Long Build'] ?? 0)
                          + ($types['PE']['counts']['Short Build'] ?? 0);
            $ceCover += $types['CE']['counts']['Short Cover'] ?? 0;
            $peCover += $types['PE']['counts']['Short Cover'] ?? 0;
        }

        if ($ceSb > 2 && $peSb > 2) {
            $direction = 'SIDEWAYS';
            $color = 'yellow';
        } elseif ($peBuildup > $ceBuildup) {
            $direction = 'BULLISH';
            $color = 'green';
        } elseif ($ceBuildup > $peBuildup) {
            $direction = 'BEARISH';
            $color = 'red';
        } else {
            $direction = 'NEUTRAL';
            $color = 'gray';
        }

        return compact('direction', 'color', 'ceBuildup', 'peBuildup', 'ceSb', 'peSb', 'ceCover', 'peCover');
    }
}
