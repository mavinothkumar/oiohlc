<?php
// app/Http/Controllers/FutureOhlcController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExpiredOhlc;
use App\Models\ExpiredExpiry;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class FutureOhlcController extends Controller
{

    public function index(Request $request)
    {
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');
        $interval = $request->input('interval');
        $expiryId = $request->input('expiry_id');

        $base = ExpiredOhlc::query()
                           ->where('instrument_type', 'FUT');

        // For intraday index data, use timestamp date, not expiry
        if ($dateFrom) {
            $base->whereDate('timestamp', '>=', $dateFrom);
        }
        if ($dateTo) {
            $base->whereDate('timestamp', '<=', $dateTo);
        }

        // Only apply expiry filter if your FUT intraday rows actually have expiry set
        if ($expiryId) {
            $expiry = ExpiredExpiry::find($expiryId);
            if ($expiry) {
                $base->whereDate('expiry', $expiry->expiry_date);
            }
        }

        if ($interval === '1hour') {
            // Build from 5‑minute data
            $rows = (clone $base)
                ->where('interval', '5minute')
                ->whereNotNull('timestamp')
                ->orderBy('instrument_key')
                ->orderBy('timestamp')
                ->get();

            if ($rows->isEmpty()) {
                $ohlc = new LengthAwarePaginator([], 0, 50);
            } else {
                // Group by instrument + date(timestamp)
                $grouped = $rows->groupBy(function ($row) {
                    return $row->instrument_key . '|' . \Carbon\Carbon::parse($row->timestamp)->format('Y-m-d');
                });

                $hourly = collect();

                foreach ($grouped as $groupRows) {
                    $groupRows = $groupRows->values(); // 0..n-1

                    // chunk every 12 candles => 1 hour block
                    $chunks = $groupRows->chunk(12);

                    foreach ($chunks as $idx => $chunk) {
                        if ($chunk->isEmpty()) {
                            continue;
                        }

                        $first   = $chunk->first();
                        $last    = $chunk->last();
                        $date    = \Carbon\Carbon::parse($first->timestamp)->startOfDay();

                        // 09:15 base + idx hours for this instrument/day
                        $start = $date->copy()->setTime(9, 15)->addHours($idx);
                        $end   = $start->copy()->addHour();

                        // skip if outside market (e.g. > 15:15)
                        if ($start->gt($date->copy()->setTime(15, 15))) {
                            continue;
                        }

                        $hourly->push((object) [
                            'underlying_symbol' => $first->underlying_symbol,
                            'exchange'          => $first->exchange,
                            'expiry'            => $first->expiry,
                            'instrument_key'    => $first->instrument_key,
                            'interval'          => '1hour',
                            'timestamp'         => $start->toDateTimeString(),
                            'label'             => $start->format('Y-m-d H:i') . ' - ' . $end->format('H:i'),
                            'open'              => $first->open,
                            'high'              => $chunk->max('high'),
                            'low'               => $chunk->min('low'),
                            'close'             => $last->close,
                            'volume'            => $chunk->sum('volume'),
                            'open_interest'     => $chunk->sum('open_interest'),
                        ]);
                    }
                }

                // paginate the collection
                $page    = LengthAwarePaginator::resolveCurrentPage();
                $perPage = 50;
                $items   = $hourly->sortByDesc('timestamp')
                                  ->forPage($page, $perPage)
                                  ->values();

                $ohlc = new LengthAwarePaginator(
                    $items,
                    $hourly->count(),
                    $perPage,
                    $page,
                    ['path' => request()->url(), 'query' => request()->query()]
                );
            }
        } else {
            if ($interval && $interval !== 'all') {
                $base->where('interval', $interval);
            }

            $ohlc = $base
                ->orderByDesc('timestamp')
                ->paginate(50)
                ->withQueryString();
        }

        $expiries = ExpiredExpiry::where('instrument_type', 'FUT')
                                 ->orderByDesc('expiry_date')
                                 ->get();

        return view('futures.ohlc-index', compact(
            'ohlc',
            'expiries',
            'dateFrom',
            'dateTo',
            'interval',
            'expiryId'
        ));
    }


}
