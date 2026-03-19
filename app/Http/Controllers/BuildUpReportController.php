<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BuildUpReportController extends Controller {
    private array $buildUpMap = [
        'SB-SB' => [ 'CE' => 'Short Build', 'PE' => 'Short Build', 'mixed' => false ],
        'LB-LB' => [ 'CE' => 'Long Build', 'PE' => 'Long Build', 'mixed' => false ],
        'SC-SC' => [ 'CE' => 'Short Cover', 'PE' => 'Short Cover', 'mixed' => false ],
        'LU-LU' => [ 'CE' => 'Long Unwind', 'PE' => 'Long Unwind', 'mixed' => false ],
        'SB-LB' => [ 'types' => [ 'Short Build', 'Long Build' ], 'mixed' => true ],
        'SB-SC' => [ 'types' => [ 'Short Build', 'Short Cover' ], 'mixed' => true ],
        'SB-LU' => [ 'types' => [ 'Short Build', 'Long Unwind' ], 'mixed' => true ],
        'LB-SC' => [ 'types' => [ 'Long Build', 'Short Cover' ], 'mixed' => true ],
        'LB-LU' => [ 'types' => [ 'Long Build', 'Long Unwind' ], 'mixed' => true ],
        'SC-LU' => [ 'types' => [ 'Short Cover', 'Long Unwind' ], 'mixed' => true ],
    ];

    public function index( Request $request ) {
        ini_set( 'memory_limit', '512M' );

        $expiries = DB::table( 'expired_expiries' )
                      ->where( 'instrument_type', 'OPT' )
                      ->orderByDesc( 'expiry_date' )
                      ->pluck( 'expiry_date' )
                      ->unique();

        $results   = collect();
        $expiry    = $request->input( 'expiry' );
        $buildType = $request->input( 'build_up_type' );

        if ( $expiry && $buildType && isset( $this->buildUpMap[ $buildType ] ) ) {
            $map = $this->buildUpMap[ $buildType ];

            // ── Fetch only needed columns, filter tight ──────────────────
            $fetch = function ( string $instrumentType, array|string $buildUp ) use ( $expiry ) {
                $query = DB::table( 'expired_ohlc' )
                           ->select( [
                               'strike',
                               'timestamp',
                               'close',
                               'open_interest',
                               'diff_oi',
                               'diff_volume',
                               'build_up',
                               'instrument_type',
                           ] )
                           ->where( 'expiry', $expiry )
                           ->where( 'instrument_type', $instrumentType )
                           ->where( 'interval', '5minute' )
                           ->whereTime( 'timestamp', '>=', '09:20:00' )
                           ->whereTime( 'timestamp', '<=', '15:05:00' )
                           ->whereRaw( 'ABS(diff_oi) >= 100000' )
                           ->orderBy( 'strike', 'asc' )
                           ->orderBy( 'timestamp', 'asc' );

                is_array( $buildUp )
                    ? $query->whereIn( 'build_up', $buildUp )
                    : $query->where( 'build_up', $buildUp );

                $grouped = [];
                $query->chunk( 500, function ( $rows ) use ( &$grouped ) {
                    foreach ( $rows as $row ) {
                        $grouped[ $row->strike ][ $row->timestamp ] = $row;
                    }
                } );

                return $grouped;
            };


            if ( ! $map['mixed'] ) {
                $ceRows = $fetch( 'CE', $map['CE'] );
                $peRows = $fetch( 'PE', $map['PE'] );
            } else {
                $ceRows = $fetch( 'CE', $map['types'] );
                $peRows = $fetch( 'PE', $map['types'] );
            }

            // ── Merge strikes ─────────────────────────────────────────────
            $strikes = array_unique(
                array_merge( array_keys( $ceRows ), array_keys( $peRows ) )
            );
            sort( $strikes );

            foreach ( $strikes as $strike ) {
                $ceByTime = $ceRows[ $strike ] ?? [];
                $peByTime = $peRows[ $strike ] ?? [];

                if ( $map['mixed'] ) {
                    // Only timestamps where BOTH CE and PE exist
                    $sharedTs = array_intersect(
                        array_keys( $ceByTime ),
                        array_keys( $peByTime )
                    );
                    sort( $sharedTs );

                    foreach ( $sharedTs as $ts ) {
                        $ce = $ceByTime[ $ts ];
                        $pe = $peByTime[ $ts ];

                        if (
                            $ce->build_up !== $pe->build_up
                            && in_array( $ce->build_up, $map['types'] )
                            && in_array( $pe->build_up, $map['types'] )
                        ) {
                            $results->push( [
                                'strike'    => $strike,
                                'timestamp' => $ts,
                                'ce'        => $ce,
                                'pe'        => $pe,
                            ] );
                        }
                    }

                } else {
                    // Symmetric: only include rows where BOTH CE and PE exist at the same timestamp
                    $sharedTs = array_intersect(
                        array_keys( $ceByTime ),
                        array_keys( $peByTime )
                    );
                    sort( $sharedTs );

                    foreach ( $sharedTs as $ts ) {
                        $results->push( [
                            'strike'    => $strike,
                            'timestamp' => $ts,
                            'ce'        => $ceByTime[ $ts ],   // guaranteed not null
                            'pe'        => $peByTime[ $ts ],   // guaranteed not null
                        ] );
                    }
                }

            }

            $results = $results->sortBy( 'timestamp' )->values();
        }

        $buildUpTypes = array_keys( $this->buildUpMap );

        return view( 'buildup.report', compact(
            'expiries', 'results', 'expiry', 'buildType', 'buildUpTypes'
        ) );
    }
}
