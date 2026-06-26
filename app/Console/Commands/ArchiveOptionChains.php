<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ArchiveOptionChains extends Command
{
    protected $signature = 'option-chains:archive';
    protected $description = 'Move previous day option chain and OHLC quotes data to history tables and keep only current day data in live tables';

    public function handle(): int
    {
        //$todayStart = now('Asia/Kolkata')->startOfDay()->toDateTimeString();

        DB::transaction(function (){
            DB::statement("
                INSERT INTO option_chains_history (
                    id,
                    instrument_key,
                    underlying_key,
                    trading_symbol,
                    expiry,
                    strike_price,
                    option_type,
                    ltp,
                    diff_ltp,
                    volume,
                    diff_volume,
                    oi,
                    diff_oi,
                    close_price,
                    bid_price,
                    bid_qty,
                    ask_price,
                    ask_qty,
                    prev_oi,
                    vega,
                    theta,
                    gamma,
                    delta,
                    iv,
                    pop,
                    underlying_spot_price,
                    pcr,
                    captured_at,
                    created_at,
                    updated_at,
                    build_up
                )
                SELECT
                    id,
                    instrument_key,
                    underlying_key,
                    trading_symbol,
                    expiry,
                    strike_price,
                    option_type,
                    ltp,
                    diff_ltp,
                    volume,
                    diff_volume,
                    oi,
                    diff_oi,
                    close_price,
                    bid_price,
                    bid_qty,
                    ask_price,
                    ask_qty,
                    prev_oi,
                    vega,
                    theta,
                    gamma,
                    delta,
                    iv,
                    pop,
                    underlying_spot_price,
                    pcr,
                    captured_at,
                    created_at,
                    updated_at,
                    build_up
                FROM option_chains
            ");

            DB::table('option_chains')->delete();

            DB::statement("
                INSERT INTO ohlc_quotes_history (
                    id,
                    instrument_key,
                    instrument_type,
                    trading_symbol,
                    expiry_date,
                    strike_price,
                    open,
                    high,
                    low,
                    close,
                    volume,
                    ts,
                    ts_at,
                    last_price,
                    created_at,
                    updated_at
                )
                SELECT
                    id,
                    instrument_key,
                    instrument_type,
                    trading_symbol,
                    expiry_date,
                    strike_price,
                    open,
                    high,
                    low,
                    close,
                    volume,
                    ts,
                    ts_at,
                    last_price,
                    created_at,
                    updated_at
                FROM ohlc_quotes
            ");

            DB::table('ohlc_quotes')->delete();
        });

        $this->info('Previous option chain and OHLC quotes data moved to history successfully.');

        return self::SUCCESS;
    }
}
