<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class OptionLevelMatchController extends Controller
{
    public function index(Request $request)
    {
        $data = $this->getDashboardData($request);

        return view('options.prev-level-match', $data);
    }

    public function live(Request $request)
    {
        $data = $this->getDashboardData($request);

        return response()->json([
            'ok' => $data['ok'],
            'message' => $data['message'],
            'meta' => $data['meta'],
            'rows' => $data['rows'],
            'updated_at' => now()->format('d M Y h:i:s A'),
        ]);
    }

    private function getDashboardData(Request $request): array
    {
        try {
            $limit = max(1, min((int) $request->query('limit', 2), 30));

            $expiry = DB::table('nse_expiries')
                        ->where('is_current', 1)
                        ->where('instrument_type', 'OPT')
                        ->where('trading_symbol', 'NIFTY')
                        ->value('expiry_date');

            $currentDate = DB::table('nse_working_days')
                             ->where('current', 1)
                             ->value('working_date');

            $previousDate = DB::table('nse_working_days')
                              ->where('previous', 1)
                              ->value('working_date');

            if (! $expiry || ! $currentDate || ! $previousDate) {
                return $this->emptyResponse(
                    'Current expiry or working-day data is missing. Please verify nse_expiries and nse_working_days.',
                    $limit
                );
            }

            $atmRow = DB::table('daily_trend')
                        ->where('symbol_name', 'NIFTY')
                        ->whereDate('trading_date', $currentDate)
                        ->first();

            if (! $atmRow) {
                return $this->emptyResponse(
                    'ATM/base strike data is not available in daily_trend for the current working day.',
                    $limit
                );
            }

            $indexOpen = (float) ($atmRow->current_day_index_open ?? 0);
            $baseStrike = $atmRow->strike
                ? (int) $atmRow->strike
                : $this->roundToNearest50($indexOpen);

            $strikes = collect(range(-$limit, $limit))
                ->map(fn ($step) => $baseStrike + ($step * 50))
                ->filter(fn ($strike) => $strike > 0)
                ->values();

            $prevDaily = DB::table('daily_ohlc_quotes')
                           ->selectRaw('CAST(strike AS UNSIGNED) as strike_price, option_type, high, low, close, volume, open_interest')
                           ->where('symbol_name', 'NIFTY')
                           ->whereDate('expiry_date', $expiry)
                           ->whereDate('quote_date', $previousDate)
                           ->whereIn(DB::raw('CAST(strike AS UNSIGNED)'), $strikes->all())
                           ->whereIn('option_type', ['CE', 'PE'])
                           ->get()
                           ->keyBy(fn ($row) => $row->strike_price . '_' . strtoupper($row->option_type));

            $latestPerGroup = DB::table('ohlc_quotes')
                                ->selectRaw('trading_symbol, expiry_date, strike_price, instrument_type, MAX(ts_at) as max_ts_at')
                                ->where('trading_symbol', 'NIFTY')
                                ->whereDate('expiry_date', $expiry)
                                ->whereIn('instrument_type', ['CE', 'PE'])
                                ->whereIn('strike_price', $strikes->all())
                                ->groupBy('trading_symbol', 'expiry_date', 'strike_price', 'instrument_type');

            $latestCloseRows = DB::table('ohlc_quotes as oq')
                                 ->joinSub($latestPerGroup, 'latest', function ($join) {
                                     $join->on('oq.trading_symbol', '=', 'latest.trading_symbol')
                                          ->on('oq.expiry_date', '=', 'latest.expiry_date')
                                          ->on('oq.strike_price', '=', 'latest.strike_price')
                                          ->on('oq.instrument_type', '=', 'latest.instrument_type')
                                          ->on('oq.ts_at', '=', 'latest.max_ts_at');
                                 })
                                 ->selectRaw('CAST(oq.strike_price AS UNSIGNED) as strike_price, oq.instrument_type, oq.close, oq.volume, oq.ts_at')
                                 ->get()
                                 ->keyBy(fn ($row) => $row->strike_price . '_' . strtoupper($row->instrument_type));

            $dayRangeRows = DB::table('ohlc_quotes')
                              ->selectRaw('CAST(strike_price AS UNSIGNED) as strike_price, instrument_type, MAX(high) as curr_high, MIN(low) as curr_low')
                              ->where('trading_symbol', 'NIFTY')
                              ->whereDate('expiry_date', $expiry)
                              ->whereIn('instrument_type', ['CE', 'PE'])
                              ->whereIn('strike_price', $strikes->all())
                              ->groupBy('strike_price', 'instrument_type')
                              ->get()
                              ->keyBy(fn ($row) => $row->strike_price . '_' . strtoupper($row->instrument_type));

            $latestChainTs = DB::table('option_chains')
                               ->where('trading_symbol', 'NIFTY')
                               ->whereDate('expiry', $expiry)
                               ->whereIn('option_type', ['CE', 'PE'])
                               ->whereIn('strike_price', $strikes->all())
                               ->max('captured_at');

            $buildUps = collect();

            if ($latestChainTs) {
                $buildUps = DB::table('option_chains')
                              ->selectRaw('CAST(strike_price AS UNSIGNED) as strike_price, option_type, build_up')
                              ->where('trading_symbol', 'NIFTY')
                              ->whereDate('expiry', $expiry)
                              ->whereIn('option_type', ['CE', 'PE'])
                              ->whereIn('strike_price', $strikes->all())
                              ->where('captured_at', $latestChainTs)
                              ->get()
                              ->keyBy(fn ($row) => $row->strike_price . '_' . strtoupper($row->option_type));
            }

            $rows = $strikes->values()->map(function ($strike, $index) use ($prevDaily, $latestCloseRows, $dayRangeRows, $buildUps) {
                $cePrev = $prevDaily->get($strike . '_CE');
                $pePrev = $prevDaily->get($strike . '_PE');

                $ceLive = $latestCloseRows->get($strike . '_CE');
                $peLive = $latestCloseRows->get($strike . '_PE');

                $ceDay = $dayRangeRows->get($strike . '_CE');
                $peDay = $dayRangeRows->get($strike . '_PE');

                $ceBuild = $buildUps->get($strike . '_CE');
                $peBuild = $buildUps->get($strike . '_PE');

                return [
                    'strike' => $strike,
                    'stripe' => $index % 2 === 0 ? 'odd' : 'even',
                    'CE' => $this->formatSideRow('CE', $cePrev, $pePrev, $ceLive, $peLive, $ceDay, $peDay, $ceBuild),
                    'PE' => $this->formatSideRow('PE', $pePrev, $cePrev, $peLive, $ceLive, $peDay, $ceDay, $peBuild),
                ];
            });

            $hasLiveData = $latestCloseRows->isNotEmpty() || $dayRangeRows->isNotEmpty();

            return [
                'ok' => true,
                'message' => $hasLiveData
                    ? null
                    : 'Live OHLC data is not available yet for the selected expiry. Previous-day levels are ready.',
                'meta' => [
                    'expiry_date' => $expiry,
                    'current_date' => $currentDate,
                    'previous_date' => $previousDate,
                    'index_open' => $indexOpen,
                    'atm_strike' => $baseStrike,
                    'limit' => $limit,
                    'updated_at' => now()->format('d M Y h:i:s A'),
                ],
                'rows' => $rows,
            ];
        } catch (Throwable $e) {
            return $this->emptyResponse(
                'Unable to load options level match data. ' . $e->getMessage(),
                (int) $request->query('limit', 2)
            );
        }
    }

    private function formatSideRow(string $side, $ownPrev, $oppPrev, $ownLive, $oppLive, $ownDay, $oppDay, $buildRow): array
    {
        $price = $ownLive?->close !== null ? (float) $ownLive->close : null;

        $prevHighMatch = $this->findMatch($price, [
            ['label' => $side . ' PH', 'value' => $ownPrev?->high],
            ['label' => ($side === 'CE' ? 'PE' : 'CE') . ' PH', 'value' => $oppPrev?->high],
        ]);

        $prevLowMatch = $this->findMatch($price, [
            ['label' => $side . ' PL', 'value' => $ownPrev?->low],
            ['label' => ($side === 'CE' ? 'PE' : 'CE') . ' PL', 'value' => $oppPrev?->low],
        ]);

        $currHighMatch = $this->findMatch($price, [
            ['label' => $side . ' CH', 'value' => $ownDay?->curr_high],
            ['label' => ($side === 'CE' ? 'PE' : 'CE') . ' CH', 'value' => $oppDay?->curr_high],
        ]);

        $currLowMatch = $this->findMatch($price, [
            ['label' => $side . ' CL', 'value' => $ownDay?->curr_low],
            ['label' => ($side === 'CE' ? 'PE' : 'CE') . ' CL', 'value' => $oppDay?->curr_low],
        ]);

        $firstMatch = collect([$prevHighMatch, $prevLowMatch, $currHighMatch, $currLowMatch])
            ->first(fn ($item) => ! is_null($item));

        return [
            'side' => $side,
            'prev_high' => $ownPrev?->high,
            'prev_low' => $ownPrev?->low,
            'build_up' => $this->shortBuildUp($buildRow?->build_up),
            'curr_high' => $ownDay?->curr_high,
            'curr_low' => $ownDay?->curr_low,
            'price' => $price,
            'ts_at' => $ownLive?->ts_at,
            'matches' => [
                'prev_high' => $prevHighMatch,
                'prev_low' => $prevLowMatch,
                'curr_high' => $currHighMatch,
                'curr_low' => $currLowMatch,
            ],
            'notification' => $firstMatch
                ? "{$side} price matched {$firstMatch['label']} @ " . number_format($firstMatch['value'], 2)
                : null,
            'has_notification' => ! is_null($firstMatch),
        ];
    }

    private function findMatch(?float $price, array $levels, float $tolerance = 0.20): ?array
    {
        if ($price === null) {
            return null;
        }

        foreach ($levels as $level) {
            if (! isset($level['value']) || $level['value'] === null) {
                continue;
            }

            if (abs($price - (float) $level['value']) <= $tolerance) {
                return [
                    'label' => $level['label'],
                    'value' => (float) $level['value'],
                ];
            }
        }

        return null;
    }

    private function shortBuildUp(?string $buildUp): string
    {
        return match ($buildUp) {
            'Short Build' => 'SB',
            'Long Build' => 'LB',
            'Short Cover' => 'SC',
            'Long Unwind' => 'LU',
            default => '--',
        };
    }

    private function emptyResponse(string $message, int $limit = 10): array
    {
        return [
            'ok' => false,
            'message' => $message,
            'meta' => [
                'expiry_date' => null,
                'current_date' => null,
                'previous_date' => null,
                'index_open' => null,
                'atm_strike' => null,
                'limit' => $limit,
                'updated_at' => now()->format('d M Y h:i:s A'),
            ],
            'rows' => collect(),
        ];
    }

    private function roundToNearest50(float $value): int
    {
        return (int) (round($value / 50) * 50);
    }
}
