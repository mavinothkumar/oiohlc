<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MultiStrikeAnalysisController extends Controller {
    public function index( Request $request ) {
        // ----- 1. Default expiry -----
        $defaultExpiry = DB::table( 'nse_expiries' )
                           ->where( 'trading_symbol', 'NIFTY' )
                           ->where( 'instrument_type', 'OPT' )
                           ->where( 'is_current', 1 )
                           ->value( 'expiry_date' ) ?? today()->toDateString();

        $selectedExpiry = $request->input( 'expiry', $defaultExpiry );
        $selectedDate   = $request->input( 'date', today()->toDateString() );
        $putStrike      = $request->input( 'put_strike' );
        $callStrike     = $request->input( 'call_strike' );
        $enterPrice     = $request->input( 'enter_price' );
        $chartView      = $request->input( 'chart_view', 'all' );
        $gridColumns    = $request->input( 'grid_columns', 2 ); // 1, 2, 3, or 4
        $strategyType   = $request->input( 'strategy_type', 'strangle' ); // 'strangle' or 'straddle'
        $step         = $request->input( 'step', 100 ); // 50 or 100

        $table =  today()->toDateString() === $selectedDate ? 'option_chains' : 'option_chains_history';

        // ----- 2. Strikes for dropdowns -----
        $strikes = DB::table( $table )
                     ->where( 'trading_symbol', 'NIFTY' )
                     ->where( 'expiry', $selectedExpiry )
                     ->distinct()
                     ->orderBy( 'strike_price' )
                     ->pluck( 'strike_price' );

        // ----- 3. Generate strike combinations based on strategy type -----
        $strikeCombinations = [];

        if ( $putStrike && $callStrike ) {
            $putStrike  = (float) $putStrike;
            $callStrike = (float) $callStrike;

            if ( $strategyType === 'straddle' ) {
                // Straddle: Both strikes must be the same
                // Use the PE strike as the base (or average if different)
                $baseStrike = $putStrike;

                $strikeCombinations = [
                    [ 'put' => $baseStrike, 'call' => $baseStrike ],
                    [ 'put' => $baseStrike - $step, 'call' => $baseStrike - $step ],
                    [ 'put' => $baseStrike + $step, 'call' => $baseStrike + $step ],
                    [ 'put' => $baseStrike - ( $step * 2 ), 'call' => $baseStrike - ( $step * 2 ) ],
                    [ 'put' => $baseStrike + ( $step * 2 ), 'call' => $baseStrike + ( $step * 2 ) ],
                ];
            } else {
                // Strangle (default): Different strikes for PE and CE
                if ( $putStrike == $callStrike ) {
                    // If same strike given in strangle mode, widen symmetrically
                    $strikeCombinations = [
                        [ 'put' => $putStrike, 'call' => $callStrike ],
                        [ 'put' => $putStrike - $step, 'call' => $callStrike + $step ],
                        [ 'put' => $putStrike - ( $step * 2 ), 'call' => $callStrike + ( $step * 2 ) ],
                        [ 'put' => $putStrike - ( $step * 3 ), 'call' => $callStrike + ( $step * 3 ) ],
                    ];
                } else {
                    // Strangle: maintain the same width between strikes
                    $width        = abs( $callStrike - $putStrike );
                    $halfWidth    = $width / 2;
                    $centerStrike = ( $putStrike + $callStrike ) / 2;

                    $strikeCombinations = [
                        [ 'put' => $putStrike, 'call' => $callStrike ],
                        [ 'put' => $centerStrike - $halfWidth - $step, 'call' => $centerStrike + $halfWidth + $step ],
                        [ 'put' => $centerStrike - $halfWidth - ( $step * 2 ), 'call' => $centerStrike + $halfWidth + ( $step * 2 ) ],
                        [ 'put' => $centerStrike - $halfWidth - ( $step * 3 ), 'call' => $centerStrike + $halfWidth + ( $step * 3 ) ],
                    ];
                }
            }
        }

        // ----- 4. Fetch data for all combinations -----
        $chartData = [];

        foreach ( $strikeCombinations as $index => $combo ) {
            $putStrikeVal  = $combo['put'];
            $callStrikeVal = $combo['call'];

            $key = "{$putStrikeVal}_PE_{$callStrikeVal}_CE";

            $rows = DB::table( $table. ' as put' )
                      ->join( $table .' as call', function ( $join ) {
                          $join->on( 'put.captured_at', '=', 'call.captured_at' )
                               ->on( 'put.expiry', '=', 'call.expiry' )
                               ->on( 'put.trading_symbol', '=', 'call.trading_symbol' );
                      } )
                      ->where( 'put.strike_price', $putStrikeVal )
                      ->where( 'put.option_type', 'PE' )
                      ->where( 'call.strike_price', $callStrikeVal )
                      ->where( 'call.option_type', 'CE' )
                      ->where( 'put.expiry', $selectedExpiry )
                      ->whereDate( 'put.captured_at', $selectedDate )
                      ->orderBy( 'put.captured_at' )
                      ->select(
                          'put.captured_at',
                          'put.ltp as put_ltp', 'call.ltp as call_ltp',
                          'put.volume as put_volume', 'call.volume as call_volume',
                          'put.diff_oi as put_diff_oi', 'call.diff_oi as call_diff_oi',
                          'put.build_up as put_build_up', 'call.build_up as call_build_up'
                      )
                      ->get();

            if ( $rows->isNotEmpty() ) {
                $labels = $rows->pluck( 'captured_at' )->map( fn( $d ) => Carbon::parse( $d )->format( 'H:i' ) );

                // Combined premium
                $putLtp      = $rows->pluck( 'put_ltp' );
                $callLtp     = $rows->pluck( 'call_ltp' );
                $combinedLtp = $rows->map( fn( $r ) => round( $r->put_ltp + $r->call_ltp, 2 ) );

                // VWAP
                $vwap          = [];
                $cumulativePV  = 0;
                $cumulativeVol = 0;
                foreach ( $rows as $row ) {
                    $combinedPrice = $row->put_ltp + $row->call_ltp;
                    $combinedVol   = $row->put_volume + $row->call_volume;
                    $cumulativePV  += $combinedPrice * $combinedVol;
                    $cumulativeVol += $combinedVol;
                    $vwap[]        = $cumulativeVol > 0 ? round( $cumulativePV / $cumulativeVol, 2 ) : ( count( $vwap ) ? end( $vwap ) : 0 );
                }

                // Net OI Change
                $netOIChange = [];
                $runningOI   = 0;
                foreach ( $rows as $row ) {
                    $runningOI     += ( $row->put_diff_oi + $row->call_diff_oi );
                    $netOIChange[] = $runningOI;
                }

                // Build-up status
                $putBuildUp  = $rows->pluck( 'put_build_up' );
                $callBuildUp = $rows->pluck( 'call_build_up' );

                $chartData[ $key ] = [
                    'put_strike'  => $putStrikeVal,
                    'call_strike' => $callStrikeVal,
                    'labels'      => $labels,
                    'putLtp'      => $putLtp,
                    'callLtp'     => $callLtp,
                    'combinedLtp' => $combinedLtp,
                    'vwap'        => $vwap,
                    'netOIChange' => $netOIChange,
                    'putBuildUp'  => $putBuildUp,
                    'callBuildUp' => $callBuildUp,
                ];
            }
        }

        return view( 'multi-strike-analysis', compact(
            'selectedExpiry', 'selectedDate', 'putStrike', 'callStrike', 'enterPrice',
            'chartView', 'gridColumns', 'strategyType', 'strikes', 'chartData', 'step'
        ) );
    }
}
