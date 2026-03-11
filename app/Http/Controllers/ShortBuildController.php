<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ShortBuildController extends Controller
{
    public function index(Request $request)
    {
        $selectedDate = $request->get('date')
            ? Carbon::parse($request->get('date'))->toDateString()
            : Carbon::today()->toDateString();

        $previousWorkingDay = DB::table('nse_working_days')
                                ->where('working_date', $selectedDate)
                                ->orderByDesc('working_date')
                                ->value('working_date');

        $dailyTrend       = null;
        $openAtmIndex     = null;
        $roundedAtmStrike = null;
        $strikes          = [];
        $rows             = collect();

        $rows = collect();

        if ($previousWorkingDay) {
            $dailyTrend = DB::table('daily_trend')
                            ->where('quote_date', $previousWorkingDay)
                            ->where('symbol_name', 'NIFTY')
                            ->first();

            if ($dailyTrend && ! is_null($dailyTrend->atm_index_open)) {
                $openAtmIndex     = (float) $dailyTrend->atm_index_open;
                $roundedAtmStrike = (int) (round($openAtmIndex / 50) * 50);

                $strikes = [
                    $roundedAtmStrike - 100,
                    $roundedAtmStrike - 50,
                    $roundedAtmStrike,
                    $roundedAtmStrike + 50,
                    $roundedAtmStrike + 100,
                ];

                $validCapturedAt = DB::table('option_chains')
                    //->whereDate('expiry', $selectedDate)
                                     ->where('trading_symbol', 'NIFTY')
                                     ->where('build_up', 'Short Build')
                                     ->whereDate('captured_at', $selectedDate)
                                     ->whereIn('strike_price', $strikes)
                                     ->where('diff_oi', '>', 100000)
                                     ->groupBy('captured_at')
                                     ->havingRaw('COUNT(DISTINCT strike_price) = 5')
                                     ->pluck('captured_at');

                $rows = DB::table('option_chains')
                          ->select([
                              'captured_at',
                              'strike_price',
                              'option_type',
                              'build_up',
                              'diff_oi',
                              'diff_volume',
                              'oi',
                              'volume',
                              'ltp',
                              'diff_ltp',
                          ])
                          ->where('trading_symbol', 'NIFTY')
                          ->where('build_up', 'Short Build')
                          ->where('diff_oi', '>', 100000)
                          ->whereIn('strike_price', $strikes)
                          ->whereIn('captured_at', $validCapturedAt)
                          ->orderBy('captured_at')
                          ->orderBy('strike_price')
                          ->orderBy('option_type')
                          ->get()
                          ->groupBy('captured_at');
            }
        }

        return view('short-build-atm.index', [
            'selectedDate'       => $selectedDate,
            'previousWorkingDay' => $previousWorkingDay,
            'dailyTrend'         => $dailyTrend,
            'openAtmIndex'       => $openAtmIndex,
            'roundedAtmStrike'   => $roundedAtmStrike,
            'strikes'            => $strikes,
            'rows'               => $rows,
        ]);
    }
}
