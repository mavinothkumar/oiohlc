<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DailyTrendController extends Controller
{
    public function index(Request $request)
    {
        $symbol = $request->input('symbol_name', 'NIFTY');

        // Tab 1: Daily trend data - get in ASC order for calculation
        $rawRows = DB::table('daily_trend')
                     ->select(
                         'id',
                         'quote_date',
                         'expiry_date',
                         'strike',
                         'ce_close',
                         'pe_close',
                         DB::raw('(ce_close + pe_close) / 2 as combined_close')
                     )
                     ->where('symbol_name', $symbol)
                     ->whereNotNull('ce_close')
                     ->whereNotNull('pe_close')
                     ->orderBy('quote_date', 'asc')  // ASC for correct calculation
                     ->orderBy('expiry_date')
                     ->orderBy('strike')
                     ->get();

        // Group by expiry and calculate differences within each expiry
        $groupedByExpiry = $rawRows->groupBy('expiry_date');
        $rows = [];

        foreach ($groupedByExpiry as $expiry => $expiryRows) {
            $prevCombinedClose = null;

            foreach ($expiryRows as $row) {
                $diff = null;
                $diffPct = null;

                // Calculate difference only if there's a previous day within this expiry
                if (!is_null($prevCombinedClose)) {
                    $diff = $row->combined_close - $prevCombinedClose;
                    $diffPct = ($diff / $prevCombinedClose) * 100;
                }

                $rows[] = (object) array_merge((array) $row, [
                    'diff' => $diff,
                    'diff_pct' => $diffPct,
                ]);

                $prevCombinedClose = $row->combined_close;
            }
        }

        // Reverse to show newest first in the view
        $rows = array_reverse($rows);

        // Tab 2: Expiry day analysis
        $expiryAnalysis = $this->getExpiryDayAnalysis($symbol);

        return view('daily-trend-view', [
            'rows'            => $rows,
            'symbol'          => $symbol,
            'expiryAnalysis'  => $expiryAnalysis,
        ]);
    }

    private function getExpiryDayAnalysis($symbol)
    {
        // Get all data grouped by expiry
        $data = DB::table('daily_trend')
                  ->select(
                      'expiry_date',
                      'quote_date',
                      DB::raw('(ce_close + pe_close) / 2 as combined_close')
                  )
                  ->where('symbol_name', $symbol)
                  ->whereNotNull('ce_close')
                  ->whereNotNull('pe_close')
                  ->orderBy('expiry_date')
                  ->orderBy('quote_date')
                  ->get()
                  ->groupBy('expiry_date');

        $analysis = [];
        $overallDays = [1 => [], 2 => [], 3 => [], 4 => [], 5 => []];

        foreach ($data as $expiry => $records) {
            $expiryDate = \Carbon\Carbon::parse($expiry);
            $month = $expiryDate->format('M Y');

            if (!isset($analysis[$month])) {
                $analysis[$month] = [
                    'day1' => [],
                    'day2' => [],
                    'day3' => [],
                    'day4' => [],
                    'day5' => [],
                ];
            }

            // Get first 5 days of this expiry
            $dayCounter = 1;
            foreach ($records as $record) {
                if ($dayCounter <= 5) {
                    $key = 'day' . $dayCounter;
                    $analysis[$month][$key][] = $record->combined_close;
                    $overallDays[$dayCounter][] = $record->combined_close;
                    $dayCounter++;
                }
            }
        }

        // Calculate averages
        $result = [];
        foreach ($analysis as $month => $days) {
            $result[$month] = [
                'day1_avg' => !empty($days['day1']) ? array_sum($days['day1']) / count($days['day1']) : null,
                'day2_avg' => !empty($days['day2']) ? array_sum($days['day2']) / count($days['day2']) : null,
                'day3_avg' => !empty($days['day3']) ? array_sum($days['day3']) / count($days['day3']) : null,
                'day4_avg' => !empty($days['day4']) ? array_sum($days['day4']) / count($days['day4']) : null,
                'day5_avg' => !empty($days['day5']) ? array_sum($days['day5']) / count($days['day5']) : null,
            ];
        }

        // Overall averages across all months
        $result['Overall'] = [
            'day1_avg' => !empty($overallDays[1]) ? array_sum($overallDays[1]) / count($overallDays[1]) : null,
            'day2_avg' => !empty($overallDays[2]) ? array_sum($overallDays[2]) / count($overallDays[2]) : null,
            'day3_avg' => !empty($overallDays[3]) ? array_sum($overallDays[3]) / count($overallDays[3]) : null,
            'day4_avg' => !empty($overallDays[4]) ? array_sum($overallDays[4]) / count($overallDays[4]) : null,
            'day5_avg' => !empty($overallDays[5]) ? array_sum($overallDays[5]) / count($overallDays[5]) : null,
        ];

        return $result;
    }
}
