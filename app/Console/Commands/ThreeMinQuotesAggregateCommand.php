<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FullMarketQuote;
use App\Models\ThreeMinQuote;
use Carbon\Carbon;

class ThreeMinQuotesAggregateCommand extends Command
{
    protected $signature = 'market:aggregate-3min-quotes';
    protected $description = 'Aggregate 3-min OHLC and metrics from full_market_quotes to three_min_quotes';

    public function handle()
    {
        $this->info('Starting 3-min quote aggregation at: ' . now());

        // Find all 1-min candles in the last 3 minutes
        $now = now();
        $threeMinsAgo = $now->copy()->subMinutes(3);

        // You can filter further if you want by symbol/expiry/instruments
        $groupedQuotes = FullMarketQuote::where('timestamp', '>=', $threeMinsAgo)
                                        ->where('timestamp', '<', $now)
                                        ->get()
                                        ->groupBy('instrument_token');

        foreach ($groupedQuotes as $instrument_token => $quotes) {
            if ($quotes->count() < 3) continue; // Only aggregate if we have 3 records

            $first = $quotes->sortBy('timestamp')->first();
            $last  = $quotes->sortBy('timestamp')->last();

            // Calculate aggregation
            ThreeMinQuote::create([
                'instrument_token'    => $instrument_token,
                'symbol'              => $last->symbol,
                'symbol_name'         => $last->symbol_name,
                'expiry'              => $last->expiry,
                'expiry_date'         => $last->expiry_date,
                'expiry_timestamp'    => $last->expiry_timestamp,
                'strike'              => $last->strike,
                'option_type'         => $last->option_type,
                'last_price'          => $last->last_price,
                'volume'              => $last->volume,
                'average_price'       => $last->average_price,
                'oi'                  => $last->oi,
                'net_change'          => $last->net_change,
                'total_buy_quantity'  => $last->total_buy_quantity,
                'total_sell_quantity' => $last->total_sell_quantity,
                'lower_circuit_limit' => $last->lower_circuit_limit,
                'upper_circuit_limit' => $last->upper_circuit_limit,
                'last_trade_time'     => $last->last_trade_time,
                'oi_day_high'         => $last->oi_day_high,
                'oi_day_low'          => $last->oi_day_low,
                'open'                => $first->open,
                'high'                => $quotes->max('high'),
                'low'                 => $quotes->min('low'),
                'close'               => $last->close,
                'timestamp'           => $last->timestamp,
                'diff_oi'             => $first->oi - $last->oi,
                'diff_volume'         => $first->volume - $last->volume,
                'diff_buy_quantity'   => $first->total_buy_quantity - $last->total_buy_quantity,
                'diff_sell_quantity'  => $first->total_sell_quantity - $last->total_sell_quantity,
                'diff_quantity'       => $last->total_buy_quantity - $last->total_sell_quantity,
            ]);
        }

        $this->info('Aggregation complete at: ' . now());
        return 0;
    }
}
