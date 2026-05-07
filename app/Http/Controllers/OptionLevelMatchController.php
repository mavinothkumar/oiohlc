<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OptionLevelMatchController extends Controller
{
    public function index(Request $request)
    {
        $result = $this->buildDataset($request);

        if (! $result['ok'] && ! empty($result['message'])) {
            $request->session()->now('error', $result['message']);
        }

        return view('options.prev-level-match', [
            'meta' => $result['meta'],
            'rows' => $result['rows'],
        ]);
    }

    public function live(Request $request): JsonResponse
    {
        $result = $this->buildDataset($request);

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'meta' => $result['meta'],
            'rows' => $result['rows'],
            'updated_at' => now()->format('d M Y h:i:s A'),
        ], $result['ok'] ? 200 : 422);
    }

    private function buildDataset(Request $request): array
    {
        $expiry = DB::table('nse_expiries')
                    ->where('is_current', 1)
                    ->where('instrument_type', 'OPT')
                    ->where('trading_symbol', 'NIFTY')
                    ->select('expiry_date')
                    ->first();

        $currentWorkingDay = DB::table('nse_working_days')
                               ->where('current', 1)
                               ->select('working_date')
                               ->first();

        $previousWorkingDay = DB::table('nse_working_days')
                                ->where('previous', 1)
                                ->select('working_date')
                                ->first();

        if (! $expiry || ! $currentWorkingDay || ! $previousWorkingDay) {
            return $this->emptyDataset(
                $request,
                'Current expiry or working day data is not available right now.'
            );
        }

        $expiryDate = $expiry->expiry_date;
        $currentDate = $currentWorkingDay->working_date;
        $previousDate = $previousWorkingDay->working_date;

        $requestedTime = $request->query('time');
        $timeCutoff = $this->resolveTimeCutoff($currentDate, $requestedTime);

        $atmRow = DB::table('daily_trend')
                    ->where('symbol_name', 'NIFTY')
                    ->whereDate('trading_date', $currentDate)
                    ->whereDate('expiry_date', $expiryDate)
                    ->select('strike', 'current_day_index_open', 'expiry_date')
                    ->first();

        if (! $atmRow) {
            $atmRow = DB::table('nse_atm_day_data')
                        ->where('underlying_symbol', 'NIFTY')
                        ->whereDate('current_date', $currentDate)
                        ->select('atm_strike', 'current_day_index_open', 'current_expiry_date')
                        ->orderByDesc('id')
                        ->first();
        }

        $centerStrike = $atmRow?->strike
            ? (int) $atmRow->strike
            : null;

        $indexOpen = $atmRow?->current_day_index_open !== null
            ? (float) $atmRow->current_day_index_open
            : null;

        if (! $centerStrike && $indexOpen) {
            $centerStrike = $this->roundToNearest50($indexOpen);
        }

        if (! $centerStrike) {
            $fallbackPrevStrike = DB::table('daily_ohlc_quotes')
                                    ->whereDate('quote_date', $previousDate)
                                    ->whereDate('expiry_date', $expiryDate)
                                    ->where('symbol_name', 'NIFTY')
                                    ->whereIn('option_type', ['CE', 'PE'])
                                    ->selectRaw('CAST(strike AS UNSIGNED) as strike_int')
                                    ->orderByRaw('CAST(strike AS UNSIGNED)')
                                    ->first();

            if ($fallbackPrevStrike) {
                $centerStrike = (int) $fallbackPrevStrike->strike_int;
            }
        }

        if (! $centerStrike) {
            $fallbackLiveStrike = DB::table('ohlc_quotes')
                                    ->whereDate('expiry_date', $expiryDate)
                                    ->whereDate('ts_at', $currentDate)
                                    ->where('ts_at', '<=', $timeCutoff)
                                    ->where('trading_symbol', 'NIFTY')
                                    ->whereIn('instrument_type', ['CE', 'PE'])
                                    ->selectRaw('CAST(strike_price AS UNSIGNED) as strike_int')
                                    ->orderByRaw('CAST(strike_price AS UNSIGNED)')
                                    ->first();

            if ($fallbackLiveStrike) {
                $centerStrike = (int) $fallbackLiveStrike->strike_int;
            }
        }

        if (! $centerStrike) {
            return $this->emptyDataset(
                $request,
                'No strike context could be derived from nse_atm_day_data, daily_ohlc_quotes, or ohlc_quotes.',
                [
                    'expiry_date' => $expiryDate,
                    'current_date' => $currentDate,
                    'previous_date' => $previousDate,
                    'current_day_index_open' => $indexOpen,
                    'requested_time' => $requestedTime,
                    'time_cutoff' => $timeCutoff->format('Y-m-d H:i:s'),
                ]
            );
        }

        $limit = (int) $request->query('limit', 2);
        $limit = $limit > 0 ? min($limit, 100) : 10;

        $strikePrices = collect(range(-$limit, $limit))
            ->map(fn ($step) => $centerStrike + ($step * 50))
            ->filter(fn ($strike) => $strike > 0)
            ->values();

        $previousDayQuotes = DB::table('daily_ohlc_quotes')
                               ->whereDate('quote_date', $previousDate)
                               ->whereDate('expiry_date', $expiryDate)
                               ->where('symbol_name', 'NIFTY')
                               ->whereIn(DB::raw('CAST(strike AS UNSIGNED)'), $strikePrices->all())
                               ->whereIn('option_type', ['CE', 'PE'])
                               ->selectRaw('
                CAST(strike AS UNSIGNED) as strike_int,
                option_type,
                high,
                low,
                close,
                volume,
                open_interest
            ')
                               ->get()
                               ->keyBy(fn ($row) => $row->strike_int . '_' . strtoupper($row->option_type));

        if ($previousDayQuotes->isEmpty()) {
            $allPrev = DB::table('daily_ohlc_quotes')
                         ->whereDate('quote_date', $previousDate)
                         ->whereDate('expiry_date', $expiryDate)
                         ->where('symbol_name', 'NIFTY')
                         ->whereIn('option_type', ['CE', 'PE'])
                         ->selectRaw('
                    CAST(strike AS UNSIGNED) as strike_int,
                    option_type,
                    high,
                    low,
                    close,
                    volume,
                    open_interest
                ')
                         ->get();

            if ($allPrev->isNotEmpty()) {
                $derivedCenter = $this->nearestStrikeFromSet($allPrev->pluck('strike_int')->unique()->values(), $centerStrike);

                $strikePrices = collect(range(-$limit, $limit))
                    ->map(fn ($step) => $derivedCenter + ($step * 50))
                    ->filter(fn ($strike) => $strike > 0)
                    ->values();

                $previousDayQuotes = $allPrev
                    ->whereIn('strike_int', $strikePrices->all())
                    ->keyBy(fn ($row) => $row->strike_int . '_' . strtoupper($row->option_type));

                $centerStrike = $derivedCenter;
            }
        }

        $olderWorkingDay = DB::table('nse_working_days')
                             ->where('working_date', '<', $previousDate)
                             ->orderByDesc('working_date')
                             ->select('working_date')
                             ->first();

        $olderPreviousQuotes = collect();

        if ($olderWorkingDay) {
            $olderPreviousQuotes = DB::table('daily_ohlc_quotes')
                                     ->whereDate('quote_date', $olderWorkingDay->working_date)
                                     ->whereDate('expiry_date', $expiryDate)
                                     ->where('symbol_name', 'NIFTY')
                                     ->whereIn(DB::raw('CAST(strike AS UNSIGNED)'), $strikePrices->all())
                                     ->whereIn('option_type', ['CE', 'PE'])
                                     ->selectRaw('
                    CAST(strike AS UNSIGNED) as strike_int,
                    option_type,
                    close,
                    open_interest
                ')
                                     ->get()
                                     ->keyBy(fn ($row) => $row->strike_int . '_' . strtoupper($row->option_type));
        }

        $liveBaseQuery = DB::table('ohlc_quotes')
                           ->whereDate('expiry_date', $expiryDate)
                           ->whereDate('ts_at', $currentDate)
                           ->where('ts_at', '<=', $timeCutoff)
                           ->whereIn('instrument_type', ['CE', 'PE'])
                           ->whereIn(DB::raw('CAST(strike_price AS UNSIGNED)'), $strikePrices->all())
                           ->where('trading_symbol', 'NIFTY');

        $dayExtremes = (clone $liveBaseQuery)
            ->selectRaw('
        CAST(strike_price AS UNSIGNED) as strike_int,
        instrument_type,
        MAX(high) as day_high,
        MIN(low) as day_low
    ')
            ->groupBy(
                DB::raw('CAST(strike_price AS UNSIGNED)'),
                'instrument_type'
            )
            ->get()
            ->keyBy(fn ($row) => $row->strike_int . '_' . strtoupper($row->instrument_type));

        $latestSubQuery = (clone $liveBaseQuery)
            ->selectRaw('
        CAST(strike_price AS UNSIGNED) as strike_int,
        instrument_type,
        expiry_date,
        MAX(ts_at) as max_ts_at
    ')
            ->groupBy(
                DB::raw('CAST(strike_price AS UNSIGNED)'),
                'instrument_type',
                'expiry_date'
            );

        $latestQuotes = DB::table('ohlc_quotes as oq')
                          ->joinSub($latestSubQuery, 'latest', function ($join) {
                              $join->on(DB::raw('CAST(oq.strike_price AS UNSIGNED)'), '=', 'latest.strike_int')
                                   ->on('oq.instrument_type', '=', 'latest.instrument_type')
                                   ->on('oq.expiry_date', '=', 'latest.expiry_date')
                                   ->on('oq.ts_at', '=', 'latest.max_ts_at');
                          })
                          ->selectRaw('
        CAST(oq.strike_price AS UNSIGNED) as strike_int,
        oq.instrument_type,
        oq.close,
        oq.volume,
        oq.ts_at
    ')
                          ->get()
                          ->keyBy(fn ($row) => $row->strike_int . '_' . strtoupper($row->instrument_type));

        $liveQuotes = collect($strikePrices)->flatMap(function ($strike) use ($dayExtremes, $latestQuotes) {
            return collect(['CE', 'PE'])->map(function ($side) use ($strike, $dayExtremes, $latestQuotes) {
                $key = $strike . '_' . $side;
                $extreme = $dayExtremes->get($key);
                $latest = $latestQuotes->get($key);

                if (! $extreme && ! $latest) {
                    return null;
                }

                return (object) [
                    'strike_int' => $strike,
                    'instrument_type' => $side,
                    'high' => $extreme?->day_high,
                    'low' => $extreme?->day_low,
                    'close' => $latest?->close,
                    'volume' => $latest?->volume,
                    'ts_at' => $latest?->ts_at,
                ];
            });
        })
                                            ->filter()
                                            ->keyBy(fn ($row) => $row->strike_int . '_' . strtoupper($row->instrument_type));

        $rows = collect($strikePrices)->map(function ($strike) use ($previousDayQuotes, $olderPreviousQuotes, $liveQuotes) {
            $cePrev = $previousDayQuotes->get($strike . '_CE');
            $pePrev = $previousDayQuotes->get($strike . '_PE');

            $ceOlder = $olderPreviousQuotes->get($strike . '_CE');
            $peOlder = $olderPreviousQuotes->get($strike . '_PE');

            $ceLive = $liveQuotes->get($strike . '_CE');
            $peLive = $liveQuotes->get($strike . '_PE');

            return [
                'strike' => $strike,
                'CE' => $this->makeSideData('CE', $cePrev, $ceOlder, $ceLive, $pePrev, $peLive),
                'PE' => $this->makeSideData('PE', $pePrev, $peOlder, $peLive, $cePrev, $ceLive),
            ];
        })
                                      ->filter(function ($row) {
                                          return collect([$row['CE'], $row['PE']])->contains(function ($side) {
                                              return $side['prev_high'] !== null
                                                     || $side['prev_low'] !== null
                                                     || $side['curr_high'] !== null
                                                     || $side['curr_low'] !== null
                                                     || $side['price'] !== null;
                                          });
                                      })
                                      ->values()
                                      ->all();

        return [
            'ok' => true,
            'message' => $atmRow ? null : 'ATM row not found. Strike range was derived from fallback market data.',
            'meta' => [
                'expiry_date' => $expiryDate,
                'current_date' => $currentDate,
                'previous_date' => $previousDate,
                'base_strike' => $centerStrike,
                'current_day_index_open' => $indexOpen,
                'requested_time' => $requestedTime ?: null,
                'limit' => $limit,
                'time_cutoff' => $timeCutoff->format('Y-m-d H:i:s'),
                'generated_at' => now()->format('d M Y h:i:s A'),
                'prev_count' => $previousDayQuotes->count(),
                'live_count' => $liveQuotes->count(),
            ],
            'rows' => $rows,
        ];
    }

    private function emptyDataset(Request $request, string $message, array $meta = []): array
    {
        return [
            'ok' => false,
            'message' => $message,
            'meta' => array_merge([
                'expiry_date' => null,
                'current_date' => null,
                'previous_date' => null,
                'base_strike' => null,
                'current_day_index_open' => null,
                'requested_time' => $request->query('time'),
                'limit' => (int) $request->query('limit', 2),
                'time_cutoff' => null,
                'generated_at' => now()->format('d M Y h:i:s A'),
                'prev_count' => 0,
                'live_count' => 0,
            ], $meta),
            'rows' => [],
        ];
    }

    private function resolveTimeCutoff(string $currentDate, ?string $requestedTime): Carbon
    {
        if ($requestedTime && preg_match('/^\d{2}:\d{2}$/', $requestedTime)) {
            return Carbon::parse($currentDate . ' ' . $requestedTime . ':59');
        }

        return now()->seconds(59);
    }

    private function makeSideData(
        string $side,
        $prevQuote,
        $olderQuote,
        $liveQuote,
        $oppositePrevQuote,
        $oppositeLiveQuote
    ): array {
        $currentClose = $liveQuote?->close !== null ? (float) $liveQuote->close : null;

        return [
            'side' => $side,
            'prev_high' => $prevQuote?->high,
            'prev_low' => $prevQuote?->low,
            'build_up' => $this->resolveBuildUp($prevQuote, $olderQuote),
            'curr_high' => $liveQuote?->high,
            'curr_low' => $liveQuote?->low,
            'price' => $liveQuote?->close,
            'volume' => $prevQuote?->volume,
            'open_interest' => $prevQuote?->open_interest,
            'ts_at' => $liveQuote?->ts_at,
            'matches' => [
                'prev_high' => $this->findMatch($currentClose, [
                    ['label' => 'Own PH', 'value' => $prevQuote?->high],
                    ['label' => 'Opp PH', 'value' => $oppositePrevQuote?->high],
                ]),
                'prev_low' => $this->findMatch($currentClose, [
                    ['label' => 'Own PL', 'value' => $prevQuote?->low],
                    ['label' => 'Opp PL', 'value' => $oppositePrevQuote?->low],
                ]),
                'curr_high' => $this->findMatch($currentClose, [
                    ['label' => 'Own CH', 'value' => $liveQuote?->high],
                    ['label' => 'Opp CH', 'value' => $oppositeLiveQuote?->high],
                ]),
                'curr_low' => $this->findMatch($currentClose, [
                    ['label' => 'Own CL', 'value' => $liveQuote?->low],
                    ['label' => 'Opp CL', 'value' => $oppositeLiveQuote?->low],
                ]),
            ],
        ];
    }

    private function resolveBuildUp($prevQuote, $olderQuote): string
    {
        if (! $prevQuote || ! $olderQuote) {
            return '--';
        }

        $priceDiff = (float) $prevQuote->close - (float) $olderQuote->close;
        $oiDiff = (float) $prevQuote->open_interest - (float) $olderQuote->open_interest;

        if ($priceDiff > 0 && $oiDiff > 0) {
            return 'LB';
        }

        if ($priceDiff < 0 && $oiDiff > 0) {
            return 'SB';
        }

        if ($priceDiff > 0 && $oiDiff < 0) {
            return 'SC';
        }

        if ($priceDiff < 0 && $oiDiff < 0) {
            return 'LU';
        }

        return '--';
    }

    private function findMatch(?float $price, array $levels, float $tolerance = 0.15): ?array
    {
        if ($price === null) {
            return null;
        }

        foreach ($levels as $level) {
            if ($level['value'] === null) {
                continue;
            }

            $levelValue = (float) $level['value'];

            if (abs($price - $levelValue) <= $tolerance) {
                return [
                    'label' => $level['label'],
                    'value' => $levelValue,
                ];
            }
        }

        return null;
    }

    private function roundToNearest50(float $value): int
    {
        return (int) (round($value / 50) * 50);
    }

    private function nearestStrikeFromSet(Collection $strikes, int $target): int
    {
        return (int) $strikes
            ->sortBy(fn ($strike) => abs((int) $strike - $target))
            ->first();
    }
}
