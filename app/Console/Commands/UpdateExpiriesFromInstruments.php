<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Instrument;
use App\Models\Expiry;
use Illuminate\Support\Carbon;

class UpdateExpiriesFromInstruments extends Command
{
    protected $signature = 'expiries:update-benchmarks';
    protected $description = 'Update expiries for Nifty50, BankNifty, Sensex options and futures';

    public function handle()
    {
        $exchanges = ['NSE', 'BSE'];
        // Index symbols to filter
        $indices = [
            'NIFTY',
            'BANKNIFTY',
            'SENSEX',
        ];
        // Grouping logic for expiry type
        $types = [
            'FUT' => ['FUT'],
            'OPT' => ['CE'],
        ];

       info('Starting UpdateExpiriesFromInstruments: ' . \Illuminate\Support\Carbon::now());
        $this->info('Starting UpdateExpiriesFromInstruments: ' . \Illuminate\Support\Carbon::now());
        Expiry::truncate();
        foreach ($exchanges as $exchange) {
            foreach ($indices as $indexSymbol) {
                foreach ($types as $expiry_type => $type_values) {
                    // Filter by trading_symbol or underlying_symbol
                    $expiries = Instrument::where('exchange', $exchange)
                                          ->whereIn('instrument_type', $type_values)
                                          ->where(function ($query) use ($indexSymbol) {
                                              $query->where('trading_symbol', 'LIKE', "%$indexSymbol%")
                                                    ->orWhere('underlying_symbol', 'LIKE', "%$indexSymbol%");
                                          })
                                          ->whereNotNull('expiry')
                                          ->pluck('expiry')
                                          ->unique()
                                          ->sort()
                                          ->values();

                    info('$exchange', [$exchange, $indexSymbol, $type_values, $expiry_type]);
                    info('$expiries', [$expiries]);



                    foreach ($expiries as $index => $expiry_ts) {
                        Expiry::updateOrCreate([
                            'exchange' => $exchange,
                            'instrument_type' => $expiry_type,
                            'trading_symbol' => $indexSymbol,
                            'expiry' => $expiry_ts,
                        ], [
                            'segment' => "{$exchange}_FO",
                            'expiry_date' => date('Y-m-d', $expiry_ts / 1000),
                            'is_current' => ($index === 0),
                            'is_next' => ($index === 1),
                        ]);
                    }
                    $this->info(
                        "Updated {$exchange} {$indexSymbol} {$expiry_type}: "
                        . "Current = " . (isset($expiries[0]) ? date('Y-m-d', $expiries[0] / 1000) : 'N/A')
                        . ", Next = " . (isset($expiries[1]) ? date('Y-m-d', $expiries[1] / 1000) : 'N/A')
                    );
                }
            }
        }

        $this->info('Benchmark index expiries updated.');
    }
}
