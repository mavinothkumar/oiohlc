<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Instrument;

class FetchUpstoxInstruments extends Command
{
    protected $signature = 'upstox:fetch-instruments';
    protected $description = 'Download and insert/update Upstox instrument master file';

    public function handle()
    {
        // 1. Download file
        $url = 'https://assets.upstox.com/market-quote/instruments/exchange/complete.json.gz';
        $gzFile = storage_path('app/complete.json.gz');
        $jsonFile = storage_path('app/complete.json');

        $this->info('Starting Downloading instruments file: ' . \Illuminate\Support\Carbon::now());
        $data = Http::get($url)->body();
        file_put_contents($gzFile, $data);

        // 2. Unzip file
        $this->info('Extracting...');
        $buffer_size = 4096; // Read in chunks
        $file = gzopen($gzFile, 'rb');
        $out_file = fopen($jsonFile, 'wb');
        while (!gzeof($file)) {
            fwrite($out_file, gzread($file, $buffer_size));
        }
        fclose($out_file);
        gzclose($file);

        // 3. Read large JSON
        $this->info('Parsing and upserting...');
        $json = file_get_contents($jsonFile);
        $instruments = json_decode($json, true);

        $count = 0;

        if (is_array($instruments)) {
            foreach ($instruments as $instrument) {
                Instrument::updateOrCreate(
                    ['instrument_key' => $instrument['instrument_key']],
                    [
                        'segment'             => $instrument['segment'] ?? null,
                        'name'                => $instrument['name'] ?? null,
                        'exchange'            => $instrument['exchange'] ?? null,
                        'isin'                => $instrument['isin'] ?? null,
                        'instrument_type'     => $instrument['instrument_type'] ?? null,
                        'exchange_token'      => $instrument['exchange_token'] ?? null,
                        'trading_symbol'      => $instrument['trading_symbol'] ?? null,
                        'short_name'          => $instrument['short_name'] ?? null,
                        'security_type'       => $instrument['security_type'] ?? null,
                        'lot_size'            => $instrument['lot_size'] ?? null,
                        'freeze_quantity'     => $instrument['freeze_quantity'] ?? null,
                        'tick_size'           => $instrument['tick_size'] ?? null,
                        'minimum_lot'         => $instrument['minimum_lot'] ?? null,
                        'underlying_symbol'   => $instrument['underlying_symbol'] ?? null,
                        'underlying_key'      => $instrument['underlying_key'] ?? null,
                        'underlying_type'     => $instrument['underlying_type'] ?? null,
                        'expiry'              => $instrument['expiry'] ?? null,
                        'weekly'              => $instrument['weekly'] ?? null,
                        'strike_price'        => $instrument['strike_price'] ?? null,
                        'option_type'         => $instrument['option_type'] ?? null,
                        'qty_multiplier'      => $instrument['qty_multiplier'] ?? null,
                        'mtf_enabled'         => $instrument['mtf_enabled'] ?? null,
                        'mtf_bracket'         => $instrument['mtf_bracket'] ?? null,
                        'intraday_margin'     => $instrument['intraday_margin'] ?? null,
                        'intraday_leverage'   => $instrument['intraday_leverage'] ?? null,
                    ]
                );
                $count++;
                if ($count % 1000 == 0) {
                    $this->info("Processed $count instruments...");
                }
            }
        }

        $this->info("Upsert complete. Total: $count instruments.");
    }
}

