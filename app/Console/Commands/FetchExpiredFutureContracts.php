<?php

// app/Console/Commands/FetchExpiredFutureContracts.php
namespace App\Console\Commands;

use App\Models\ExpiredExpiry;
use App\Models\ExpiredOptionContract;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FetchExpiredFutureContracts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Example usages:
     *  php artisan upstox:fetch-expired-fut
     *  php artisan upstox:fetch-expired-fut --date=2024-11-27
     */
    protected $signature = 'upstox:fetch-expired-fut
                            {--date= : Only process this expiry_date (Y-m-d)}
                            {--symbol= : Only process this underlying_symbol}';

    /**
     * The console command description.
     */
    protected $description = 'Fetch expired future contracts from Upstox and store them in expired_option_contracts';

    public function handle(): int
    {
        $this->info('Starting fetch of expired future contracts...');

        $query = ExpiredExpiry::query()
                              ->where('instrument_type', 'FUT');

        if ($date = $this->option('date')) {
            $query->whereDate('expiry_date', $date);
        }

        if ($symbol = $this->option('symbol')) {
            $query->where('underlying_symbol', $symbol);
        }

        $expiries = $query->orderBy('expiry_date')->get();

        if ($expiries->isEmpty()) {
            $this->warn('No expired futures found to process.');
            return self::SUCCESS;
        }

        $client = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.upstox.access_token'),
            'Accept'        => 'application/json',
        ])->baseUrl('https://api.upstox.com/v2');

        foreach ($expiries as $expiry) {
            $this->info("Processing {$expiry->underlying_symbol} - {$expiry->expiry_date->toDateString()}");

            $response = $client->get('/expired-instruments/future/contract', [
                'instrument_key' => $expiry->underlying_instrument_key,
                'expiry_date'    => $expiry->expiry_date->toDateString(),
            ]);

            if ($response->failed()) {
                $this->error("Failed for {$expiry->underlying_instrument_key} {$expiry->expiry_date}: {$response->status()}");
                $this->line($response->body());
                continue;
            }

            $data = $response->json();

            // Upstox future contract response is typically a list under "data" or similar key.
            // Adjust this mapping once you confirm exact schema from docs/response.[web:3][web:5]
            $contracts = $data['data'] ?? $data ?? [];

            if (!is_array($contracts)) {
                $this->warn('Unexpected response format, skipping.');
                continue;
            }

            foreach ($contracts as $contract) {
                $this->storeContract($expiry, $contract);
            }
        }

        $this->info('Completed fetching expired future contracts.');
        return self::SUCCESS;
    }

    protected function storeContract(ExpiredExpiry $expiry, array $contract): void
    {
        // Map Upstox fields to your table fields.
        // Field names below are illustrative; confirm using the official schema.[web:3][web:5]
        $payload = [
            'name'             => $contract['name'] ?? $expiry->underlying_symbol,
            'segment'          => $contract['segment'] ?? 'NFO-FUT',
            'exchange'         => $contract['exchange'] ?? 'NSE',
            'expiry'           => $contract['expiry'] ?? $expiry->expiry_date->toDateString(),
            'instrument_key'   => $contract['instrument_key'],
            'exchange_token'   => $contract['exchange_token'] ?? '',
            'trading_symbol'   => $contract['trading_symbol'] ?? '',
            'tick_size'        => $contract['tick_size'] ?? 5,
            'lot_size'         => $contract['lot_size'] ?? 50,
            'instrument_type'  => $contract['instrument_type'] ?? 'FUT',
            'freeze_quantity'  => $contract['freeze_quantity'] ?? null,
            'weekly'           => $contract['weekly'] ?? 0,
            'underlying_key'   => $contract['underlying_key'] ?? $expiry->underlying_instrument_key,
            'underlying_type'  => $contract['underlying_type'] ?? 'INDEX',
            'underlying_symbol'=> $contract['underlying_symbol'] ?? $expiry->underlying_symbol,
            'strike_price'     => $contract['strike_price'] ?? 0,
            'minimum_lot'      => $contract['minimum_lot'] ?? null,
            'expired_expiry_id'=> $expiry->id,
        ];

        ExpiredOptionContract::updateOrCreate(
            ['instrument_key' => $payload['instrument_key']],
            $payload
        );
    }
}

