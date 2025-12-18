<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ExpiredExpiry;
use Illuminate\Support\Facades\DB;

class CreateFutureExpiries extends Command
{
    protected $signature = 'expired-expiries:create-fut';
    protected $description = 'Create FUT records based on last expiry of each month without overriding OPT';

    public function handle(): int
    {
        // Get last expiry per month per underlying
        $groups = ExpiredExpiry::query()
                               ->select([
                                   'underlying_instrument_key',
                                   DB::raw('DATE_FORMAT(expiry_date, "%Y-%m") as ym'),
                                   DB::raw('MAX(expiry_date) as last_expiry'),
                               ])
                               ->groupBy('underlying_instrument_key', 'ym')
                               ->get();

        foreach ($groups as $group) {
            // Fetch the original OPT row for that last_expiry
            $optRow = ExpiredExpiry::query()
                                   ->where('underlying_instrument_key', $group->underlying_instrument_key)
                                   ->whereDate('expiry_date', $group->last_expiry)
                                   ->where('instrument_type', 'OPT')
                                   ->first();

            if (! $optRow) {
                continue;
            }

            // Check if FUT already exists for this key+expiry
            $existsFut = ExpiredExpiry::query()
                                      ->where('underlying_instrument_key', $optRow->underlying_instrument_key)
                                      ->whereDate('expiry_date', $optRow->expiry_date)
                                      ->where('instrument_type', 'FUT')
                                      ->exists();

            if ($existsFut) {
                continue;
            }

            // Create FUT record (duplicate with instrument_type = FUT)
            ExpiredExpiry::create([
                'underlying_instrument_key' => $optRow->underlying_instrument_key,
                'underlying_symbol'         => $optRow->underlying_symbol,
                'instrument_type'           => 'FUT',
                'expiry_date'               => $optRow->expiry_date,
            ]);
        }

        $this->info('FUT records created for last expiry of each month.');

        return self::SUCCESS;
    }
}
