<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IndexFuturesChartController extends Controller
{
    public function index()
    {
        return view('index-futures-chart');
    }

    public function dailyTrend(Request $request)
    {
        $request->validate([
            'symbol_name' => 'required|string',
            'quote_date'  => 'required|date',
        ]);

        $symbol = $request->symbol_name;
        $date   = $request->quote_date;

        // Fetch from daily_trend table for trend lines
        $trend = DB::table('daily_trend')
                   ->where('symbol_name', $symbol)
                   ->whereDate('quote_date', $date)
                   ->first();

        if ( ! $trend) {
            return response()->json([
                'index_data'  => [],
                'future_data' => [],
                'trend_data'  => null,
            ]);
        }
        $dailyTrendFuture = DB::table('expired_ohlc')
                              ->where('underlying_symbol', 'NIFTY')
                              ->where('interval', 'day')
                              ->where('instrument_type', 'FUT')
                              ->whereDate('timestamp', $date)
                              ->first(['open', 'high', 'low', 'close']);
        $futureATM        = round((float) $dailyTrendFuture->open / 50) * 50;
        
        // Convert to floats for chart consumption
        $trendData = [
            'index_high'             => (float) $trend->index_high,
            'index_low'              => (float) $trend->index_low,
            'index_close'            => (float) $trend->index_close,
            'current_day_index_open' => (float) $trend->current_day_index_open,
            'earth_high'             => (float) $trend->earth_high,
            'earth_low'              => (float) $trend->earth_low,
            'min_r'                  => (float) $trend->min_r,
            'min_s'                  => (float) $trend->min_s,
            'max_r'                  => (float) $trend->max_r,
            'max_s'                  => (float) $trend->max_s,
            'atm_ce'                 => (float) $trend->atm_ce,
            'atm_pe'                 => (float) $trend->atm_pe,
            'atm_r_1'                => (float) $trend->atm_r_1,
            'atm_r_2'                => (float) $trend->atm_r_2,
            'atm_r_3'                => (float) $trend->atm_r_3,
            'atm_s_1'                => (float) $trend->atm_s_1,
            'atm_s_2'                => (float) $trend->atm_s_2,
            'atm_s_3'                => (float) $trend->atm_s_3,
            'atm_r'                  => (float) $trend->atm_r,
            'atm_s'                  => (float) $trend->atm_s,
            'atm_r_avg'              => (float) $trend->atm_r_avg,
            'atm_s_avg'              => (float) $trend->atm_s_avg,
            'atm_index_open'         => (float) $trend->atm_index_open,
            'future_atm'             => (float) $futureATM,
            'future_open'            => (float) $dailyTrendFuture->open,
            'future_close'            => (float) $dailyTrendFuture->close,
            'show'                   => [
                'open_type'              => $trend->open_type,
                'open_value'             => (float) $trend->open_value,
                'atm_index_open'         => (float) $trend->atm_index_open,
                'current_day_index_open' => (float) $trend->current_day_index_open,
                'previous_day_atm'       => (float) $trend->strike,
                'atm_ce'                 => (float) $trend->atm_ce,
                'atm_pe'                 => (float) $trend->atm_pe,
                'index_high'             => (float) $trend->index_high,
                'index_low'              => (float) $trend->index_low,
                'index_close'            => (float) $trend->index_close,
                'future_atm'             => (float) $futureATM,
                'future_open'            => (float) $dailyTrendFuture->open,
            ],
        ];

        // Fetch 5-minute candles from expired_ohlc
        $startOfDay = $date.' 09:15:00';
        $endOfDay   = $date.' 15:30:00';


        $index5m = DB::table('expired_ohlc')
                     ->where('underlying_symbol', $symbol)
                     ->where('instrument_type', 'INDEX')
                     ->where('interval', '5minute')
                     ->whereBetween('timestamp', [$startOfDay, $endOfDay])
                     ->orderBy('timestamp', 'asc')
                     ->get(['open', 'high', 'low', 'close', 'timestamp']);

        $fut5m = DB::table('expired_ohlc')
                   ->where('underlying_symbol', $symbol)
                   ->where('instrument_type', 'FUT')
                   ->where('interval', '5minute')
                   ->whereBetween('timestamp', [$startOfDay, $endOfDay])
                   ->orderBy('timestamp', 'asc')
                   ->get(['open', 'high', 'low', 'close', 'timestamp']);


        $highestHigh = $index5m->max('high');
        $lowestLow   = $index5m->min('low');
        $open        = optional($index5m->first())->open;

        $trendData['show']['low_point']  = $open - $lowestLow;
        $trendData['show']['high_point'] = $highestHigh - $open;

        $map = fn($row) => [
            'time'  => strtotime($row->timestamp),
            'open'  => (float) $row->open,
            'high'  => (float) $row->high,
            'low'   => (float) $row->low,
            'close' => (float) $row->close,
        ];

        return response()->json([
            'trend_data'  => $trendData,
            'index_data'  => $index5m->map($map)->values(),
            'future_data' => $fut5m->map($map)->values(),
        ]);
    }

}
