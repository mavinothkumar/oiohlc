<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class GenerateIndexGap extends Command {
    protected $signature = 'index-gap:generate
        {--symbol=NIFTY : Index symbol}
        {--from= : From date YYYY-MM-DD}
        {--to= : To date YYYY-MM-DD}
        {--truncate : Truncate index_gap before rebuild}';

    protected $description = 'Generate index gap values using nse_working_days and expired_ohlc day candles.';

    public function handle(): int {
        $symbol = strtoupper( $this->option( 'symbol' ) );
        $from   = $this->option( 'from' );
        $to     = $this->option( 'to' );

        if ( ! $from || ! $to ) {
            $this->error( '--from and --to are required.' );

            return self::FAILURE;
        }

        if ( $this->option( 'truncate' ) ) {
            DB::table( 'index_gap' )->where( 'symbol_name', $symbol )->delete();
            $this->warn( "Deleted old index_gap rows for {$symbol}" );
        }

        $workingDays = DB::table( 'nse_working_days' )
                         ->whereBetween( 'working_date', [ $from, $to ] )
                         ->orderBy( 'working_date' )
                         ->pluck( 'working_date' )
                         ->values();

        if ( $workingDays->isEmpty() ) {
            $this->warn( 'No working days found in the range.' );

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar( $workingDays->count() );
        $bar->start();

        $inserted = 0;
        $skipped  = 0;

        foreach ( $workingDays as $tradeDate ) {
            $prevDate = DB::table( 'nse_working_days' )
                          ->where( 'working_date', '<', $tradeDate )
                          ->orderByDesc( 'working_date' )
                          ->value( 'working_date' );

            if (!$prevDate) {
                $this->warn("SKIP {$tradeDate}: no previous working day");
                $skipped++;
                $bar->advance();
                continue;
            }

            $prevRow = DB::table( 'expired_ohlc' )
                         ->where( 'underlying_symbol', $symbol )
                         ->where( 'instrument_type', 'INDEX' )
                         ->where( 'interval', 'day' )
                         ->whereDate( 'timestamp', $prevDate )
                         ->select( 'close', 'high', 'low' )
                         ->first();

            $currRow = DB::table( 'expired_ohlc' )
                         ->where( 'underlying_symbol', $symbol )
                         ->where( 'instrument_type', 'INDEX' )
                         ->where( 'interval', 'day' )
                         ->whereDate( 'timestamp', $tradeDate )
                         ->select( 'open' )
                         ->first();

            if ( ! $prevRow || ! $currRow ) {
                if (!$prevRow) {
                    $this->warn("SKIP {$tradeDate}: missing previous day candle for {$prevDate}");
                    $skipped++;
                    $bar->advance();
                    continue;
                }
                if (!$currRow) {
                    $this->warn("SKIP {$tradeDate}: missing current day candle");
                    $skipped++;
                    $bar->advance();
                    continue;
                }
            }

            $previousClose    = (float) $prevRow->close;
            $previousHigh     = (float) $prevRow->high;
            $previousLow      = (float) $prevRow->low;

            $currentOpen = (float) $currRow->open;
            $gapValue    = round( $currentOpen - $previousClose, 2 );

            $gapType = match ( true ) {
                $gapValue > 0 => 'Gap Up',
                $gapValue < 0 => 'Gap Down',
                default => 'Flat',
            };

            $gapValue         = round( $currentOpen - $previousClose, 2 );
            $gapAbs           = round( abs( $gapValue ), 2 );
            $previousDayRange = round( $previousHigh - $previousLow, 2 );

            $gapPctPrevClose = $previousClose > 0
                ? round( ( $gapValue / $previousClose ) * 100, 4 )
                : null;

            $gapPctPrevRange = $previousDayRange > 0
                ? round( ( $gapAbs / $previousDayRange ) * 100, 4 )
                : null;

            DB::table( 'index_gap' )->updateOrInsert(
                [
                    'symbol_name'  => $symbol,
                    'trading_date' => $tradeDate,
                ],
                [
                    'previous_trading_date' => $prevDate,
                    'previous_close'        => $previousClose,
                    'previous_high'         => $previousHigh,
                    'previous_low'          => $previousLow,
                    'previous_day_range'    => $previousDayRange,
                    'current_open'          => $currentOpen,
                    'gap_value'             => $gapValue,
                    'gap_abs'               => $gapAbs,
                    'gap_type'              => $gapType,
                    'gap_pct_prev_close'    => $gapPctPrevClose,
                    'gap_pct_prev_range'    => $gapPctPrevRange,
                    'updated_at'            => now(),
                    'created_at'            => now(),
                ]
            );

            $inserted ++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine( 2 );
        $this->info( "Done. Inserted/updated: {$inserted}, skipped: {$skipped}" );

        return self::SUCCESS;
    }
}
