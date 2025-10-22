<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\OptionChain;

class FetchOptionChain3Min extends Command
{
    protected $signature = 'optionchain:fetch-3m';
    protected $description = 'Aggregate option chain data every 3 minutes with difference calculation';

    public function handle()
    {
        $capturedAt = now()->second(0)->minute(floor(now()->minute / 3) * 3);

        $latest = OptionChain::where('created_at', '>=', now()->subMinutes(5))->get();

        foreach ($latest as $record) {
            $previous = DB::table('option_chains_3m')
                          ->where('trading_symbol', $record->trading_symbol)
                          ->where('strike_price', $record->strike_price)
                          ->where('option_type', $record->option_type)
                          ->where('expiry', $record->expiry)
                          ->orderByDesc('captured_at')
                          ->first();

            $data = [
                'underlying_key' => $record->underlying_key,
                'trading_symbol' => $record->trading_symbol,
                'expiry' => $record->expiry,
                'strike_price' => $record->strike_price,
                'option_type' => $record->option_type,
                'ltp' => $record->ltp,
                'volume' => $record->volume,
                'oi' => $record->oi,
                'close_price' => $record->close_price,
                'bid_price' => $record->bid_price,
                'bid_qty' => $record->bid_qty,
                'ask_price' => $record->ask_price,
                'ask_qty' => $record->ask_qty,
                'prev_oi' => $record->prev_oi,
                'vega' => $record->vega,
                'theta' => $record->theta,
                'gamma' => $record->gamma,
                'delta' => $record->delta,
                'iv' => $record->iv,
                'pop' => $record->pop,
                'underlying_spot_price' => $record->underlying_spot_price,
                'pcr' => $record->pcr,
                'diff_underlying_spot_price' => $previous ? $record->underlying_spot_price - $previous->underlying_spot_price : null,
                'diff_ltp' => $previous ? $record->ltp - $previous->ltp : null,
                'diff_volume' => $previous ? $record->volume - $previous->volume : null,
                'diff_oi' => $previous ? $record->oi - $previous->oi : null,
                'captured_at' => $capturedAt,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            DB::table('option_chains_3m')->insert($data);
        }

        $this->info('3-minute option chain snapshot inserted at '.$capturedAt);
    }
}
