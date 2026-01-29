<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class OiBuildupLiveController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'at'    => 'nullable|date_format:Y-m-d\TH:i',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);


        $expiry = DB::table('nse_expiries')
                    ->where('instrument_type', 'OPT')
                    ->where('is_current', 1)
                    ->where('trading_symbol', 'NIFTY')
                    ->value('expiry_date');

        //dd($expiry);

        $limit = (int) ($validated['limit'] ?? 6);
        $at    = ! empty($validated['at']) ? $validated['at'] : null;
        $at    = $at
            ? Carbon::createFromFormat('Y-m-d\TH:i', $at)
            : Carbon::now();

        $minutes = floor($at->minute / 3) * 3; // Changed from 5 to 3 minutes

        $atDateTime = $at->setTime($at->hour, $minutes, 0);

        $intervals = [3, 6, 9, 15, 30, 375]; // Updated intervals to 3-minute multiples
        $datasets  = [];
        $underlying_spot = 0;

        foreach ($intervals as $intervalMinutes) {
            $fromTime         = $atDateTime->copy()->subMinutes($intervalMinutes);
            $atDateTimeString = $atDateTime->format('Y-m-d H:i:s');
            $fromTimeString   = $fromTime->format('Y-m-d H:i:s');
            $fromDateString   = $fromTime->format('Y-m-d').' 09:15:00';

            //dd([$atDateTimeString, $fromTimeString]);

            $currentRows = DB::table('option_chains')
                             ->where('expiry', $expiry)
                             ->where('captured_at', $atDateTimeString)
                //->orderBy('diff_oi', 'desc')
                             ->get()
                             ->keyBy('instrument_key');

            $instrument_key = $currentRows->pluck('instrument_key')->all();

            $previousRows = collect();
            if ( ! empty($instrument_key)) {
                //dump([$atDateTimeString,$fromTime]);
                $previousRows = DB::table('option_chains')
                                  ->where('expiry', $expiry)
                                  ->whereIn('instrument_key', $instrument_key)
                    //->where('captured_at', $fromTimeString)
                                  ->when(375 === $intervalMinutes, function ($query) use ($fromDateString) {
                        $first_tick = DB::table('option_chains')->where('captured_at','>=', $fromDateString)->first()->captured_at;
                        return $query->where('captured_at',$first_tick);
                    }, function ($query) use ($fromTimeString) {
                        return $query->where('captured_at', $fromTimeString);
                    })
                                  ->get()
                                  ->keyBy('instrument_key');
                //->orderBy('diff_oi', 'desc')
            }
            $rows = [];
            foreach ($currentRows as $ik => $current) {
                if ( ! isset($previousRows[$ik])) {
                    continue;
                }
                $prev = $previousRows[$ik];

                $deltaOi    = (int) $current->oi - (int) $prev->oi;
                $deltaClose = (float) $current->ltp - (float) $prev->ltp;

                $underlying_spot = (float) $current->underlying_spot_price;

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
                    'strike'        => $current->strike_price,
                    'option_type'   => $current->option_type,
                    'current_close' => (float) $current->ltp,
                    'prev_close'    => (float) $prev->ltp,
                    'current_oi'    => (int) $current->oi,
                    'prev_oi'       => (int) $prev->oi,
                    'delta_price'   => $deltaClose,
                    'delta_oi'      => $deltaOi,
                    'buildup'       => $buildup,
                    'timestamp'     => $current->captured_at,
                ];
            }

            usort($rows, fn($a, $b) => abs($b['delta_oi']) <=> abs($a['delta_oi']));
            $datasets[$intervalMinutes] = array_slice($rows, 0, $limit);
        }
        return view('oi-buildup.live', [
            'filters'  => [
                'at'    => $at,
                'limit' => $limit,
                'date'  => $atDateTimeString,
            ],
            'datasets' => $datasets,
            'underlying_spot' => $underlying_spot,
            'oiThreshold' => 1000000, //1500000,
        ]);
    }

}
