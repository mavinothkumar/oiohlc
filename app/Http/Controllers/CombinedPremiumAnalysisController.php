<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CombinedPremiumAnalysisController extends Controller {
    public function index( Request $request ) {
        // ----- 1. Default expiry -----
        $defaultExpiry = DB::table( 'nse_expiries' )
                           ->where( 'trading_symbol', 'NIFTY' )
                           ->where( 'instrument_type', 'OPT' )
                           ->where( 'is_current', 1 )
                           ->value( 'expiry_date' ) ?? today()->toDateString();

        $selectedExpiry = $request->input( 'expiry', $defaultExpiry );
        $selectedDate   = $request->input( 'date', today()->toDateString() );
        $putStrikes     = $request->input( 'put_strikes', [] );
        $callStrikes    = $request->input( 'call_strikes', [] );
        $enterPrice     = $request->input( 'enter_price' );
        $chartView      = $request->input( 'chart_view', 'combined' );

        $beforeFormat = Carbon::parse( $selectedDate )->format( 'Y-m-d' ) . ' 15:30:00';
        $endDate      = Carbon::parse( $beforeFormat )->format( 'Y-m-d\TH:i' );
        $table        = getTableName( 'option_chains' );

        // ----- 2. All strikes for dropdowns -----
        $allStrikes = DB::table( $table )
                        ->where( 'trading_symbol', 'NIFTY' )
                        ->where( 'expiry', $selectedExpiry )
                        ->distinct()
                        ->orderBy( 'strike_price' )
                        ->pluck( 'strike_price' );

        // ----- 3. Fetch and aggregate data -----
        $data         = collect();
        $labels       = [];
        $totalPutLtp  = [];
        $totalCallLtp = [];
        $combinedLtp  = [];
        $putVega      = [];
        $callVega     = [];
        $netVega      = [];
        $putTheta     = [];
        $callTheta    = [];
        $netTheta     = [];
        $putGamma     = [];
        $callGamma    = [];
        $netGamma     = [];
        $putDelta     = [];
        $callDelta    = [];
        $netDelta     = [];
        $putIv        = [];
        $callIv       = [];
        $putPop       = [];
        $callPop      = [];
        $vwap         = [];
        $oiVwap       = [];
        $netOIChange  = [];
        $putBuildUp   = [];
        $callBuildUp  = [];


        if ( ! empty( $putStrikes ) && ! empty( $callStrikes ) ) {
            $peData = DB::table( $table )
                        ->whereIn( 'strike_price', $putStrikes )
                        ->where( 'option_type', 'PE' )
                        ->where( 'expiry', $selectedExpiry )
                        ->whereBetween( 'captured_at', [ $selectedDate, $endDate ] )
                        ->orderBy( 'captured_at' )
                        ->get();

            $ceData = DB::table( $table )
                        ->whereIn( 'strike_price', $callStrikes )
                        ->where( 'option_type', 'CE' )
                        ->where( 'expiry', $selectedExpiry )
                        ->whereBetween( 'captured_at', [ $selectedDate, $endDate ] )
                        ->orderBy( 'captured_at' )
                        ->get();

            $groupedData = [];

            foreach ( $peData as $row ) {
                $key = $row->captured_at;
                if ( ! isset( $groupedData[ $key ] ) ) {
                    $groupedData[ $key ] = [
                        'captured_at'        => $row->captured_at,
                        'total_put_ltp'      => 0,
                        'total_put_volume'   => 0,
                        'total_put_oi'       => 0,
                        'total_put_prev_oi'  => 0,
                        'total_put_diff_oi'  => 0,
                        'total_put_vega'     => 0,
                        'total_put_theta'    => 0,
                        'total_put_gamma'    => 0,
                        'total_put_delta'    => 0,
                        'total_put_iv'       => 0,
                        'total_put_pop'      => 0,
                        'put_count'          => 0,
                        'total_call_ltp'     => 0,
                        'total_call_volume'  => 0,
                        'total_call_oi'      => 0,
                        'total_call_prev_oi' => 0,
                        'total_call_diff_oi' => 0,
                        'total_call_vega'    => 0,
                        'total_call_theta'   => 0,
                        'total_call_gamma'   => 0,
                        'total_call_delta'   => 0,
                        'total_call_iv'      => 0,
                        'total_call_pop'     => 0,
                        'call_count'         => 0,
                        'put_build_up'       => [],
                        'call_build_up'      => [],
                    ];
                }

                $groupedData[ $key ]['total_put_ltp']     += $row->ltp;
                $groupedData[ $key ]['total_put_volume']  += $row->volume;
                $groupedData[ $key ]['total_put_oi']      += $row->oi;
                $groupedData[ $key ]['total_put_prev_oi'] += $row->prev_oi;
                $groupedData[ $key ]['total_put_diff_oi'] += $row->diff_oi;
                $groupedData[ $key ]['total_put_vega']    += $row->vega;
                $groupedData[ $key ]['total_put_theta']   += $row->theta;
                $groupedData[ $key ]['total_put_gamma']   += $row->gamma;
                $groupedData[ $key ]['total_put_delta']   += $row->delta;
                $groupedData[ $key ]['total_put_iv']      += $row->iv;
                $groupedData[ $key ]['total_put_pop']     += $row->pop;
                $groupedData[ $key ]['put_count'] ++;
                if ( $row->build_up ) {
                    $groupedData[ $key ]['put_build_up'][] = $row->build_up;
                }
            }

            foreach ( $ceData as $row ) {
                $key = $row->captured_at;
                if ( ! isset( $groupedData[ $key ] ) ) {
                    $groupedData[ $key ] = [
                        'captured_at'        => $row->captured_at,
                        'total_put_ltp'      => 0,
                        'total_put_volume'   => 0,
                        'total_put_oi'       => 0,
                        'total_put_prev_oi'  => 0,
                        'total_put_diff_oi'  => 0,
                        'total_put_vega'     => 0,
                        'total_put_theta'    => 0,
                        'total_put_gamma'    => 0,
                        'total_put_delta'    => 0,
                        'total_put_iv'       => 0,
                        'total_put_pop'      => 0,
                        'put_count'          => 0,
                        'total_call_ltp'     => 0,
                        'total_call_volume'  => 0,
                        'total_call_oi'      => 0,
                        'total_call_prev_oi' => 0,
                        'total_call_diff_oi' => 0,
                        'total_call_vega'    => 0,
                        'total_call_theta'   => 0,
                        'total_call_gamma'   => 0,
                        'total_call_delta'   => 0,
                        'total_call_iv'      => 0,
                        'total_call_pop'     => 0,
                        'call_count'         => 0,
                        'put_build_up'       => [],
                        'call_build_up'      => [],
                    ];
                }

                $groupedData[ $key ]['total_call_ltp']     += $row->ltp;
                $groupedData[ $key ]['total_call_volume']  += $row->volume;
                $groupedData[ $key ]['total_call_oi']      += $row->oi;
                $groupedData[ $key ]['total_call_prev_oi'] += $row->prev_oi;
                $groupedData[ $key ]['total_call_diff_oi'] += $row->diff_oi;
                $groupedData[ $key ]['total_call_vega']    += $row->vega;
                $groupedData[ $key ]['total_call_theta']   += $row->theta;
                $groupedData[ $key ]['total_call_gamma']   += $row->gamma;
                $groupedData[ $key ]['total_call_delta']   += $row->delta;
                $groupedData[ $key ]['total_call_iv']      += $row->iv;
                $groupedData[ $key ]['total_call_pop']     += $row->pop;
                $groupedData[ $key ]['call_count'] ++;
                if ( $row->build_up ) {
                    $groupedData[ $key ]['call_build_up'][] = $row->build_up;
                }
            }

            ksort( $groupedData );
            $data = collect( array_values( $groupedData ) );

            $labels = $data->pluck( 'captured_at' )->map( fn( $d ) => Carbon::parse( $d )->format( 'H:i' ) );

            $totalPutLtp  = $data->pluck( 'total_put_ltp' );
            $totalCallLtp = $data->pluck( 'total_call_ltp' );
            $combinedLtp  = $data->map( fn( $r ) => round( $r['total_put_ltp'] + $r['total_call_ltp'], 2 ) );

            $putCount  = $data->pluck( 'put_count' )->map( fn( $c ) => max( $c, 1 ) );
            $callCount = $data->pluck( 'call_count' )->map( fn( $c ) => max( $c, 1 ) );

            $putVega  = $data->pluck( 'total_put_vega' )->map( fn( $v, $i ) => round( $v / $putCount[ $i ], 4 ) );
            $callVega = $data->pluck( 'total_call_vega' )->map( fn( $v, $i ) => round( $v / $callCount[ $i ], 4 ) );
            $netVega  = $putVega->zip( $callVega )->map( fn( $pair ) => round( - ( $pair[0] + $pair[1] ), 4 ) );

            $putTheta  = $data->pluck( 'total_put_theta' )->map( fn( $v, $i ) => round( $v / $putCount[ $i ], 4 ) );
            $callTheta = $data->pluck( 'total_call_theta' )->map( fn( $v, $i ) => round( $v / $callCount[ $i ], 4 ) );
            $netTheta  = $putTheta->zip( $callTheta )->map( fn( $pair ) => round( - ( $pair[0] + $pair[1] ), 4 ) );

            $putGamma  = $data->pluck( 'total_put_gamma' )->map( fn( $v, $i ) => round( $v / $putCount[ $i ], 4 ) );
            $callGamma = $data->pluck( 'total_call_gamma' )->map( fn( $v, $i ) => round( $v / $callCount[ $i ], 4 ) );
            $netGamma  = $putGamma->zip( $callGamma )->map( fn( $pair ) => round( - ( $pair[0] + $pair[1] ), 4 ) );

            $putDelta  = $data->pluck( 'total_put_delta' )->map( fn( $v, $i ) => round( $v / $putCount[ $i ], 4 ) );
            $callDelta = $data->pluck( 'total_call_delta' )->map( fn( $v, $i ) => round( $v / $callCount[ $i ], 4 ) );
            $netDelta  = $putDelta->zip( $callDelta )->map( fn( $pair ) => round( - ( $pair[0] + $pair[1] ), 4 ) );

            $putIv   = $data->pluck( 'total_put_iv' )->map( fn( $v, $i ) => round( $v / $putCount[ $i ], 2 ) );
            $callIv  = $data->pluck( 'total_call_iv' )->map( fn( $v, $i ) => round( $v / $callCount[ $i ], 2 ) );
            $putPop  = $data->pluck( 'total_put_pop' )->map( fn( $v, $i ) => round( $v / $putCount[ $i ], 2 ) );
            $callPop = $data->pluck( 'total_call_pop' )->map( fn( $v, $i ) => round( $v / $callCount[ $i ], 2 ) );

            $putBuildUp  = $data->map( fn( $r ) => implode( ', ', array_unique( $r['put_build_up'] ) ) );
            $callBuildUp = $data->map( fn( $r ) => implode( ', ', array_unique( $r['call_build_up'] ) ) );

            $cumulativePV  = 0;
            $cumulativeVol = 0;
            foreach ( $data as $row ) {
                $combinedPrice = $row['total_put_ltp'] + $row['total_call_ltp'];
                $combinedVol   = $row['total_put_volume'] + $row['total_call_volume'];
                $cumulativePV  += $combinedPrice * $combinedVol;
                $cumulativeVol += $combinedVol;
                $vwap[]        = $cumulativeVol > 0 ? round( $cumulativePV / $cumulativeVol, 2 ) : ( count( $vwap ) ? end( $vwap ) : 0 );
            }

            $cumulativeOIPV     = 0;
            $cumulativeOIWeight = 0;
            foreach ( $data as $row ) {
                $combinedPrice = $row['total_put_ltp'] + $row['total_call_ltp'];
                $weight        = max( $row['total_put_diff_oi'], 0 ) + max( $row['total_call_diff_oi'], 0 );
                if ( $weight > 0 ) {
                    $cumulativeOIPV     += $combinedPrice * $weight;
                    $cumulativeOIWeight += $weight;
                    $oiVwap[]           = round( $cumulativeOIPV / $cumulativeOIWeight, 2 );
                } else {
                    $oiVwap[] = count( $oiVwap ) ? end( $oiVwap ) : round( $combinedPrice, 2 );
                }
            }

            $runningOI = 0;
            foreach ( $data as $row ) {
                $runningOI     += ( $row['total_put_diff_oi'] + $row['total_call_diff_oi'] );
                $netOIChange[] = $runningOI;
            }
        }

        return view( 'combine-premium-analysis', compact(
            'selectedExpiry', 'selectedDate', 'putStrikes', 'callStrikes', 'enterPrice', 'chartView',
            'allStrikes',
            'labels',
            'totalPutLtp', 'totalCallLtp', 'combinedLtp',
            'putVega', 'callVega', 'netVega',
            'putTheta', 'callTheta', 'netTheta',
            'putGamma', 'callGamma', 'netGamma',
            'putDelta', 'callDelta', 'netDelta',
            'putIv', 'callIv', 'putPop', 'callPop',
            'vwap', 'oiVwap', 'netOIChange',
            'putBuildUp', 'callBuildUp',
            'data'
        ) );
    }

    public function strikeOptimizer( Request $request ) {
        $selectedExpiry = $request->input( 'expiry', DB::table( 'nse_expiries' )
                                                       ->where( 'trading_symbol', 'NIFTY' )
                                                       ->where( 'instrument_type', 'OPT' )
                                                       ->where( 'is_current', 1 )
                                                       ->value( 'expiry_date' ) ?? today()->toDateString() );

        $selectedDateTime    = $request->input( 'date' );
        $selectedEndDateTime = $request->input( 'end_date' );
        $selectedDate        = ! empty( $selectedDateTime ) ? Carbon::parse( $selectedDateTime )->format( 'Y-m-d' ) : today()->toDateString();
        if ( empty( $_selectedDate ) ) {
            $selectedDateTime = $selectedDate . ' 09:15:00';
        }
        if ( empty( $selectedEndDateTime ) ) {
            $selectedEndDateTime = $selectedDate . ' 15:30:00';
        }

        //return $selectedEndDateTime = $request->input( 'end_date', Carbon::parse( $beforeFormat )->format( 'Y-m-d H:m:i' ) );
        $table = getTableName( 'option_chains' );

        $dailyTrend = DB::table( 'daily_trend' )
                        ->where( 'symbol_name', 'NIFTY' )
                        ->where( 'trading_date', $selectedDate )
                        ->select( 'current_day_index_open', 'index_high', 'index_low', 'index_close' )
                        ->first();

        if ( ! $dailyTrend || ! $dailyTrend->current_day_index_open ) {
            $previousWorkingDay = DB::table( 'nse_working_days' )
                                    ->where( 'previous', 1 )
                                    ->orderBy( 'working_date', 'desc' )
                                    ->first();
            if ( $previousWorkingDay ) {
                $dailyTrend       = DB::table( 'daily_trend' )
                                      ->where( 'symbol_name', 'NIFTY' )
                                      ->where( 'trading_date', $previousWorkingDay->working_date )
                                      ->select( 'current_day_index_open', 'index_high', 'index_low', 'index_close' )
                                      ->first();
                $selectedDate     = $previousWorkingDay->working_date;
                $selectedDateTime = $selectedDate . ' 09:15:00';
                $selectedEndDateTime = $selectedDate . ' 15:30:00';
            }
        }

        $openPrice     = $dailyTrend->current_day_index_open;
        $nearestStrike = round( $openPrice / 100 ) * 100;

        $strikes = [];
        for ( $i = - 6; $i <= 6; $i ++ ) {
            $strikes[] = $nearestStrike + ( $i * 100 );
        }
        sort( $strikes );

        $formatInrCompact = function ( $number ) {
            $abs  = abs( (float) $number );
            $sign = $number < 0 ? '-' : '';

            if ( $abs >= 10000000 ) {
                return $sign . round( $abs / 10000000, 2 ) . ' C';
            } elseif ( $abs >= 100000 ) {
                return $sign . round( $abs / 100000, 2 ) . ' L';
            } elseif ( $abs >= 1000 ) {
                return $sign . round( $abs / 1000, 2 ) . ' T';
            }

            return $sign . number_format( $abs, 2 );
        };

        $results     = [];
        $results_otm = [];

        foreach ( $strikes as $atmStrike ) {
            // Standard: PE: ATM-200, ATM-100, ATM | CE: ATM, ATM+100, ATM+200
            $putStrikes = [];
            for ( $i = 2; $i >= 0; $i -- ) {
                $strike = $atmStrike - ( $i * 100 );
                if ( in_array( $strike, $strikes ) ) {
                    $putStrikes[] = $strike;
                }
            }
            sort( $putStrikes );

            $callStrikes = [];
            for ( $i = 0; $i <= 2; $i ++ ) {
                $strike = $atmStrike + ( $i * 100 );
                if ( in_array( $strike, $strikes ) ) {
                    $callStrikes[] = $strike;
                }
            }
            sort( $callStrikes );

            // OTM version: PE: ATM-300, ATM-200, ATM-100 | CE: ATM+100, ATM+200, ATM+300
            $putStrikesOTM = [];
            for ( $i = 3; $i >= 1; $i -- ) {
                $strike = $atmStrike - ( $i * 100 );
                if ( in_array( $strike, $strikes ) ) {
                    $putStrikesOTM[] = $strike;
                }
            }
            sort( $putStrikesOTM );

            $callStrikesOTM = [];
            for ( $i = 1; $i <= 3; $i ++ ) {
                $strike = $atmStrike + ( $i * 100 );
                if ( in_array( $strike, $strikes ) ) {
                    $callStrikesOTM[] = $strike;
                }
            }
            sort( $callStrikesOTM );

            if ( count( $putStrikes ) < 2 || count( $callStrikes ) < 2 ) {
                continue;
            }

            // Process standard strikes
            $peData = DB::table( $table )
                        ->whereIn( 'strike_price', $putStrikes )
                        ->where( 'option_type', 'PE' )
                        ->where( 'expiry', $selectedExpiry )
                        ->whereBetween( 'captured_at', [ $selectedDateTime, $selectedEndDateTime ] )
                        ->orderBy( 'captured_at' )
                        ->get();

            $ceData = DB::table( $table )
                        ->whereIn( 'strike_price', $callStrikes )
                        ->where( 'option_type', 'CE' )
                        ->where( 'expiry', $selectedExpiry )
                        ->whereBetween( 'captured_at', [ $selectedDateTime, $selectedEndDateTime ] )
                        ->orderBy( 'captured_at' )
                        ->get();

            if ( $peData->isEmpty() || $ceData->isEmpty() ) {
                continue;
            }

            $totalPutVolume  = $peData->sum( 'volume' );
            $totalPutOI      = $peData->sum( 'oi' );
            $totalCallVolume = $ceData->sum( 'volume' );
            $totalCallOI     = $ceData->sum( 'oi' );

            $grouped = [];
            foreach ( $peData as $row ) {
                $key = $row->captured_at;
                if ( ! isset( $grouped[ $key ] ) ) {
                    $grouped[ $key ] = [ 'pe' => 0, 'ce' => 0 ];
                }
                $grouped[ $key ]['pe'] += $row->ltp;
            }
            foreach ( $ceData as $row ) {
                $key = $row->captured_at;
                if ( ! isset( $grouped[ $key ] ) ) {
                    $grouped[ $key ] = [ 'pe' => 0, 'ce' => 0 ];
                }
                $grouped[ $key ]['ce'] += $row->ltp;
            }

            ksort( $grouped );

            if ( count( $grouped ) < 5 ) {
                continue;
            }

            $combinedPremiums = [];
            foreach ( $grouped as $ts => $data ) {
                $combinedPremiums[] = $data['pe'] + $data['ce'];
            }

            $startingPremium = $combinedPremiums[0] ?? 0;
            $endingPremium   = $combinedPremiums[ count( $combinedPremiums ) - 1 ] ?? 0;
            $totalReturn     = $startingPremium - $endingPremium;
            $returnPercent   = $startingPremium > 0 ? ( $totalReturn / $startingPremium ) * 100 : 0;

            $vwapValues    = [];
            $cumulativePV  = 0;
            $cumulativeVol = 0;
            foreach ( $combinedPremiums as $premium ) {
                $cumulativePV += $premium;
                $cumulativeVol ++;
                $vwapValues[] = $cumulativePV / $cumulativeVol;
            }

            $crossedVwap    = false;
            $belowVwapCount = 0;
            foreach ( $combinedPremiums as $i => $premium ) {
                if ( $i > 0 && $premium < $vwapValues[ $i ] ) {
                    $crossedVwap = true;
                    $belowVwapCount ++;
                }
            }

            $stabilityScore = count( $combinedPremiums ) > 0 ?
                ( ( count( $combinedPremiums ) - $belowVwapCount ) / count( $combinedPremiums ) ) * 100 : 0;

            $maxDrawdown = 0;
            $peak        = $startingPremium;
            foreach ( $combinedPremiums as $premium ) {
                if ( $premium > $peak ) {
                    $peak = $premium;
                }
                $drawdown = $peak - $premium;
                if ( $drawdown > $maxDrawdown ) {
                    $maxDrawdown = $drawdown;
                }
            }

            $results[] = [
                'atm_strike'            => $atmStrike,
                'put_strikes'           => $putStrikes,
                'call_strikes'          => $callStrikes,
                'total_strikes'         => count( $putStrikes ) + count( $callStrikes ),
                'starting_premium'      => round( $startingPremium, 2 ),
                'ending_premium'        => round( $endingPremium, 2 ),
                'total_return'          => round( $totalReturn, 2 ),
                'return_percent'        => round( $returnPercent, 2 ),
                'max_drawdown'          => round( $maxDrawdown, 2 ),
                'crossed_vwap'          => $crossedVwap,
                'stability_score'       => round( $stabilityScore, 2 ),
                'premium_data'          => $combinedPremiums,
                'vwap_data'             => $vwapValues,
                'timestamps'            => array_keys( $grouped ),
                'put_volume'            => $totalPutVolume,
                'put_oi'                => $totalPutOI,
                'call_volume'           => $totalCallVolume,
                'call_oi'               => $totalCallOI,
                'put_volume_formatted'  => $formatInrCompact( $totalPutVolume ),
                'put_oi_formatted'      => $formatInrCompact( $totalPutOI ),
                'call_volume_formatted' => $formatInrCompact( $totalCallVolume ),
                'call_oi_formatted'     => $formatInrCompact( $totalCallOI ),
            ];

            // Process OTM strikes if valid
            if ( count( $putStrikesOTM ) >= 2 && count( $callStrikesOTM ) >= 2 ) {
                $peDataOTM = DB::table( $table )
                               ->whereIn( 'strike_price', $putStrikesOTM )
                               ->where( 'option_type', 'PE' )
                               ->where( 'expiry', $selectedExpiry )
                               ->where( 'captured_at', '>=', $selectedDateTime )
                               ->orderBy( 'captured_at' )
                               ->get();

                $ceDataOTM = DB::table( $table )
                               ->whereIn( 'strike_price', $callStrikesOTM )
                               ->where( 'option_type', 'CE' )
                               ->where( 'expiry', $selectedExpiry )
                               ->where( 'captured_at', '>=', $selectedDateTime )
                               ->orderBy( 'captured_at' )
                               ->get();

                if ( ! $peDataOTM->isEmpty() && ! $ceDataOTM->isEmpty() ) {
                    $totalPutVolumeOTM  = $peDataOTM->sum( 'volume' );
                    $totalPutOIOTM      = $peDataOTM->sum( 'oi' );
                    $totalCallVolumeOTM = $ceDataOTM->sum( 'volume' );
                    $totalCallOIOTM     = $ceDataOTM->sum( 'oi' );

                    $groupedOTM = [];
                    foreach ( $peDataOTM as $row ) {
                        $key = $row->captured_at;
                        if ( ! isset( $groupedOTM[ $key ] ) ) {
                            $groupedOTM[ $key ] = [ 'pe' => 0, 'ce' => 0 ];
                        }
                        $groupedOTM[ $key ]['pe'] += $row->ltp;
                    }
                    foreach ( $ceDataOTM as $row ) {
                        $key = $row->captured_at;
                        if ( ! isset( $groupedOTM[ $key ] ) ) {
                            $groupedOTM[ $key ] = [ 'pe' => 0, 'ce' => 0 ];
                        }
                        $groupedOTM[ $key ]['ce'] += $row->ltp;
                    }

                    ksort( $groupedOTM );

                    if ( count( $groupedOTM ) >= 5 ) {
                        $combinedPremiumsOTM = [];
                        foreach ( $groupedOTM as $ts => $data ) {
                            $combinedPremiumsOTM[] = $data['pe'] + $data['ce'];
                        }

                        $startingPremiumOTM = $combinedPremiumsOTM[0] ?? 0;
                        $endingPremiumOTM   = $combinedPremiumsOTM[ count( $combinedPremiumsOTM ) - 1 ] ?? 0;
                        $totalReturnOTM     = $startingPremiumOTM - $endingPremiumOTM;
                        $returnPercentOTM   = $startingPremiumOTM > 0 ? ( $totalReturnOTM / $startingPremiumOTM ) * 100 : 0;

                        $vwapValuesOTM    = [];
                        $cumulativePVOTM  = 0;
                        $cumulativeVolOTM = 0;
                        foreach ( $combinedPremiumsOTM as $premium ) {
                            $cumulativePVOTM += $premium;
                            $cumulativeVolOTM ++;
                            $vwapValuesOTM[] = $cumulativePVOTM / $cumulativeVolOTM;
                        }

                        $crossedVwapOTM    = false;
                        $belowVwapCountOTM = 0;
                        foreach ( $combinedPremiumsOTM as $i => $premium ) {
                            if ( $i > 0 && $premium < $vwapValuesOTM[ $i ] ) {
                                $crossedVwapOTM = true;
                                $belowVwapCountOTM ++;
                            }
                        }

                        $stabilityScoreOTM = count( $combinedPremiumsOTM ) > 0 ?
                            ( ( count( $combinedPremiumsOTM ) - $belowVwapCountOTM ) / count( $combinedPremiumsOTM ) ) * 100 : 0;

                        $maxDrawdownOTM = 0;
                        $peakOTM        = $startingPremiumOTM;
                        foreach ( $combinedPremiumsOTM as $premium ) {
                            if ( $premium > $peakOTM ) {
                                $peakOTM = $premium;
                            }
                            $drawdownOTM = $peakOTM - $premium;
                            if ( $drawdownOTM > $maxDrawdownOTM ) {
                                $maxDrawdownOTM = $drawdownOTM;
                            }
                        }

                        $results_otm[] = [
                            'atm_strike'            => $atmStrike,
                            'put_strikes'           => $putStrikesOTM,
                            'call_strikes'          => $callStrikesOTM,
                            'total_strikes'         => count( $putStrikesOTM ) + count( $callStrikesOTM ),
                            'starting_premium'      => round( $startingPremiumOTM, 2 ),
                            'ending_premium'        => round( $endingPremiumOTM, 2 ),
                            'total_return'          => round( $totalReturnOTM, 2 ),
                            'return_percent'        => round( $returnPercentOTM, 2 ),
                            'max_drawdown'          => round( $maxDrawdownOTM, 2 ),
                            'crossed_vwap'          => $crossedVwapOTM,
                            'stability_score'       => round( $stabilityScoreOTM, 2 ),
                            'premium_data'          => $combinedPremiumsOTM,
                            'vwap_data'             => $vwapValuesOTM,
                            'timestamps'            => array_keys( $groupedOTM ),
                            'put_volume'            => $totalPutVolumeOTM,
                            'put_oi'                => $totalPutOIOTM,
                            'call_volume'           => $totalCallVolumeOTM,
                            'call_oi'               => $totalCallOIOTM,
                            'put_volume_formatted'  => $formatInrCompact( $totalPutVolumeOTM ),
                            'put_oi_formatted'      => $formatInrCompact( $totalPutOIOTM ),
                            'call_volume_formatted' => $formatInrCompact( $totalCallVolumeOTM ),
                            'call_oi_formatted'     => $formatInrCompact( $totalCallOIOTM ),
                        ];
                    }
                }
            }
        }

        usort( $results, function ( $a, $b ) {
            return $a['atm_strike'] - $b['atm_strike'];
        } );

        usort( $results_otm, function ( $a, $b ) {
            return $a['atm_strike'] - $b['atm_strike'];
        } );

        $topResults    = array_slice( $results, 0, 15 );
        $topResultsOTM = array_slice( $results_otm, 0, 15 );

        $atmStrike = $nearestStrike;

        return view( 'strike-optimizer', compact(
            'selectedExpiry', 'selectedDate', 'openPrice',
            'strikes', 'topResults', 'topResultsOTM', 'results', 'results_otm',
            'atmStrike', 'selectedDateTime', 'selectedEndDateTime'
        ) );
    }
}
