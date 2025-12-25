<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IndexOptionAnalysisController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('index_option_analysis')
                   ->orderByDesc('trade_date')
                   ->orderBy('underlying_symbol');

        if ($request->filled('symbol')) {
            $query->where('underlying_symbol', 'like', '%'.$request->symbol.'%');
        }

        if ($request->filled('from')) {
            $query->whereDate('trade_date', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('trade_date', '<=', $request->to);
        }

        if ($request->filled('atm')) {
            $query->where('atm_strike', $request->atm);
        }

        $rows = $query->paginate(500)->withQueryString(); // Tailwind-ready pagination [web:74][web:71]

        $summary = $rows->getCollection()->reduce(function ($carry, $row) {
            $curClose = $row->cur_index_close;

            // -------- recompute candidate levels (same as in Blade) --------
            $cePlus  = $row->range_ce_low_plus;
            $ceMinus = $row->range_ce_low_minus;

            $pePlus  = $row->cur_pe_low !== null ? $row->cur_index_low + $row->cur_pe_low : null;
            $peMinus = $row->cur_pe_low !== null ? $row->cur_index_low - $row->cur_pe_low : null;

            $avgLowPlus  = $row->range_avg_low_plus;
            $avgLowMinus = $row->range_avg_low_minus;

            $avgHighPlus  = $row->range_avg_high_plus;
            $avgHighMinus = $row->range_avg_high_minus;

            $idxHighPlusAvgLow  = $row->cur_index_high + $row->avg_low;
            $idxHighMinusAvgLow = $row->cur_index_high - $row->avg_low;

            $idxHighPlusAvgHigh  = $row->cur_index_high + $row->avg_high;
            $idxHighMinusAvgHigh = $row->cur_index_high - $row->avg_high;

            $avgHLDiff = $row->avg_high - $row->avg_low;
            $avgHLSum  = $row->avg_high + $row->avg_low;

            $idxLowPlusAvgDiff  = $row->cur_index_low + $avgHLDiff;
            $idxLowMinusAvgDiff = $row->cur_index_low - $avgHLDiff;

            $idxLowPlusAvgSum  = $row->cur_index_low + $avgHLSum;
            $idxLowMinusAvgSum = $row->cur_index_low - $avgHLSum;

            $idxHighPlusAvgDiff  = $row->cur_index_high + $avgHLDiff;
            $idxHighMinusAvgDiff = $row->cur_index_high - $avgHLDiff;

            $idxHighPlusAvgSum  = $row->cur_index_high + $avgHLSum;
            $idxHighMinusAvgSum = $row->cur_index_high - $avgHLSum;

            $idxMid = ($row->cur_index_high + $row->cur_index_low) / 2;

            $midPlusAvgHigh  = $idxMid + $row->avg_high;
            $midMinusAvgHigh = $idxMid - $row->avg_high;

            $midPlusAvgLow  = $idxMid + $row->avg_low;
            $midMinusAvgLow = $idxMid - $row->avg_low;

            $candidates = [];

            if ($cePlus !== null)      $candidates['ce_plus']       = $cePlus;
            if ($ceMinus !== null)     $candidates['ce_minus']      = $ceMinus;
            if ($pePlus !== null)      $candidates['pe_plus']       = $pePlus;
            if ($peMinus !== null)     $candidates['pe_minus']      = $peMinus;
            if ($avgLowPlus !== null)  $candidates['avg_low_plus']  = $avgLowPlus;
            if ($avgLowMinus !== null) $candidates['avg_low_minus'] = $avgLowMinus;
            if ($avgHighPlus !== null) $candidates['avg_high_plus'] = $avgHighPlus;
            if ($avgHighMinus !== null)$candidates['avg_high_minus']= $avgHighMinus;

            $candidates['idx_high_plus_avg_low']   = $idxHighPlusAvgLow;
            $candidates['idx_high_minus_avg_low']  = $idxHighMinusAvgLow;
            $candidates['idx_high_plus_avg_high']  = $idxHighPlusAvgHigh;
            $candidates['idx_high_minus_avg_high'] = $idxHighMinusAvgHigh;

            $candidates['idx_low_plus_avg_diff']   = $idxLowPlusAvgDiff;
            $candidates['idx_low_minus_avg_diff']  = $idxLowMinusAvgDiff;
            $candidates['idx_low_plus_avg_sum']    = $idxLowPlusAvgSum;
            $candidates['idx_low_minus_avg_sum']   = $idxLowMinusAvgSum;

            $candidates['idx_high_plus_avg_diff']  = $idxHighPlusAvgDiff;
            $candidates['idx_high_minus_avg_diff'] = $idxHighMinusAvgDiff;
            $candidates['idx_high_plus_avg_sum']   = $idxHighPlusAvgSum;
            $candidates['idx_high_minus_avg_sum']  = $idxHighMinusAvgSum;

            $candidates['mid_plus_avg_high']  = $midPlusAvgHigh;
            $candidates['mid_minus_avg_high'] = $midMinusAvgHigh;
            $candidates['mid_plus_avg_low']   = $midPlusAvgLow;
            $candidates['mid_minus_avg_low']  = $midMinusAvgLow;

            $closestKey = null;
            $closestDiff = null;

            foreach ($candidates as $key => $value) {
                $d = abs($value - $curClose);
                if ($closestDiff === null || $d < $closestDiff) {
                    $closestDiff = $d;
                    $closestKey  = $key;
                }
            }

            // map closestKey into bucket + side
            $bucket = null;
            $side   = null; // '+' or '-'

            if ($closestKey === 'ce_plus' || $closestKey === 'ce_minus') {
                $bucket = 'idx_low_ce';
                $side   = $closestKey === 'ce_plus' ? '+' : '-';
            } elseif ($closestKey === 'pe_plus' || $closestKey === 'pe_minus') {
                $bucket = 'idx_low_pe';
                $side   = $closestKey === 'pe_plus' ? '+' : '-';
            } elseif (in_array($closestKey, ['avg_low_plus','avg_low_minus'], true)) {
                $bucket = 'idx_low_avg_low';
                $side   = $closestKey === 'avg_low_plus' ? '+' : '-';
            } elseif (in_array($closestKey, ['avg_high_plus','avg_high_minus'], true)) {
                $bucket = 'idx_low_avg_high';
                $side   = $closestKey === 'avg_high_plus' ? '+' : '-';
            } elseif (in_array($closestKey, [
                'idx_high_plus_avg_low','idx_high_minus_avg_low',
                'idx_high_plus_avg_high','idx_high_minus_avg_high',
            ], true)) {
                $bucket = 'idx_high_avg';
                $side   = str_contains($closestKey, 'plus') ? '+' : '-';
            } elseif (in_array($closestKey, [
                'mid_plus_avg_high','mid_minus_avg_high',
                'mid_plus_avg_low','mid_minus_avg_low',
            ], true)) {
                $bucket = 'mid_avg';
                $side   = str_contains($closestKey, 'plus') ? '+' : '-';
            }

            if ($bucket && $side) {
                $carry[$bucket][$side] = ($carry[$bucket][$side] ?? 0) + 1;
            }

            return $carry;
        }, [
            'idx_low_ce'      => ['+' => 0, '-' => 0],
            'idx_low_pe'      => ['+' => 0, '-' => 0],
            'idx_low_avg_low' => ['+' => 0, '-' => 0],
            'idx_low_avg_high'=> ['+' => 0, '-' => 0],
            'idx_high_avg'    => ['+' => 0, '-' => 0],
            'mid_avg'         => ['+' => 0, '-' => 0],
        ]);
        $within10 = $rows->getCollection()->reduce(function ($carry, $row) {
            $curClose = $row->cur_index_close;

            // Existing levels
            $cePlus  = $row->range_ce_low_plus;
            $ceMinus = $row->range_ce_low_minus;

            $pePlus  = $row->cur_pe_low !== null ? $row->cur_index_low + $row->cur_pe_low : null;
            $peMinus = $row->cur_pe_low !== null ? $row->cur_index_low - $row->cur_pe_low : null;

            $avgLowPlus  = $row->range_avg_low_plus;
            $avgLowMinus = $row->range_avg_low_minus;

            $avgHighPlus  = $row->range_avg_high_plus;
            $avgHighMinus = $row->range_avg_high_minus;

            $idxHighPlusAvgLow  = $row->cur_index_high + $row->avg_low;
            $idxHighMinusAvgLow = $row->cur_index_high - $row->avg_low;

            $idxHighPlusAvgHigh  = $row->cur_index_high + $row->avg_high;
            $idxHighMinusAvgHigh = $row->cur_index_high - $row->avg_high;

            $idxMid = ($row->cur_index_high + $row->cur_index_low) / 2;
            $midPlusAvgHigh  = $idxMid + $row->avg_high;
            $midMinusAvgHigh = $idxMid - $row->avg_high;
            $midPlusAvgLow   = $idxMid + $row->avg_low;
            $midMinusAvgLow  = $idxMid - $row->avg_low;

            // NEW: Index High - CE / PE Low / High
            $idxHighMinusCeLow  = $row->cur_ce_low  !== null ? $row->cur_index_high - $row->cur_ce_low  : null;
            $idxHighMinusCeHigh = $row->cur_ce_high !== null ? $row->cur_index_high - $row->cur_ce_high : null;
            $idxHighMinusPeLow  = $row->cur_pe_low  !== null ? $row->cur_index_high - $row->cur_pe_low  : null;
            $idxHighMinusPeHigh = $row->cur_pe_high !== null ? $row->cur_index_high - $row->cur_pe_high : null;

            // Threshold: ±20 (change to 10 if you prefer)
            $isWithin = function ($level) use ($curClose) {
                return $level !== null && abs($level - $curClose) <= 10;
            };

            // Index Low ± CE Low
            if ($isWithin($cePlus))  $carry['idx_low_ce']['+']++;
            if ($isWithin($ceMinus)) $carry['idx_low_ce']['-']++;

            // Index Low ± PE Low
            if ($isWithin($pePlus))  $carry['idx_low_pe']['+']++;
            if ($isWithin($peMinus)) $carry['idx_low_pe']['-']++;

            // Index Low ± Avg Low
            if ($isWithin($avgLowPlus))  $carry['idx_low_avg_low']['+']++;
            if ($isWithin($avgLowMinus)) $carry['idx_low_avg_low']['-']++;

            // Index Low ± Avg High
            if ($isWithin($avgHighPlus))  $carry['idx_low_avg_high']['+']++;
            if ($isWithin($avgHighMinus)) $carry['idx_low_avg_high']['-']++;

            // Index High ± Avg Low/High
            if ($isWithin($idxHighPlusAvgLow))   $carry['idx_high_avg']['+']++;
            if ($isWithin($idxHighMinusAvgLow))  $carry['idx_high_avg']['-']++;
            if ($isWithin($idxHighPlusAvgHigh))  $carry['idx_high_avg']['+']++;
            if ($isWithin($idxHighMinusAvgHigh)) $carry['idx_high_avg']['-']++;

            // Mid (H+L)/2 ± Avg H/L
            if ($isWithin($midPlusAvgHigh))  $carry['mid_avg']['+']++;
            if ($isWithin($midMinusAvgHigh)) $carry['mid_avg']['-']++;
            if ($isWithin($midPlusAvgLow))   $carry['mid_avg']['+']++;
            if ($isWithin($midMinusAvgLow))  $carry['mid_avg']['-']++;

            // NEW: separate buckets for each Index High − CE/PE L/H
            if ($isWithin($idxHighMinusCeLow))   $carry['idx_high_ce_low']['-']++;
            if ($isWithin($idxHighMinusCeHigh))  $carry['idx_high_ce_high']['-']++;
            if ($isWithin($idxHighMinusPeLow))   $carry['idx_high_pe_low']['-']++;
            if ($isWithin($idxHighMinusPeHigh))  $carry['idx_high_pe_high']['-']++;

            return $carry;
        }, [
            'idx_low_ce'          => ['+' => 0, '-' => 0],
            'idx_low_pe'          => ['+' => 0, '-' => 0],
            'idx_low_avg_low'     => ['+' => 0, '-' => 0],
            'idx_low_avg_high'    => ['+' => 0, '-' => 0],
            'idx_high_avg'        => ['+' => 0, '-' => 0],
            'mid_avg'             => ['+' => 0, '-' => 0],
            'idx_high_ce_low'     => ['+' => 0, '-' => 0],
            'idx_high_ce_high'    => ['+' => 0, '-' => 0],
            'idx_high_pe_low'     => ['+' => 0, '-' => 0],
            'idx_high_pe_high'    => ['+' => 0, '-' => 0],
        ]);





        return view('analysis.index', compact('rows', 'summary','within10'));
    }
}
