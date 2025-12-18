<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\ExpiredExpiry;
use App\Models\ExpiredOptionContract;

class SyncExpiredFutureContracts extends Command
{
    protected $signature = 'upstox:sync-expired-futures-to-options';
    protected $description = 'Fetch expired future contracts for FUT expiries and store details in expired_option_contracts';

    protected string $baseUrl;
    protected string $token;

    public function __construct()
    {
        parent::__construct();

        $this->baseUrl = config('services.upstox.base_url', 'https://api.upstox.com/v2');
        $this->token   = config('services.upstox.access_token');
    }

    public function handle(): int
    {
        if (! $this->token) {
            $this->error('Upstox token not configured.');
            return self::FAILURE;
        }

        // 1. Get all FUT expiries
        $futExpiries = ExpiredExpiry::query()
                                    ->where('instrument_type', 'FUT')
                                    ->get();

        if ($futExpiries->isEmpty()) {
            $this->info('No FUT expiries found.');
            return self::SUCCESS;
        }

        foreach ($futExpiries as $expiry) {
            $this->info("Processing FUT expiry: {$expiry->underlying_instrument_key} - {$expiry->expiry_date}");

            // 2. Call Upstox "Get Expired Future Contracts" for this underlying+expiry
            $contracts = $this->getExpiredFutureContracts(
                $expiry->underlying_instrument_key,
                $expiry->expiry_date->format('Y-m-d')
            );

            if (! $contracts) {
                $this->warn("No contracts returned for {$expiry->underlying_instrument_key} - {$expiry->expiry_date}");
                continue;
            }

            // 3. Store each contract into expired_option_contracts
            foreach ($contracts as $contract) {
                // Avoid duplicates for same instrument_key + expired_expiry_id
                $exists = ExpiredOptionContract::query()
                                               ->where('instrument_key', $contract['instrument_key'])
                                               ->where('expired_expiry_id', $expiry->id)
                                               ->exists();

                if ($exists) {
                    continue;
                }

                ExpiredOptionContract::create([
                    'name'              => $contract['name'] ?? $expiry->underlying_symbol,
                    'segment'           => $contract['segment'] ?? 'NSE_FO',
                    'exchange'          => $contract['exchange'] ?? 'NSE',
                    'expiry'            => $contract['expiry'],          // 'YYYY-MM-DD'
                    'instrument_key'    => $contract['instrument_key'],
                    'exchange_token'    => $contract['exchange_token'] ?? null,
                    'trading_symbol'    => $contract['trading_symbol'],
                    'tick_size'         => $contract['tick_size'] ?? 0,
                    'lot_size'          => $contract['lot_size'] ?? 0,
                    'instrument_type'   => $contract['instrument_type'], // FUT / CE / PE depending on API
                    'freeze_quantity'   => $contract['freeze_quantity'] ?? 0,
                    'weekly'            => $contract['weekly'] ?? 0,
                    'underlying_key'    => $contract['underlying_key'] ?? $expiry->underlying_instrument_key,
                    'underlying_type'   => $contract['underlying_type'] ?? 'INDEX',
                    'underlying_symbol' => $contract['underlying_symbol'] ?? $expiry->underlying_symbol,
                    'strike_price'      => $contract['strike_price'] ?? 0,
                    'minimum_lot'       => $contract['minimum_lot'] ?? ($contract['lot_size'] ?? 0),
                    'expired_expiry_id' => $expiry->id,
                ]);
            }
        }

        $this->info('Expired future contracts synced into expired_option_contracts.');

        return self::SUCCESS;
    }

    /**
     * Call Upstox Get Expired Future Contracts API.
     */
    protected function getExpiredFutureContracts(string $underlyingInstrumentKey, string $expiryDate): array
    {
        $response = Http::withToken($this->token)
                        ->get($this->baseUrl . '/expired-instruments/future/contract', [
                            'instrument_key' => $underlyingInstrumentKey,
                            'expiry_date'    => $expiryDate,
                        ]);

        if ($response->failed()) {
            $this->error("API error for {$underlyingInstrumentKey} {$expiryDate}: " . $response->body());
            return [];
        }

        // Upstox expired instruments APIs usually wrap data in a `data` array.[web:1][web:3][web:4]
        return $response->json('data') ?? [];
    }
}
