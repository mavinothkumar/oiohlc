<?php

// app/Console/Commands/ExportLossDaysOhlc.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExportLossDaysOhlc extends Command {
    protected $signature = 'export:loss-days-ohlc
        {--strategy=strangle_straddle : Strategy name}
        {--symbol=NIFTY               : Underlying symbol}
        {--from=                      : From date (YYYY-MM-DD)}
        {--to=                        : To date (YYYY-MM-DD)}
        {--interval=5minute           : Candle interval}
        {--output=                    : Output file path (default: storage/app/)}';

    protected $description = 'Export loss days upper/lower strangle OHLC candles to a CSV file';

    public function handle(): int {
        $strategy = $this->option( 'strategy' );
        $symbol   = $this->option( 'symbol' );
        $interval = $this->option( 'interval' );
        $from     = $this->option( 'from' );
        $to       = $this->option( 'to' );

        $_filename = ( ! empty( $from ) && ! empty( $to ) ) ? $from . '__' . $to : '';

        $filename  = $this->option( 'output' )
            ?: storage_path( "app/loss_days_ohlc_{$strategy}_" . $_filename . '_' . now()->format( 'Ymd_His' ) . '.csv' );

        $this->info( "Strategy : {$strategy}" );
        $this->info( "Symbol   : {$symbol}" );
        $this->info( "Interval : {$interval}" );
        $this->info( "From     : " . ( $from ?: 'all' ) );
        $this->info( "To       : " . ( $to ?: 'all' ) );
        $this->info( "Output   : {$filename}" );
        $this->newLine();

        // ── Inner: upper = ATM+offset, lower = ATM-offset ─────────────
        $inner = DB::table( 'backtest_trades' )
                   ->selectRaw( "
                trade_date,
                expiry,
                day_group_id,
                MAX(strike) AS upper_strike,
                MIN(strike) AS lower_strike
            " )
                   ->where( 'strategy', $strategy )
                   ->where( 'day_outcome', 'loss' )
                   ->when( $from, fn( $q ) => $q->whereDate( 'trade_date', '>=', $from ) )
                   ->when( $to, fn( $q ) => $q->whereDate( 'trade_date', '<=', $to ) )
                   ->groupByRaw( 'day_group_id, trade_date, expiry, day_outcome' );

        // ── Main query — 4 legs joined by same candle timestamp ────────
        $sql = "
            SELECT
                bt.trade_date,
                bt.expiry,
                bt.upper_strike,
                bt.lower_strike,
                upper_ce.timestamp      AS candle_time,

                -- Upper CE (OTM Call — ATM + offset)
                upper_ce.open           AS upper_ce_open,
                upper_ce.high           AS upper_ce_high,
                upper_ce.low            AS upper_ce_low,
                upper_ce.close          AS upper_ce_close,
                upper_ce.volume         AS upper_ce_volume,
                upper_ce.open_interest  AS upper_ce_oi,

                -- Upper PE (ITM Put — ATM + offset)
                upper_pe.open           AS upper_pe_open,
                upper_pe.high           AS upper_pe_high,
                upper_pe.low            AS upper_pe_low,
                upper_pe.close          AS upper_pe_close,
                upper_pe.volume         AS upper_pe_volume,
                upper_pe.open_interest  AS upper_pe_oi,

                -- Lower CE (ITM Call — ATM - offset)
                lower_ce.open           AS lower_ce_open,
                lower_ce.high           AS lower_ce_high,
                lower_ce.low            AS lower_ce_low,
                lower_ce.close          AS lower_ce_close,
                lower_ce.volume         AS lower_ce_volume,
                lower_ce.open_interest  AS lower_ce_oi,

                -- Lower PE (OTM Put — ATM - offset)
                lower_pe.open           AS lower_pe_open,
                lower_pe.high           AS lower_pe_high,
                lower_pe.low            AS lower_pe_low,
                lower_pe.close          AS lower_pe_close,
                lower_pe.volume         AS lower_pe_volume,
                lower_pe.open_interest  AS lower_pe_oi

            FROM ({$inner->toSql()}) AS bt

            -- Upper CE drives the rows (one per 5-min candle)
            INNER JOIN expired_ohlc upper_ce
                ON  upper_ce.underlying_symbol = ?
                AND upper_ce.instrument_type   = 'CE'
                AND upper_ce.strike            = bt.upper_strike
                AND upper_ce.expiry            = bt.expiry
                AND upper_ce.interval          = ?
                AND DATE(upper_ce.timestamp)   = bt.trade_date

            -- Upper PE — same timestamp
            LEFT JOIN expired_ohlc upper_pe
                ON  upper_pe.underlying_symbol = ?
                AND upper_pe.instrument_type   = 'PE'
                AND upper_pe.strike            = bt.upper_strike
                AND upper_pe.expiry            = bt.expiry
                AND upper_pe.interval          = ?
                AND upper_pe.timestamp         = upper_ce.timestamp

            -- Lower CE — same timestamp
            LEFT JOIN expired_ohlc lower_ce
                ON  lower_ce.underlying_symbol = ?
                AND lower_ce.instrument_type   = 'CE'
                AND lower_ce.strike            = bt.lower_strike
                AND lower_ce.expiry            = bt.expiry
                AND lower_ce.interval          = ?
                AND lower_ce.timestamp         = upper_ce.timestamp

            -- Lower PE — same timestamp
            LEFT JOIN expired_ohlc lower_pe
                ON  lower_pe.underlying_symbol = ?
                AND lower_pe.instrument_type   = 'PE'
                AND lower_pe.strike            = bt.lower_strike
                AND lower_pe.expiry            = bt.expiry
                AND lower_pe.interval          = ?
                AND lower_pe.timestamp         = upper_ce.timestamp

            ORDER BY bt.trade_date ASC, upper_ce.timestamp ASC
        ";

        $bindings = array_merge(
            $inner->getBindings(),
            [
                $symbol,
                $interval,   // upper_ce
                $symbol,
                $interval,   // upper_pe
                $symbol,
                $interval,   // lower_ce
                $symbol,
                $interval,   // lower_pe
            ]
        );

        // ── Fetch & stream to CSV ──────────────────────────────────────
        $this->info( 'Fetching rows...' );

        $rows = DB::select( $sql, $bindings );

        if ( empty( $rows ) ) {
            $this->warn( 'No rows found. Check your strategy name and filters.' );

            return self::FAILURE;
        }

        $total = count( $rows );
        $this->info( "Found {$total} rows. Writing CSV..." );

        $file = fopen( $filename, 'w' );

        if ( ! $file ) {
            $this->error( "Cannot open file for writing: {$filename}" );

            return self::FAILURE;
        }

        // Header
        fputcsv( $file, array_keys( (array) $rows[0] ) );

        $bar = $this->output->createProgressBar( $total );
        $bar->start();

        foreach ( $rows as $row ) {
            fputcsv( $file, (array) $row );
            $bar->advance();
        }

        $bar->finish();
        fclose( $file );

        $this->newLine( 2 );
        $this->info( "✅ Export complete → {$filename}" );
        $this->info( 'Rows exported : ' . number_format( $total ) );
        $this->info( 'File size     : ' . $this->humanFileSize( filesize( $filename ) ) );

        return self::SUCCESS;
    }

    private function humanFileSize( int $bytes ): string {
        $units = [ 'B', 'KB', 'MB', 'GB' ];
        $i     = 0;
        while ( $bytes >= 1024 && $i < count( $units ) - 1 ) {
            $bytes /= 1024;
            $i ++;
        }

        return round( $bytes, 2 ) . ' ' . $units[ $i ];
    }
}
