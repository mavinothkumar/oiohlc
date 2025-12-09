<?php

// app/Console/Commands/SyncNiftyExpiredContracts.php
namespace App\Console\Commands;

use App\Models\ExpiredOptionContract;
use App\Models\ExpiredExpiry;
use App\Services\UpstoxExpiredService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncNiftyExpiredContracts extends Command
{
    protected $signature = 'upstox:sync-nifty-expired-contracts
                            {--expiry= : Only sync a single expiry date (YYYY-MM-DD)}';

    protected $description = 'Sync NIFTY expired option contracts for all (or one) expiries';

    public function handle(UpstoxExpiredService $service): int
    {
        $expiryOption = $this->option('expiry');

        $query = ExpiredExpiry::query()
                              ->where('underlying_instrument_key', 'NSE_INDEX|Nifty 50');

        if ($expiryOption) {
            $query->whereDate('expiry_date', $expiryOption);
        }

        $expiries = $query->orderBy('expiry_date')->get();

        if ($expiries->isEmpty()) {
            $this->warn('No expiries found. Run upstox:sync-nifty-expiries first.');
            return self::FAILURE;
        }

        foreach ($expiries as $expiry) {
            $date = $expiry->expiry_date->format('Y-m-d');
            $this->info("Fetching contracts for expiry {$date}...");

            $contracts = $service->getExpiredOptionContracts($date);

            if (empty($contracts)) {
                $this->line("No contracts returned for {$date}");
                continue;
            }

            // Bulk upsert in chunks
            foreach (array_chunk($contracts, 500) as $chunk) {
                $rows = [];

                foreach ($chunk as $item) {
                    if(empty($item['instrument_key'])) {
                        continue;
                    }
                    $rows[] = [
                        'name'               => $item['name'] ?? 'NIFTY',
                        'segment'            => $item['segment'] ?? 'NSE_FO',
                        'exchange'           => $item['exchange'] ?? 'NSE',
                        'expiry'             => $item['expiry'],
                        'instrument_key'     => $item['instrument_key'],
                        'exchange_token'     => $item['exchange_token'],
                        'trading_symbol'     => $item['trading_symbol'],
                        'tick_size'          => $item['tick_size'],
                        'lot_size'           => $item['lot_size'],
                        'instrument_type'    => $item['instrument_type'],
                        'freeze_quantity'    => $item['freeze_quantity'] ?? null,
                        'underlying_key'     => $item['underlying_key'],
                        'underlying_type'    => $item['underlying_type'],
                        'underlying_symbol'  => $item['underlying_symbol'],
                        'strike_price'       => $item['strike_price'],
                        'minimum_lot'        => $item['minimum_lot'] ?? null,
                        'weekly'             => $item['weekly'] ?? false,
                        'expired_expiry_id'    => $expiry->id,
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ];
                }

                // Using DB::table()->upsert to avoid duplicates on instrument_key
                DB::table('expired_option_contracts')->upsert(
                    $rows,
                    ['instrument_key'], // unique by instrument_key
                    [
                        'trading_symbol',
                        'tick_size',
                        'lot_size',
                        'instrument_type',
                        'freeze_quantity',
                        'strike_price',
                        'minimum_lot',
                        'weekly',
                        'updated_at',
                        'expired_expiry_id',
                    ],
                );
            }

            $this->info("Synced ".count($contracts)." contracts for {$date}.");
        }

        $this->info('All contracts synced.');
        return self::SUCCESS;
    }
}
