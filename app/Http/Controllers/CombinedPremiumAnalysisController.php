<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
                        ->where( 'captured_at', Carbon::parse( $selectedDate )->toDateString() .' 09:15:00' )
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
        $defaultExpiry = today()->toDateString();
        if ( Schema::hasTable( 'nse_expiries' ) ) {
            $defaultExpiry = DB::table( 'nse_expiries' )
                               ->where( 'trading_symbol', 'NIFTY' )
                               ->where( 'instrument_type', 'OPT' )
                               ->where( 'is_current', 1 )
                               ->value( 'expiry_date' ) ?? $defaultExpiry;
        }
        $selectedExpiry = $request->input( 'expiry', $defaultExpiry );

        $selectedDateTime    = $request->input( 'date' );
        $selectedEndDateTime = $request->input( 'end_date' );
        $selectedStrike = $request->input( 'selected_strike' );
        $strikeStep = (float)$request->input( 'strike_step', 100 );
        $selectedDate        = ! empty( $selectedDateTime ) ? Carbon::parse( $selectedDateTime )->format( 'Y-m-d' ) : today()->toDateString();
        if ( empty( $selectedDate ) ) {
            $selectedDateTime = $selectedDate . ' 09:15:00';
        }
        if ( empty( $selectedEndDateTime ) ) {
            $selectedEndDateTime = $selectedDate . ' 15:30:00';
        }

        $table = getTableName( 'option_chains' );
        if ( ! Schema::hasTable( $table ) && Schema::hasTable( 'option_chains' ) ) {
            $table = 'option_chains';
        }

        // 1. Resolve Backtest Strategies from /backtest/strategies
        $backtestStrategies = collect();
        if ( Schema::hasTable( 'backtest_strategies' ) ) {
            $backtestStrategies = DB::table( 'backtest_strategies' )
                                    ->where( 'is_active', true )
                                    ->orWhereNotNull( 'name' )
                                    ->orderBy( 'name' )
                                    ->get();
        }

        $selectedStrategyId = (int)$request->input( 'strategy_id', $backtestStrategies->first()?->id ?? 1 );
        $selectedStrategy   = $backtestStrategies->firstWhere( 'id', $selectedStrategyId ) ?? $backtestStrategies->first();

        // 2. Decode strategy legs
        $rawLegs = [];
        if ( $selectedStrategy && ! empty( $selectedStrategy->definition ) ) {
            $def = is_array( $selectedStrategy->definition )
                ? $selectedStrategy->definition
                : json_decode( $selectedStrategy->definition, true );
            $rawLegs = $def['legs'] ?? [];
        }

        // Default fallback: Standard 1-lot ATM Straddle
        if ( empty( $rawLegs ) ) {
            $rawLegs = [
                [ 'lots' => 1, 'side' => 'SELL', 'moneyness' => 'ATM', 'option_type' => 'CE', 'strike_offset' => 0 ],
                [ 'lots' => 1, 'side' => 'SELL', 'moneyness' => 'ATM', 'option_type' => 'PE', 'strike_offset' => 0 ],
            ];
        }

        // 3. Resolve Nifty open price / ATM center
        $dailyTrend = null;
        if ( Schema::hasTable( 'daily_trend' ) ) {
            $dailyTrend = DB::table( 'daily_trend' )
                            ->where( 'symbol_name', 'NIFTY' )
                            ->where( 'trading_date', $selectedDate )
                            ->select( 'current_day_index_open', 'index_high', 'index_low', 'index_close' )
                            ->first();
        }

        if ( ( ! $dailyTrend || ! $dailyTrend->current_day_index_open ) && Schema::hasTable( 'nse_working_days' ) && Schema::hasTable( 'daily_trend' ) ) {
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

        $openPrice = $selectedStrike ?? ($dailyTrend->current_day_index_open ?? null);
        if ( empty( $openPrice ) ) {
            $spotVal = DB::table( $table )
                         ->where( 'trading_symbol', 'NIFTY' )
                         ->where( 'captured_at', '>=', $selectedDateTime )
                         ->value( 'underlying_spot_price' );
            $openPrice = $spotVal ? (float)$spotVal : 22550.0;
        }

        $nearestStrike = round( (float)$openPrice / $strikeStep ) * $strikeStep;

        // 4. Generate the 15 ATM strikes centered around nearest strike
        $strikes = [];
        for ( $i = - 7; $i <= 7; $i ++ ) {
            $strikes[] = (float)( $nearestStrike + ( $i * $strikeStep ) );
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

        // 5. Pre-resolve legs for each of the 15 ATM strike centers and collect all needed strikes
        $step = 50.0; // Index strike step for moneyness calculation in Nifty
        $allNeededStrikes = [];
        $resolvedAtmStrategies = [];

        foreach ( $strikes as $atmStrike ) {
            $legsForAtm = [];
            foreach ( $rawLegs as $leg ) {
                $lots = max( 1, (int)( $leg['lots'] ?? 1 ) );
                $side = strtoupper( $leg['side'] ?? 'SELL' );
                $opt = strtoupper( $leg['option_type'] ?? 'CE' );
                $mon = strtoupper( $leg['moneyness'] ?? 'ATM' );
                $offset = (float)( $leg['strike_offset'] ?? 0 );

                $moneynessAdjustment = match ( $mon ) {
                    'ITM' => - $step - $offset,
                    'OTM' => $step + $offset,
                    default => 0.0,
                };

                $stk = (float)( $atmStrike + $moneynessAdjustment );
                $allNeededStrikes[] = (int)$stk;

                $legsForAtm[] = [
                    'strike'      => $stk,
                    'option_type' => $opt,
                    'side'        => $side,
                    'lots'        => $lots,
                    'moneyness'   => $mon,
                    'offset'      => (int)$offset,
                ];
            }
            $resolvedAtmStrategies[(int)$atmStrike] = $legsForAtm;
        }

        $allNeededStrikes = array_values( array_unique( $allNeededStrikes ) );

        // 6. Fast bulk retrieval of option chains across all needed strikes
        $optionRows = DB::table( $table )
                        ->whereIn( 'strike_price', $allNeededStrikes )
                        ->where( 'expiry', $selectedExpiry )
                        ->whereBetween( 'captured_at', [ $selectedDateTime, $selectedEndDateTime ] )
                        ->orderBy( 'captured_at' )
                        ->get( [ 'captured_at', 'strike_price', 'option_type', 'ltp', 'volume', 'oi' ] );

        // 7. Organize in-memory lookup maps
        $dataByTimestamp = [];
        $strikeTotals = [];

        foreach ( $optionRows as $row ) {
            $ts = $row->captured_at;
            $opt = $row->option_type;
            $stk = (int)$row->strike_price;

            if ( ! isset( $dataByTimestamp[ $ts ] ) ) {
                $dataByTimestamp[ $ts ] = [ 'CE' => [], 'PE' => [] ];
            }
            $dataByTimestamp[ $ts ][ $opt ][ $stk ] = (float)$row->ltp;

            if ( ! isset( $strikeTotals[ $opt ][ $stk ] ) ) {
                $strikeTotals[ $opt ][ $stk ] = [ 'volume' => 0, 'oi' => 0 ];
            }
            $strikeTotals[ $opt ][ $stk ]['volume'] += (int)$row->volume;
            $strikeTotals[ $opt ][ $stk ]['oi'] = (int)$row->oi;
        }

        // 8. Compute performance metrics for each of the 15 ATM centers
        $results = [];

        foreach ( $strikes as $atmStrike ) {
            $atmInt = (int)$atmStrike;
            $legsForAtm = $resolvedAtmStrategies[ $atmInt ] ?? [];

            $callStrikes = collect( $legsForAtm )->where( 'option_type', 'CE' )->pluck( 'strike' )->unique()->sort()->values()->toArray();
            $putStrikes = collect( $legsForAtm )->where( 'option_type', 'PE' )->pluck( 'strike' )->unique()->sort()->values()->toArray();

            $combinedPremiums = [];
            $validTimestamps = [];

            foreach ( $dataByTimestamp as $ts => $typeData ) {
                $totalLtp = 0;
                $hasData = false;

                foreach ( $legsForAtm as $leg ) {
                    $stk = (int)$leg['strike'];
                    $opt = $leg['option_type'];
                    $lots = $leg['lots'];
                    if ( isset( $typeData[ $opt ][ $stk ] ) ) {
                        $totalLtp += ( $typeData[ $opt ][ $stk ] * $lots );
                        $hasData = true;
                    }
                }

                if ( $hasData ) {
                    $combinedPremiums[] = $totalLtp;
                    $validTimestamps[] = $ts;
                }
            }

            if ( count( $combinedPremiums ) < 2 ) {
                continue;
            }

            $startingPremium = $combinedPremiums[0] ?? 0;
            $endingPremium   = $combinedPremiums[ count( $combinedPremiums ) - 1 ] ?? 0;
            $totalReturn     = $startingPremium - $endingPremium; // Positive return for net premium decay
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

            $maxProfit = 0;
            $maxLoss = 0;
            foreach ( $combinedPremiums as $premium ) {
                $profit = $startingPremium - $premium;
                $loss = $premium - $startingPremium;
                if ( $profit > $maxProfit ) {
                    $maxProfit = $profit;
                }
                if ( $loss > $maxLoss ) {
                    $maxLoss = $loss;
                }
            }

            $totalCallVolume = 0;
            $totalCallOI     = 0;
            $totalPutVolume  = 0;
            $totalPutOI      = 0;

            foreach ( $legsForAtm as $leg ) {
                $stk = (int)$leg['strike'];
                $opt = $leg['option_type'];
                $lots = $leg['lots'];

                $vol = $strikeTotals[ $opt ][ $stk ]['volume'] ?? 0;
                $oi  = $strikeTotals[ $opt ][ $stk ]['oi'] ?? 0;

                if ( $opt === 'CE' ) {
                    $totalCallVolume += ( $vol * $lots );
                    $totalCallOI += ( $oi * $lots );
                } else {
                    $totalPutVolume += ( $vol * $lots );
                    $totalPutOI += ( $oi * $lots );
                }
            }

            $results[] = [
                'atm_strike'            => $atmStrike,
                'put_strikes'           => $putStrikes,
                'call_strikes'          => $callStrikes,
                'strategy_legs'         => $legsForAtm,
                'total_strikes'         => count( $putStrikes ) + count( $callStrikes ),
                'starting_premium'      => round( $startingPremium, 2 ),
                'ending_premium'        => round( $endingPremium, 2 ),
                'total_return'          => round( $totalReturn, 2 ),
                'return_percent'        => round( $returnPercent, 2 ),
                'max_profit'            => round( $maxProfit, 2 ),
                'max_loss'              => round( $maxLoss, 2 ),
                'crossed_vwap'          => $crossedVwap,
                'stability_score'       => round( $stabilityScore, 2 ),
                'premium_data'          => $combinedPremiums,
                'vwap_data'             => $vwapValues,
                'timestamps'            => $validTimestamps,
                'put_volume'            => $totalPutVolume,
                'put_oi'                => $totalPutOI,
                'call_volume'           => $totalCallVolume,
                'call_oi'               => $totalCallOI,
                'put_volume_formatted'  => $formatInrCompact( $totalPutVolume ),
                'put_oi_formatted'      => $formatInrCompact( $totalPutOI ),
                'call_volume_formatted' => $formatInrCompact( $totalCallVolume ),
                'call_oi_formatted'     => $formatInrCompact( $totalCallOI ),
            ];
        }

        usort( $results, function ( $a, $b ) {
            return $a['atm_strike'] - $b['atm_strike'];
        } );

        $topResults    = array_slice( $results, 0, 15 );
        $topResultsOTM = [];
        $results_otm   = [];
        $atmStrike     = $nearestStrike;

        return view( 'strike-optimizer', compact(
            'selectedExpiry', 'selectedDate', 'openPrice',
            'strikes', 'topResults', 'topResultsOTM', 'results', 'results_otm',
            'atmStrike', 'selectedDateTime', 'selectedEndDateTime', 'selectedStrike', 'strikeStep',
            'backtestStrategies', 'selectedStrategyId', 'selectedStrategy'
        ) );
    }
}
