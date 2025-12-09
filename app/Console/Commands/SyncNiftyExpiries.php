<?php

// app/Console/Commands/SyncNiftyExpiries.php
namespace App\Console\Commands;

use App\Models\ExpiredExpiry;
use App\Services\UpstoxExpiredService;
use Illuminate\Console\Command;

class SyncNiftyExpiries extends Command
{
    protected $signature = 'upstox:sync-nifty-expiries';
    protected $description = 'Sync NIFTY expired expiries from Upstox';

    public function handle(UpstoxExpiredService $service): int
    {
        $this->info('Fetching NIFTY expiries from Upstox...');

        $expiries = $service->getNiftyExpiries(); // array of strings or objects, per API

        foreach ($expiries as $expiry) {
            // If API returns objects like { "expiry_date": "2025-04-17" }
            $expiryDate = is_array($expiry) ? ($expiry['expiry_date'] ?? null) : $expiry;

            if (! $expiryDate) {
                continue;
            }

            $model = ExpiredExpiry::updateOrCreate(
                [
                    'underlying_instrument_key' => 'NSE_INDEX|Nifty 50',
                    'expiry_date'               => $expiryDate,
                ],
                [
                    'underlying_symbol' => 'NIFTY',
                ],
            );

            $this->line("Synced expiry: {$model->expiry_date}");
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}

