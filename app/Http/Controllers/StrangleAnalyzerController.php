<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StrangleAnalyzerController extends Controller {
    public function index( Request $request ) {
        // --- Defaults ---
        $tradingDate = $request->input( 'trading_date', now()->toDateString() );
        $time        = $request->input( 'time' );


        if ( empty( $time ) ) {
            $ohlc_quote_ts = DB::table( 'ohlc_quotes' )->orderByDesc( 'id' )->value( 'ts_at' );
        } else {
            $ohlc_quote_ts = Carbon::parse( "{$tradingDate} {$time}" )->toDateTimeString();
        }
        // --- Expiry ---
        $expiry = $request->input( 'expiry_date' );
        if ( ! $expiry ) {
            $expiryRow = DB::table( 'nse_expiries' )
                           ->where( 'is_current', 1 )
                           ->where( 'trading_symbol', 'NIFTY' )
                           ->where( 'instrument_type', 'OPT' )
                           ->orderBy( 'expiry_date' )
                           ->first();
            $expiry    = $expiryRow ? $expiryRow->expiry_date : null;
        }

        // --- Available expiries for dropdown ---
        $expiries = DB::table( 'nse_expiries' )
                      ->where( 'instrument_type', 'OPT' )
                      ->where( 'trading_symbol', 'NIFTY' )
                      ->where( 'instrument_type', 'OPT' )
                      ->orderBy( 'expiry_date' )
                      ->get( [ 'expiry_date', 'is_current' ] );

        // --- NIFTY Day Open from daily_trend ---
        $dailyTrend = DB::table( 'daily_trend' )
                        ->where( 'symbol_name', 'NIFTY' )
                        ->whereDate( 'trading_date', $tradingDate )
                        ->first();

        $niftyOpen = $dailyTrend->current_day_index_open ?? null;

        // --- Auto-generate ATM strikes rounded to 100 ---
        $atmStrike = $niftyOpen ? ( round( $niftyOpen / 100 ) * 100 ) : null;

        $ceStrike = (int) $request->input( 'ce_strike', $atmStrike ?? 0 );
        $peStrike = (int) $request->input( 'pe_strike', $atmStrike ?? 0 );

        // --- Generate 10 leg rows ---
        // Determine base offsets
        // Row 0 is closest to ATM: CE = ceStrike+50, PE = peStrike-50
        // Each subsequent row shifts by 50 on each side
        $legs = [];

        if ( $ceStrike && $peStrike && $expiry ) {


            for ( $i = 0; $i < 10; $i ++ ) {
                $ceLeg = $ceStrike + 50 + ( $i * 50 );
                $peLeg = $peStrike - 50 - ( $i * 50 );

                // Distance from given CE/PE centre
                $distance = $i === 0
                    ? abs( $ceLeg - $ceStrike ) + abs( $ceStrike - $peLeg )
                    : ( $i * 50 ) + 50;

                // Fetch last price for CE leg
                $ceData = DB::table( 'ohlc_quotes' )
                            ->where( 'trading_symbol', 'NIFTY' )
                            ->where( 'instrument_type', 'CE' )
                            ->where( 'strike_price', $ceLeg )
                            ->where( 'expiry_date', $expiry )
                            ->where( 'ts_at', $ohlc_quote_ts )
                            ->first( [ 'close' ] );

                // Fetch last price for PE leg
                $peData = DB::table( 'ohlc_quotes' )
                            ->where( 'trading_symbol', 'NIFTY' )
                            ->where( 'instrument_type', 'PE' )
                            ->where( 'strike_price', $peLeg )
                            ->where( 'expiry_date', $expiry )
                            ->where( 'ts_at', $ohlc_quote_ts )
                            ->first( [ 'close' ] );

                $cePremium = $ceData ? ( $ceData->close ?? 0 ) : null;
                $pePremium = $peData ? ( $peData->close ?? 0 ) : null;

                $totalPremium = ( $cePremium !== null && $pePremium !== null )
                    ? round( $cePremium + $pePremium, 2 )
                    : null;

                $premiumDiff = ( $cePremium !== null && $pePremium !== null )
                    ? round( ( $cePremium - $pePremium ), 2 )
                    : null;

                $legs[] = [
                    'distance'   => $distance,
                    'ce_strike'  => $ceLeg,
                    'pe_strike'  => $peLeg,
                    'ce_premium' => $cePremium,
                    'pe_premium' => $pePremium,
                    'total'      => $totalPremium,
                    'diff'       => $premiumDiff,
                ];
            }
        }

        $isStraddle = $ceStrike === $peStrike;

        return view( 'strangle-analyzer', compact(
            'tradingDate', 'time',
            'expiry', 'expiries',
            'ceStrike', 'peStrike',
            'niftyOpen', 'atmStrike',
            'legs', 'isStraddle'
        ) );
    }
}
