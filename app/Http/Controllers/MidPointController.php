<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MidPointController extends Controller {
    public function index( Request $request ) {
        // 1. Get working date
        $today = Carbon::today()->format( 'Y-m-d' );

        $workingDay = DB::table( 'nse_working_days' )
                        ->where( 'working_date', '<=', $today )
                        ->orderBy( 'working_date', 'desc' )
                        ->first();

        $defaultWorkingDate = $workingDay ? $workingDay->working_date : $today;

        $fromDate = $request->input( 'from_date', $defaultWorkingDate . ' 09:15:00' );
        $toDate   = $request->input( 'to_date', $defaultWorkingDate . ' 15:29:00' );

        $workingDate = Carbon::parse( $fromDate )->format( 'Y-m-d' );

        // 2. Get Expiry Date
        $expiryDate = $request->input( 'expiry_date' );
        if ( ! $expiryDate ) {
            $expiryRecord = DB::table( 'nse_expiries' )
                              ->where( 'instrument_type', 'OPT' )
                              ->where( 'trading_symbol', 'NIFTY' )
                              ->where( 'is_current', '1' )
                              ->first();
            $expiryDate   = $expiryRecord ? $expiryRecord->expiry_date : null;
        } else {
            $expiryRecord = DB::table( 'nse_expiries' )
                              ->where( 'instrument_type', 'OPT' )
                              ->where( 'trading_symbol', 'NIFTY' )
                              ->where( 'expiry_date', $expiryDate )
                              ->first();
            $expiryDate   = $expiryRecord ? $expiryRecord->expiry_date : null;
        }

        // 3. Get daily_trend values
        $dailyTrend = DB::table( 'daily_trend' )
                        ->where( 'symbol_name', 'NIFTY' )
                        ->where('expiry_date',  $expiryDate )
                        ->where( function ( $q ) use ( $workingDate ) {
                            $q->where( 'quote_date', $workingDate )
                              ->orWhere( 'trading_date', $workingDate );
                        } )
                        ->first();

        $midPoint       = $dailyTrend ? $dailyTrend->mid_point : 0;
        $startStrikeRaw = $dailyTrend ? ( $dailyTrend->current_day_index_open ?? $dailyTrend->atm_ce) : 0;

        // Round to nearest 50
        $startStrike = round( $startStrikeRaw / 50 ) * 50;

        // 4. Collect strikes and check prices
        $ceStrikes = [];
        $peStrikes = [];

        if ( $midPoint > 0 && $startStrike > 0 && $expiryDate ) {
            $ohlc_quotes     = getTableName( 'ohlc_quotes' );
            $maxRange        = 10;
            $possibleStrikes = [];
            for ( $i = - $maxRange; $i <= $maxRange; $i ++ ) {
                $possibleStrikes[] = $startStrike + ( $i * 50 );
            }

            $latestOHLCQuoteTime = DB::table( 'ohlc_quotes' )->orderByDesc( 'id' )->first();
            $instrument_keys = DB::table( 'instruments' )
                                 ->whereIn( 'instrument_type', [ 'CE', 'PE' ] )
                                 ->where( 'name', 'NIFTY' )
                                 ->where( 'expiry', $expiryRecord->expiry )
                                 ->whereIn( 'strike_price', $possibleStrikes )->pluck( 'instrument_key' );

            // Get latest prices for all NIFTY options of the selected expiry within the date range
            $latestPricesQuery = DB::table( $ohlc_quotes )
                                   ->select( 'strike_price', 'instrument_type', 'close' )
                                   ->whereIn( 'instrument_key', $instrument_keys )
                                   ->where( 'ts_at', $latestOHLCQuoteTime->ts_at ?? '' )
                                   ->get();

            // Structure the prices
            $pricesByStrikeType = [];
            foreach ( $latestPricesQuery as $quote ) {
                $pricesByStrikeType[ $quote->strike_price ][ $quote->instrument_type ] = $quote->close;
            }


            // We need to collect strikes starting from +/- 10, ensuring at least 6 CE and 6 PE < midPoint
            // Let's expand range gradually
            $range   = 10;
            $ceCount = 0;
            $peCount = 0;

            // Generate strikes within current range
            while ( true ) {
                $ceStrikes = [];
                $peStrikes = [];
                $ceCount   = 0;
                $peCount   = 0;

                for ( $i = - $range; $i <= $range; $i ++ ) {
                    $strike = (float) $startStrike + ( $i * 50 ) . '.00';

                    // Check CE
                    if ( isset( $pricesByStrikeType[ $strike ]['CE'] ) ) {
                        $price = number_format( $pricesByStrikeType[ $strike ]['CE'], 2 );
                        if ( $price < $midPoint && $price >= 25 ) {
                            $ceStrikes[] = [ 'strike' => $strike, 'price' => $price, 'type' => 'CE' ];
                            $ceCount ++;
                        }
                    }

                    // Check PE
                    if ( isset( $pricesByStrikeType[ $strike ]['PE'] ) ) {
                        $price = number_format( $pricesByStrikeType[ $strike ]['PE'], 2 );
                        if ( $price < $midPoint && $price >= 25 ) {
                            $peStrikes[] = [ 'strike' => $strike, 'price' => $price, 'type' => 'PE' ];
                            $peCount ++;
                        }
                    }
                }

                if ( $ceCount >= 6 && $peCount >= 6 ) {
                    break;
                }

                // If range gets too large, break to avoid infinite loop
                if ( $range > 11 ) {
                    break;
                }

                $range ++;
            }


            // Combine strikes for display
            $allStrikesMap = [];
            foreach ( $ceStrikes as $c ) {
                $allStrikesMap[ (string) $c['strike'] ]['CE'] = $c['price'];
            }
            foreach ( $peStrikes as $p ) {
                $allStrikesMap[ (string) $p['strike'] ]['PE'] = $p['price'];
            }

            $strikeKeys = array_keys( $allStrikesMap );
            // Sort ascending
            sort( $strikeKeys, SORT_NUMERIC );

            $combinedStrikes = [];
            foreach ( $strikeKeys as $strike ) {
                $combinedStrikes[] = [
                    'strike'   => $strike,
                    'ce_price' => $allStrikesMap[ $strike ]['CE'] ?? '-',
                    'pe_price' => $allStrikesMap[ $strike ]['PE'] ?? '-',
                ];
            }
        }

        return view( 'mid-point', compact(
            'fromDate', 'toDate', 'expiryDate', 'midPoint', 'startStrike', 'ceStrikes', 'peStrikes', 'combinedStrikes', 'startStrikeRaw'
        ) );
    }
}
