<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StrangleViewController extends Controller {
    public function index( Request $request ) {
        $date   = $request->input( 'date' );
        $time   = $request->input( 'time' );
        $expiry = $request->input( 'expiry', $this->getCurrentExpiry() );


        if ( empty( $date ) || empty( $time ) ) {
            $timestamp = DB::table( 'ohlc_quotes' )->orderByDesc( 'id' )->first()->ts_at;
        } else {
            // Combine date and time
            $timestamp = Carbon::parse( $date . ' ' . $time );
        }
        $openPrice = $request->input( 'open_price', $this->getOpenPrice( Carbon::parse( $timestamp )->format( 'Y-m-d' ) ) );

        // Get NIFTY current price at this timestamp
        $niftyData    = $this->getNiftyPriceAtTime( $timestamp );
        $currentPrice = $niftyData['close'] ?? 0;

        // Generate strangle legs
        $strangleLegs = $this->generateStrangleLegs( $timestamp, $expiry, $openPrice );

        return view( 'strangle-view', compact(
            'strangleLegs',
            'currentPrice',
            'openPrice',
            'date',
            'time',
            'expiry',
            'niftyData'
        ) );
    }

    private function generateStrangleLegs( $timestamp, $expiry, $openPrice ) {
        // Round open price to nearest 100
        $baseStrike = round( $openPrice / 100 ) * 100;

        $legs       = [];
        $distances  = [ 50, 100, 150, 200, 250, 300, 350, 400, 450, 500 ];
        $allStrikes = [];

        foreach ( $distances as $distance ) {
            $allStrikes[] = $baseStrike + $distance;
            $allStrikes[] = $baseStrike - $distance;
        }

        // Get all OHLC data for the strikes at the specific timestamp
        $ohlcData = DB::table( 'ohlc_quotes' )
                      ->select( 'strike_price', 'instrument_type', 'close', 'volume' )
                      ->where( 'ts_at', $timestamp )
                      ->whereIn( 'strike_price', $allStrikes )
                      ->where( 'trading_symbol', 'NIFTY' )
                      ->where( 'expiry_date', $expiry )
                      ->get();

        // Index data for fast lookup
        $ohlcIndex = [];
        foreach ( $ohlcData as $record ) {
            $key               = (int) $record->strike_price . '_' . $record->instrument_type;
            $ohlcIndex[ $key ] = $record;
        }


        foreach ( $distances as $distance ) {
            $ceStrike = $baseStrike + $distance;
            $peStrike = $baseStrike - $distance;

            $ceKey = $ceStrike . '_CE';
            $peKey = $peStrike . '_PE';

            $ceRecord = $ohlcIndex[ $ceKey ] ?? null;
            $peRecord = $ohlcIndex[ $peKey ] ?? null;

            // Only add if both records exist
            if ( $ceRecord && $peRecord ) {
                $cePremium = $ceRecord->close ?? 0;
                $pePremium = $peRecord->close ?? 0;
                $ceVolume  = $ceRecord->volume ?? 0;
                $peVolume  = $peRecord->volume ?? 0;

                $totalPremium = $cePremium + $pePremium;
                $premiumDiff  = $cePremium - $pePremium;

                $legs[] = [
                    'distance'      => $distance,
                    'ce_strike'     => $ceStrike,
                    'pe_strike'     => $peStrike,
                    'ce_premium'    => round( $cePremium, 2 ),
                    'pe_premium'    => round( $pePremium, 2 ),
                    'ce_volume'     => $ceVolume,
                    'pe_volume'     => $peVolume,
                    'total_premium' => round( $totalPremium, 2 ),
                    'premium_diff'  => round( $premiumDiff, 2 ),
                ];
            }
        }

        return $legs;
    }

    private function getNiftyPriceAtTime( $timestamp ) {
        $record = DB::table( 'ohlc_quotes' )
                    ->select( 'close', 'open', 'high', 'low' )
                    ->where( 'ts_at', $timestamp )
                    ->where( 'instrument_key', 'NSE_INDEX|Nifty 50' )
                    ->first();

        return [
            'close' => $record->close ?? 0,
            'open'  => $record->open ?? 0,
            'high'  => $record->high ?? 0,
            'low'   => $record->low ?? 0,
        ];
    }

    private function getCurrentExpiry() {
        return DB::table( 'nse_expiries' )
                 ->where( 'is_current', 1 )
                 ->where( 'instrument_type', 'OPT' )
                 ->where( 'trading_symbol', 'NIFTY' )
                 ->value( 'expiry_date' );
    }

    private function getOpenPrice( $date ) {
        $record = DB::table( 'daily_trend' )
                    ->select( 'current_day_index_open' )
                    ->where( 'trading_date', $date )
                    ->where( 'symbol_name', 'NIFTY' )
                    ->first();

        return $record->current_day_index_open ?? 23400;
    }
}
