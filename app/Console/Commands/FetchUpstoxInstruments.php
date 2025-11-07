<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\Instrument;

class FetchUpstoxInstruments extends Command
{
    protected $signature = 'upstox:fetch-instruments';
    protected $description = 'Download and insert/update Upstox instrument master file';

    public function handle()
    {
        // 1. Download file
        $url      = 'https://assets.upstox.com/market-quote/instruments/exchange/complete.json.gz';
        $gzFile   = storage_path('app/complete.json.gz');
        $jsonFile = storage_path('app/complete.json');

        $index = [
            'NIFTY',
            'SENSEX',
            'BANKNIFTY',
        ];

        info('Starting Downloading instruments file: '.\Illuminate\Support\Carbon::now());
        $this->info('Starting Downloading instruments file: '.\Illuminate\Support\Carbon::now());
        $data = Http::get($url)->body();
        file_put_contents($gzFile, $data);

        // 2. Unzip file
        $this->info('Extracting...');
        $buffer_size = 4096; // Read in chunks
        $file        = gzopen($gzFile, 'rb');
        $out_file    = fopen($jsonFile, 'wb');
        while ( ! gzeof($file)) {
            fwrite($out_file, gzread($file, $buffer_size));
        }
        fclose($out_file);
        gzclose($file);

        // 3. Read large JSON
        $this->info('Parsing and upserting...');
        $json        = file_get_contents($jsonFile);
        $instruments = json_decode($json, true);
        $batchSize   = 1000; // process 1000 at a time
        $batch       = [];

        $count = 0;

        Instrument::truncate();

        if (is_array($instruments)) {
            foreach ($instruments as $instrument) {
                if (
                    isset($instrument['underlying_type'], $instrument['underlying_symbol']) &&
                    $instrument['underlying_type'] === 'INDEX' &&
                    in_array($instrument['underlying_symbol'], $index)
                ) {

                    $batch[] = [
                        'instrument_key'    => $instrument['instrument_key'],
                        'segment'           => $instrument['segment'] ?? null,
                        'name'              => $instrument['name'] ?? null,
                        'exchange'          => $instrument['exchange'] ?? null,
                        'isin'              => $instrument['isin'] ?? null,
                        'instrument_type'   => $instrument['instrument_type'] ?? null,
                        'exchange_token'    => $instrument['exchange_token'] ?? null,
                        'trading_symbol'    => $instrument['trading_symbol'] ?? null,
                        'short_name'        => $instrument['short_name'] ?? null,
                        'security_type'     => $instrument['security_type'] ?? null,
                        'lot_size'          => $instrument['lot_size'] ?? null,
                        'freeze_quantity'   => $instrument['freeze_quantity'] ?? null,
                        'tick_size'         => $instrument['tick_size'] ?? null,
                        'minimum_lot'       => $instrument['minimum_lot'] ?? null,
                        'underlying_symbol' => $instrument['underlying_symbol'] ?? null,
                        'underlying_key'    => $instrument['underlying_key'] ?? null,
                        'underlying_type'   => $instrument['underlying_type'] ?? null,
                        'expiry'            => $instrument['expiry'] ?? null,
                        'weekly'            => $instrument['weekly'] ?? null,
                        'strike_price'      => $instrument['strike_price'] ?? null,
                        'option_type'       => $instrument['option_type'] ?? null,
                        'qty_multiplier'    => $instrument['qty_multiplier'] ?? null,
                        'mtf_enabled'       => $instrument['mtf_enabled'] ?? null,
                        'mtf_bracket'       => $instrument['mtf_bracket'] ?? null,
                        'intraday_margin'   => $instrument['intraday_margin'] ?? null,
                        'intraday_leverage' => $instrument['intraday_leverage'] ?? null,
                        'created_at'        => Carbon::now(),
                        'updated_at'        => Carbon::now(),
                    ];

                    $count++;

                    if (count($batch) >= $batchSize) {
                        DB::table('instruments')->upsert(
                            $batch,
                            ['instrument_key'], // Unique key to match records
                            [
                                'segment',
                                'name',
                                'exchange',
                                'isin',
                                'instrument_type',
                                'exchange_token',
                                'trading_symbol',
                                'short_name',
                                'security_type',
                                'lot_size',
                                'freeze_quantity',
                                'tick_size',
                                'minimum_lot',
                                'underlying_symbol',
                                'underlying_key',
                                'underlying_type',
                                'expiry',
                                'weekly',
                                'strike_price',
                                'option_type',
                                'qty_multiplier',
                                'mtf_enabled',
                                'mtf_bracket',
                                'intraday_margin',
                                'intraday_leverage',
                            ] // Columns to update
                        );
                        $this->info("Processed $count instruments...");
                        $batch = []; // Clear batch for next

                        // optionally free memory with gc_collect_cycles();
                    }
                }
            }
            // Insert remaining records
            if ( ! empty($batch)) {
                DB::table('instruments')->upsert(
                    $batch,
                    ['instrument_key'],
                    [
                        'segment',
                        'name',
                        'exchange',
                        'isin',
                        'instrument_type',
                        'exchange_token',
                        'trading_symbol',
                        'short_name',
                        'security_type',
                        'lot_size',
                        'freeze_quantity',
                        'tick_size',
                        'minimum_lot',
                        'underlying_symbol',
                        'underlying_key',
                        'underlying_type',
                        'expiry',
                        'weekly',
                        'strike_price',
                        'option_type',
                        'qty_multiplier',
                        'mtf_enabled',
                        'mtf_bracket',
                        'intraday_margin',
                        'intraday_leverage',
                    ]
                );
                $this->info("Processed $count instruments...");
            }
        }

        unlink($gzFile);
        unlink($jsonFile);
        info("Upsert complete. Total: $count instruments.");
        $this->info("Upsert complete. Total: $count instruments.");
    }
}

