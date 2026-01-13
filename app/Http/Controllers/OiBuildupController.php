<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class OiBuildupController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'underlying_symbol' => 'nullable|string',
            'expiry'            => 'nullable|date',
            'instrument_type'   => 'nullable|string',
            'at'                => 'nullable|date_format:Y-m-d\TH:i',
            'limit'             => 'nullable|integer|min:1|max:100',
        ]);
        $datasets  = [];

        $hasFilters = filled($validated['at'] ?? null) || filled($validated['expiry'] ?? null);

        if ( ! $hasFilters) {

            return view('oi-buildup.index', [
                'no_filter' => true,
                'filters'   => [
                    'underlying_symbol' => '',
                    'expiry'            => '',
                    'instrument_type'   => '',
                    'interval'          => 5,
                    'at'                => now()->format('Y-m-d\TH:i'),
                    'limit'             => 5,
                ],
                'datasets' => $datasets,
            ]);
        }

        $limit = (int) ($validated['limit'] ?? 5);
        $at    = ! empty($validated['at']) ? $validated['at'] :  null;
        $at    = $at
            ? Carbon::createFromFormat('Y-m-d\TH:i', $at)
            : Carbon::now();

        $minutes = floor($at->minute / 5) * 5;
        $atDateTime = $at->setTime($at->hour, $minutes, 0);

        $baseWhere = [];
        if ( ! empty($validated['underlying_symbol'])) {
            $baseWhere[] = ['underlying_symbol', '=', $validated['underlying_symbol']];
        }
        if ( ! empty($validated['expiry'])) {
            $baseWhere[] = ['expiry', '=', $validated['expiry']];
        }

        if ( ! empty($validated['instrument_type'])) {
            $baseWhere[] = ['instrument_type', '=', $validated['instrument_type']];
        }

        $intervals = [5, 10, 15, 30];


        foreach ($intervals as $intervalMinutes) {
            $fromTime   = $atDateTime->copy()->subMinutes($intervalMinutes);
            $atDateTimeString = $atDateTime->format('Y-m-d H:i:s');
            $fromTimeString = $fromTime->format('Y-m-d H:i:s');

            $currentRows = DB::table('expired_ohlc')
                             ->where($baseWhere)
                             ->where('strike', '>', 0)
                             ->where('interval', '5minute')
                             ->where('timestamp', $atDateTimeString)
                             //->orderBy('open_interest', 'desc')
                             ->get();
 //dd($currentRows->toRawSql());
            $instrumentKeys = $currentRows->pluck('instrument_key')->all();

           // dd($instrumentKeys);

            $previousRows = collect();
            if ( ! empty($instrumentKeys)) {
                //dump([$atDateTimeString,$fromTime]);
                $previousRows = DB::table('expired_ohlc')
                                  ->where($baseWhere)
                                  ->where('strike', '>', 0)
                                  ->where('interval', '5minute')
                                  ->whereIn('instrument_key', $instrumentKeys)
                                  ->where('timestamp', $fromTimeString)
                                  //->orderBy('timestamp', 'desc')
                                  ->get();
            }

            $rows = [];
            foreach ($currentRows as $ik => $current) {
                if ( ! isset($previousRows[$ik])) {
                    continue;
                }
                $prev = $previousRows[$ik];

                $deltaOi    = (int) $current->open_interest - (int) $prev->open_interest;
                $deltaClose = (float) $current->close - (float) $prev->close;

                if ($deltaOi === 0 || $deltaClose === 0) {
                    $buildup = 'Neutral';
                } elseif ($deltaClose > 0 && $deltaOi > 0) {
                    $buildup = 'Long';
                } elseif ($deltaClose < 0 && $deltaOi > 0) {
                    $buildup = 'Short';
                } elseif ($deltaClose > 0 && $deltaOi < 0) {
                    $buildup = 'Cover';
                } else {
                    $buildup = 'Unwind';
                }

                $rows[] = [
                    'instrument_type' => $current->instrument_type,
                    'instrument_key'  => $ik,
                    'strike'          => $current->strike,
                    'current_close'   => (float) $current->close,
                    'prev_close'      => (float) $prev->close,
                    'current_oi'      => (int) $current->open_interest,
                    'prev_oi'         => (int) $prev->open_interest,
                    'delta_price'     => $deltaClose,
                    'delta_oi'        => $deltaOi,
                    'buildup'         => $buildup,
                    'timestamp'       => $current->timestamp,
                ];
            }

            usort($rows, fn($a, $b) => abs($b['delta_oi']) <=> abs($a['delta_oi']));
            $datasets[$intervalMinutes] = array_slice($rows, 0, $limit);
        }
//dd($datasets);

        return view('oi-buildup.index', [
            'filters'  => [
                'underlying_symbol' => $validated['underlying_symbol'] ?? '',
                'expiry'            => $validated['expiry'] ?? '',
                'instrument_type'   => $validated['instrument_type'] ?? '',
                'interval'          => $validated['interval'] ?? '',
                'at'                => $at,
                'limit'             => $limit,
            ],
            'datasets' => $datasets,
        ]);
    }


    public function expiries(Request $request)
    {
        $request->validate([
            'underlying_symbol' => 'required|string',
            'at'                => 'required|date_format:Y-m-d\TH:i',
        ]);

        $symbol = $request->underlying_symbol;
        $at     = \Carbon\Carbon::createFromFormat('Y-m-d\TH:i', $request->at);

        $date = $at->toDateString();

        $expiry = DB::table('expired_expiries')
                    ->where('instrument_type', 'OPT')
                    ->whereDate('expiry_date', '>=', $date)
                    ->orderBy('expiry_date')
                    ->limit(1)
                    ->value('expiry_date');   // returns string 'YYYY-MM-DD' or null


        return response()->json([
            'expiry' => $expiry,
        ]);
    }


}
