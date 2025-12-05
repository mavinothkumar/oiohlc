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

        // 2. Static trends (yesterday)
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

            $levelsCrossed = $meta->levels_crossed ?? [];
            $triggers      = $meta->triggers ?? [];

            $rows[] = [
                'symbol'          => $trend->symbol_name,
                'tracked_date'    => $currentDay,

                'index_ltp'       => $meta->index_ltp,
                'ce_ltp'          => $meta->ce_ltp,
                'pe_ltp'          => $meta->pe_ltp,
                'recorded_at'     => optional($meta->recorded_at)->format('H:i'),

                'market_scenario' => $meta->market_scenario,
                'trade_signal'    => $meta->trade_signal,

                'ce_type'         => $meta->ce_type ?? $trend->ce_type,
                'pe_type'         => $meta->pe_type ?? $trend->pe_type,
                'dominant_side'   => $meta->dominant_side,

                'broken_status'   => $meta->broken_status,
                'first_broken_at' => $meta->first_broken_at,
                'good_zone'       => $meta->good_zone,

                'triggers'        => $triggers,
                'levels_crossed'  => $levelsCrossed,
                'index_crossed'   => $this->summarizeIndexCrossed($levelsCrossed),
                'reason'          => $this->buildReason($meta->market_scenario, $meta->trade_signal, $triggers),
            ];
        }
        return view('trend.meta', [
            'previousDay' => $previousDay,
            'currentDay'  => $currentDay,
            'rows'        => $rows,
        ]);
    }

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
            if (! str_starts_with($item['level'], 'INDEX_')) {
                continue;
            }

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
        if ($up) {
            $parts[] = 'Index Up: ' . implode(', ', array_unique($up));
        }
        if ($down) {
            $parts[] = 'Index Down: ' . implode(', ', array_unique($down));
        }

        return $parts ? implode(' • ', $parts) : null;
    }

    protected function buildReason(?string $scenario, ?string $signal, array $t): string
    {
        $parts = [];

        if (!empty($t['cs_panic']) && !empty($t['ps_pb'])) {
            $parts[] = 'Call Panic + Put Profit Booking (CSP-PSPB)';
        }
        if (!empty($t['cs_pb']) && !empty($t['ps_panic'])) {
            $parts[] = 'Call Profit Booking + Put Panic (CSPB-PSP)';
        }
        if (!empty($t['cs_pb']) && !empty($t['ps_pb'])) {
            $parts[] = 'Both Profit Booking (BOTHPB)';
        }

        if (!empty($t['spot_break_min_res']) && empty($t['spot_break_max_res'])) {
            $parts[] = 'Spot broke MinRes band';
        }
        if (!empty($t['spot_break_min_sup']) && empty($t['spot_break_max_sup'])) {
            $parts[] = 'Spot broke MinSup band';
        }
        if (!empty($t['spot_near_pdc'])) {
            $parts[] = 'Spot near PDC';
        }
        if (!empty($t['ce_above_pdh'])) {
            $parts[] = 'CE above PDH';
        }
        if (!empty($t['pe_above_pdh'])) {
            $parts[] = 'PE above PDH';
        }

        if ($signal === 'SIDEWAYS_NO_TRADE' && empty($parts)) {
            $parts[] = 'No clear side; market indecision';
        }

        if ($scenario && empty($parts)) {
            $parts[] = $scenario;
        }

        return implode(' • ', $parts);
    }
}
