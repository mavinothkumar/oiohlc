<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use App\Models\DailyTrend;
use App\Models\DailyTrendMeta;

class TrendMetaController extends Controller
{
    public function index()
    {
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

        $dailyTrends = DailyTrend::whereDate('quote_date', $previousDay)
                                 ->whereIn('symbol_name', ['NIFTY', 'BANKNIFTY', 'SENSEX'])
                                 ->get()
                                 ->keyBy('id');

        if ($dailyTrends->isEmpty()) {
            abort(404, 'Daily trends not populated for previous day');
        }

        $trendIds = $dailyTrends->keys()->all();

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

            $levelsCrossed = $meta->levels_crossed ?? [];

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

                'broken_status'   => $meta->broken_status,
                'first_broken_at' => $meta->first_broken_at,
                'good_zone'       => $meta->good_zone,

                'triggers'        => $meta->triggers ?? [],
                'levels_crossed'  => $levelsCrossed,
                // NEW: summarized index crossings text
                'index_crossed'   => $this->summarizeIndexCrossed($levelsCrossed),
            ];
        }

        return view('trend.meta', [
            'previousDay' => $previousDay,
            'currentDay'  => $currentDay,
            'rows'        => $rows,
        ]);
    }

    /**
     * Produce a short text summary like:
     *  "Index Up: PDH, MinR • Down: PDL"
     */
    protected function summarizeIndexCrossed(array $levelsCrossed): ?string
    {
        if (empty($levelsCrossed)) {
            return null;
        }

        $up = [];
        $down = [];

        foreach ($levelsCrossed as $item) {
            if (empty($item['level']) || empty($item['direction'])) {
                continue;
            }

            // Only index-related keys
            if (! str_starts_with($item['level'], 'INDEX_')) {
                continue;
            }

            // Map machine keys to short label
            $label = match ($item['level']) {
                'INDEX_PDH'        => 'PDH',
                'INDEX_PDL'        => 'PDL',
                'INDEX_PDC'        => 'PDC',
                'INDEX_MinR'       => 'MinR',
                'INDEX_MaxR'       => 'MaxR',
                'INDEX_MinS'       => 'MinS',
                'INDEX_MaxS'       => 'MaxS',
                'INDEX_EarthHigh'  => 'EarthH',
                'INDEX_EarthLow'   => 'EarthL',
                default            => $item['level'],
            };

            if ($item['direction'] === 'Up') {
                $up[] = $label;
            } elseif ($item['direction'] === 'Down') {
                $down[] = $label;
            }
        }

        $parts = [];
        if (! empty($up)) {
            $parts[] = 'Index Up: ' . implode(', ', array_unique($up));
        }
        if (! empty($down)) {
            $parts[] = 'Index Down: ' . implode(', ', array_unique($down));
        }

        return $parts ? implode(' • ', $parts) : null;
    }
}
