<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use App\Models\DailyTrend;
use App\Models\DailyTrendMeta;

class TrendMetaController extends Controller
{
    public function index()
    {
        // 1. Working days
        $days = DB::table('nse_working_days')
                  ->where(function ($q) {
                      $q->where('previous', 1)
                        ->orWhere('current', 1);
                  })
                  ->orderByDesc('id')
                  ->get();

        $previousDay = optional($days->firstWhere('previous', 1))->working_date;
        $currentDay  = optional($days->firstWhere('current', 1))->working_date;

        if (! $previousDay || ! $currentDay) {
            abort(404, 'Working days not configured');
        }

        // 2. Static trends
        $dailyTrends = DailyTrend::whereDate('quote_date', $previousDay)
                                 ->whereIn('symbol_name', ['NIFTY', 'BANKNIFTY', 'SENSEX'])
                                 ->get()
                                 ->keyBy('id');

        if ($dailyTrends->isEmpty()) {
            abort(404, 'Daily trends not populated for previous day');
        }

        $trendIds = $dailyTrends->keys()->all();

        // 3. All meta rows for today, latest first
        $metaRows = DailyTrendMeta::whereIn('daily_trend_id', $trendIds)
                                  ->whereDate('tracked_date', $currentDay)
                                  ->orderBy('recorded_at', 'desc')
                                  ->orderBy('sequence_id', 'desc')
                                  ->get();

        $rows = [];

        foreach ($metaRows as $meta) {
            $trend = $dailyTrends[$meta->daily_trend_id] ?? null;
            if (! $trend) {
                continue;
            }

            $rows[] = [
                'symbol'          => $trend->symbol_name,
                'quote_date'      => $trend->quote_date?->toDateString(),
                'tracked_date'    => $currentDay,
                'market_type'     => $trend->market_type ?? null,
                'ce_type'         => $trend->ce_type ?? null,
                'pe_type'         => $trend->pe_type ?? null,

                'index_high'      => $trend->index_high,
                'index_low'       => $trend->index_low,
                'index_close'     => $trend->index_close,
                'strike'          => $trend->strike,
                'min_r'           => $trend->min_r,
                'max_r'           => $trend->max_r,
                'min_s'           => $trend->min_s,
                'max_s'           => $trend->max_s,
                'earth_high'      => $trend->earth_high,
                'earth_low'       => $trend->earth_low,

                'index_ltp'       => $meta->index_ltp,
                'ce_ltp'          => $meta->ce_ltp,
                'pe_ltp'          => $meta->pe_ltp,
                'market_scenario' => $meta->market_scenario,
                'trade_signal'    => $meta->trade_signal,
                'recorded_at'     => $meta->recorded_at?->format('H:i'),
                'dominant_side'   => $meta->dominant_side,
                'triggers'        => $meta->triggers ?? [],
            ];
        }

        return view('trend.meta', [
            'previousDay' => $previousDay,
            'currentDay'  => $currentDay,
            'rows'        => $rows,
        ]);
    }
}
