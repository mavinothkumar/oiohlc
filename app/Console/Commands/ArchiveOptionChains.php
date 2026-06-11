<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ArchiveOptionChains extends Command
{
    protected $signature = 'option-chains:archive';
    protected $description = 'Move previous day option chain data to history table and keep only current day data in live table';

    public function handle(): int
    {
        $todayStart = now('Asia/Kolkata')->startOfDay()->toDateTimeString();

        DB::transaction(function () use ($todayStart) {
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
                WHERE captured_at < ?
            ", [$todayStart]);

            DB::table('option_chains')
              ->where('captured_at', '<', $todayStart)
              ->delete();
        });

        $this->info('Previous option chain data moved to history successfully.');

        return self::SUCCESS;
    }
}
