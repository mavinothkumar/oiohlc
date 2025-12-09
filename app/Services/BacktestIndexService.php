<?php

// app/Services/BacktestIndexService.php
namespace App\Services;

use App\Models\ExpiredOhlc;
use App\Models\ExpiredExpiry;
use App\Models\ExpiredOptionContract;
use Illuminate\Support\Facades\DB;

class BacktestIndexService
{
    public function getPreviousWorkingDate(string $date): ?string
    {
        return DB::table('nse_working_days')
                 ->where('working_date', '<', $date)
                 ->orderByDesc('working_date')
                 ->value('working_date');
    }

    public function getNiftyCloseForDate(string $date): ?float
    {
        return ExpiredOhlc::query()
                          ->where('underlying_symbol', 'NIFTY')
                          ->where('instrument_type', 'INDEX')
                          ->where('interval', 'day')
                          ->whereDate('timestamp', $date)
                          ->orderByDesc('timestamp')
                          ->value('close');
    }

    public function generateNiftyStrikes(float $close): array
    {
        // Round to nearest 50
        $atm = round($close / 50) * 50;

        $strikes = [];

        // Buffer +/- 500 or more as needed
        for ($strike = $atm - 500; $strike <= $atm + 500; $strike += 50) {
            $strikes[] = (int) $strike;
        }

        return $strikes;
    }

    public function getCurrentExpiryForDate(string $workingDate): ?string
    {
        // Current expiry = first expiry >= workingDate for NIFTY
        return ExpiredExpiry::query()
                            ->where('underlying_symbol', 'NIFTY')
                            ->whereDate('expiry_date', '>=', $workingDate)
                            ->orderBy('expiry_date')
                            ->value('expiry_date');
    }

    public function getNiftyOptionContractsForStrikes(
        string $expiryDate,
        array $strikes
    ): array {
        return ExpiredOptionContract::query()
                                    ->where('underlying_symbol', 'NIFTY')
                                    ->whereDate('expiry', $expiryDate)
                                    ->whereIn('instrument_type', ['CE', 'PE'])
                                    ->whereIn('strike_price', $strikes)
                                    ->get()
                                    ->all();
    }
}
