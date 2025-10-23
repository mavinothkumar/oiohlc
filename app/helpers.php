<?php

use App\Models\OptionChain3M;
use Illuminate\Support\Carbon;

if ( ! function_exists( 'format_inr_compact' ) ) {
    function format_inr_compact( $number ): string {
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
    }
}

// ----------------------------------------------
// put this helper in the command or a helpers.php
// ----------------------------------------------
function segmentFromSymbol( string $symbol ): string {
    return match ( strtoupper( $symbol ) ) {
        'SENSEX' => 'BSE_FNO',
        'NIFTY', 'BANKNIFTY' => 'NSE_FNO',
        default => 'NSE_FNO',   // safe fallback
    };
}

function closingSpots( Carbon $tradingDay ): array {
    $latestTs = OptionSnapshot3M::selectRaw( 'symbol, MAX(timestamp) AS ts' )
                                ->whereIn( 'symbol', [ 'NIFTY', 'BANKNIFTY', 'SENSEX' ] )
                                ->whereDate( 'timestamp', $tradingDay )
                                ->groupBy( 'symbol' );

    $rows = OptionSnapshot3M::select( 'os.symbol', 'os.underlying_strike' )
                            ->fromSub( $latestTs, 'l' )
                            ->join( 'option_snapshots_3m AS os', function ( $j ) {
                                $j->on( 'os.symbol', '=', 'l.symbol' )
                                  ->on( 'os.timestamp', '=', 'l.ts' );
                            } )
                            ->get();

    return $rows->pluck( 'underlying_strike', 'symbol' )->map( fn( $v ) => (float) $v )->all();
}

function exchange_segments() {
    return [
        'IDX_I',
        'NSE_EQ',
        'NSE_FNO',
        'NSE_CURRENCY',
        'BSE_EQ',
        'MCX_COMM',
        'BSE_CURRENCY',
        'BSE_FNO',
    ];
}
