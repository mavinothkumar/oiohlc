<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Carbon\Carbon;

class StrangleController extends Controller
{
    public function index(Request $request): View
    {
        $strikes = [];
        $expiry = $request->get('expiry', '2025-01-30');

        // Parse strike-instrument_type parameters
        if ($request->has('strike')) {
            $strikeParams = explode(',', $request->get('strike'));
            foreach ($strikeParams as $param) {
                [$strike, $instrumentType] = explode('-', $param);
                $strikes[] = [
                    'strike' => (int) $strike,
                    'instrument_type' => strtoupper($instrumentType)
                ];
            }
        }

        $data = $this->calculateMonthlyStrangleProfit($strikes, $expiry);

        return view('strangle.profit', compact('data', 'strikes', 'expiry'));
    }

    private function calculateMonthlyStrangleProfit(array $strikes, string $expiry): array
    {
        $monthlyData = [];
        $datesIndex = []; // map date => index for alignment

        // 1) Build per-leg data
        foreach ($strikes as $strikeData) {
            $allDays = DB::table('expired_ohlc')
                         ->where('strike', $strikeData['strike'])
                         ->where('instrument_type', $strikeData['instrument_type'])
                         ->where('expiry', $expiry)
                         ->where('interval', 'day')
                         ->orderBy('timestamp')
                         ->get();

            $dailyPnL = [];
            $prevOpen = null;

            foreach ($allDays as $idx => $dayData) {
                $dayDate = \Carbon\Carbon::parse($dayData->timestamp)->format('Y-m-d');

                if (!array_key_exists($dayDate, $datesIndex)) {
                    $datesIndex[$dayDate] = count($datesIndex);
                }

                if ($prevOpen !== null) {
                    $pnlAbs = $dayData->open - $prevOpen; // open-to-open
                    $pnlPct = $prevOpen != 0
                        ? round(($dayData->open - $prevOpen) / $prevOpen * 100, 2)
                        : 0;
                } else {
                    $pnlAbs = 0;
                    $pnlPct = 0;
                }

                $dailyPnL[] = [
                    'date'       => $dayDate,
                    'timestamp'  => $dayData->timestamp,
                    'open'       => $dayData->open,
                    'high'       => $dayData->high,
                    'low'        => $dayData->low,
                    'close'      => $dayData->close,
                    'volume'     => $dayData->volume,
                    'oi'         => $dayData->open_interest,
                    'prev_open'  => $prevOpen,
                    'pnl_abs'    => $pnlAbs,
                    'pnl_pct'    => $pnlPct,
                ];

                $prevOpen = $dayData->open;
            }

            $strikeKey = $strikeData['strike'].'-'.$strikeData['instrument_type'];

            $monthlyData['legs'][$strikeKey] = [
                'strike'         => $strikeData['strike'],
                'instrument_type'=> $strikeData['instrument_type'],
                'daily_data'     => $dailyPnL,
                'total_pnl_abs'  => collect($dailyPnL)->sum('pnl_abs'),
                'total_pnl_pct'  => collect($dailyPnL)->avg('pnl_pct'),
                'trading_days'   => count($dailyPnL),
            ];
        }

        // 2) Build combined CE+PE by date (assumes exactly two legs: one CE, one PE)
        $dates = array_keys($datesIndex);
        sort($dates);

        $combinedDaily = [];
        $cumStranglePnl = 0;
        $cumStranglePnlPct = 0;

        // determine which key is CE and which is PE
        $ceKey = null;
        $peKey = null;
        foreach ($monthlyData['legs'] ?? [] as $k => $leg) {
            if ($leg['instrument_type'] === 'CE') {
                $ceKey = $k;
            } elseif ($leg['instrument_type'] === 'PE') {
                $peKey = $k;
            }
        }

        foreach ($dates as $i => $date) {
            $ce = $ceKey ? collect($monthlyData['legs'][$ceKey]['daily_data'])->firstWhere('date', $date) : null;
            $pe = $peKey ? collect($monthlyData['legs'][$peKey]['daily_data'])->firstWhere('date', $date) : null;

            if (!$ce && !$pe) {
                continue;
            }

            $ceOpen = $ce['open'] ?? null;
            $peOpen = $pe['open'] ?? null;

            $cePnlAbs = $ce['pnl_abs'] ?? 0;
            $pePnlAbs = $pe['pnl_abs'] ?? 0;

            $dayStranglePnl = $cePnlAbs + $pePnlAbs;
            $cumStranglePnl += $dayStranglePnl;

            // For percentage, sum base and recompute simplest way
            $dayStranglePnlPct = 0;
            $entryBase = 0;
            if ($i > 0) {
                $prevCeOpen = $ce['prev_open'] ?? 0;
                $prevPeOpen = $pe['prev_open'] ?? 0;
                $entryBase = $prevCeOpen + $prevPeOpen;
                if ($entryBase != 0) {
                    $dayStranglePnlPct = round($dayStranglePnl / $entryBase * 100, 2);
                }
            }

            if ($i == 0) {
                $entryBaseFirst = ($ceOpen ?? 0) + ($peOpen ?? 0);
                $cumStranglePnl = 0;
                $cumStranglePnlPct = 0;
            } else {
                $cumStranglePnlPct = $entryBaseFirst != 0
                    ? round($cumStranglePnl / $entryBaseFirst * 100, 2)
                    : 0;
            }

            $combinedDaily[] = [
                'date'                 => $date,
                'timestamp'            => $ce['timestamp'] ?? $pe['timestamp'] ?? null,
                'ce'                   => $ce,
                'pe'                   => $pe,
                'ce_pe_sum_today'      => ($ceOpen ?? 0) + ($peOpen ?? 0),
                'ce_pe_sum_cum'        => $entryBaseFirst != 0 ? $entryBaseFirst + $cumStranglePnl : $cumStranglePnl,
                'day_strangle_pnl'     => $dayStranglePnl,
                'day_strangle_pnl_pct' => $dayStranglePnlPct,
                'cum_strangle_pnl'     => $cumStranglePnl,
                'cum_strangle_pnl_pct' => $cumStranglePnlPct,
            ];
        }

        $monthlyData['combined'] = $combinedDaily;

        return [
            'strikes'      => $monthlyData['legs'] ?? [],
            'combined'     => $monthlyData['combined'] ?? [],
            'trading_days' => $dates,
        ];
    }

}
