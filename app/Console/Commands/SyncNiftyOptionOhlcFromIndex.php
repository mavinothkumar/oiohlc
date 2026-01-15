<?php

namespace App\Console\Commands;

use App\Services\BacktestIndexService;
use App\Services\UpstoxExpiredService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncNiftyOptionOhlcFromIndex extends Command
{
    protected $signature = 'upstox:sync-nifty-option-ohlc
                            {from : From working date YYYY-MM-DD}
                            {to   : To working date YYYY-MM-DD}
                            {--expiry= : Optional explicit expiry date YYYY-MM-DD}
                            {--strikes= : Strikes comma separated}
                            {--no-5m : Skip 5-minute candles}
                            {--no-3m : Skip 3-minute candles}
                            {--no-day : Skip day candles}
                            ';

    protected $description = 'Sync NIFTY option OHLC (day & optionally 5m) - OPTIMIZED';

    public function handle(
        BacktestIndexService $indexService,
        UpstoxExpiredService $expiredService
    ): int {
        $from          = $this->argument('from');
        $to            = $this->argument('to');
        $expiryOpt     = $this->option('expiry');
        $skip5m        = $this->option('no-5m');
        $skip3m        = $this->option('no-3m');
        $skipday       = $this->option('no-day');
        $manualStrikes = $this->option('strikes');

        // Validate working days exist
        $workingDays = DB::table('nse_working_days')
                         ->whereBetween('working_date', [$from, $to])
                         ->pluck('working_date')
                         ->all();

        if (empty($workingDays)) {
            $this->warn('No working days in given range.');

            return self::FAILURE;
        }

        // Single expiry for entire range
        $firstWorkingDate = $workingDays[0];
        $expiryDate       = $expiryOpt ?: $indexService->getCurrentExpiryForDate($firstWorkingDate);
        if ( ! $expiryDate) {
            $this->warn("No expiry found for {$firstWorkingDate}");

            return self::FAILURE;
        }

        $this->info("Using expiry {$expiryDate} for {$from} → {$to}");

        // Single NIFTY close from first day's previous
        $prevDate = $indexService->getPreviousWorkingDate($firstWorkingDate);
        $close    = $indexService->getNiftyCloseForDate($prevDate);
        if ($close === null) {
            $this->warn("No NIFTY close for {$prevDate}");

            return self::FAILURE;
        }

        // Generate strikes once
        $strikes = $manualStrikes
            ? explode(',', $manualStrikes)
            : $indexService->generateNiftyStrikes($close);

        $this->line("NIFTY close {$close}, strikes: ".count($strikes)." pcs");

        // Get all contracts once
        $contracts = $indexService->getNiftyOptionContractsForStrikes($expiryDate, $strikes);
        if (empty($contracts)) {
            $this->warn("No contracts found");

            return self::FAILURE;
        }

        $this->info("Processing ".count($contracts)." contracts...");

        // **OPTIMIZED: 1 API call per instrument per interval for FULL date range**
        foreach ($contracts as $contract) {
            $instrumentKey = $contract->instrument_key;
            $this->line("  -> {$instrumentKey} ({$contract->instrument_type} {$contract->strike_price})");

            if ( ! $skipday) {
                // Day candles - SINGLE call for entire range
                $dayCandles = $expiredService->getExpiredHistoricalCandles(
                    $instrumentKey, 'day', $from, $to
                );
                $this->storeOptionCandles($dayCandles, $contract, 'day');
            }
            // 5m candles - SINGLE call for entire range (if enabled)
            if ( ! $skip5m) {
                $fiveMinCandles = $expiredService->getExpiredHistoricalCandles(
                    $instrumentKey, '5minute', $from, $to
                );
                $this->storeOptionCandles($fiveMinCandles, $contract, '5minute');
            }
            if ( ! $skip3m) {
                $fiveMinCandles = $expiredService->getExpiredHistoricalCandles(
                    $instrumentKey, '3minute', $from, $to
                );
                $this->storeOptionCandles($fiveMinCandles, $contract, '3minute');
            }
        }

        $this->info('✅ Finished syncing NIFTY option OHLC.');

        return self::SUCCESS;
    }

    protected function storeOptionCandles(array $candles, $contract, string $interval): void
    {
        if (empty($candles)) {
            return;
        }

        $rows = [];
        foreach ($candles as $candle) {
            $ts = $candle[0] ?? $candle['timestamp'] ?? null;
            if ( ! $ts) {
                continue;
            }

            $timestamp = is_numeric($ts)
                ? gmdate('Y-m-d H:i:s', (int) ($ts / 1000))
                : date('Y-m-d H:i:s', strtotime($ts));

            $rows[] = [
                'underlying_symbol' => $contract->underlying_symbol,
                'exchange'          => $contract->exchange,
                'expiry'            => $contract->expiry,
                'instrument_key'    => $contract->instrument_key,
                'instrument_type'   => $contract->instrument_type,
                'strike'            => $contract->strike_price,
                'open'              => $candle[1] ?? $candle['open'] ?? null,
                'high'              => $candle[2] ?? $candle['high'] ?? null,
                'low'               => $candle[3] ?? $candle['low'] ?? null,
                'close'             => $candle[4] ?? $candle['close'] ?? null,
                'volume'            => $candle[5] ?? $candle['volume'] ?? null,
                'open_interest'     => $candle[6] ?? $candle['open_interest'] ?? null,
                'interval'          => $interval,
                'timestamp'         => $timestamp,
                'created_at'        => now(),
                'updated_at'        => now(),
            ];
        }

        // Bulk upsert in chunks
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('expired_ohlc')->upsert(
                $chunk,
                ['instrument_key', 'interval', 'timestamp'],
                ['open', 'high', 'low', 'close', 'volume', 'open_interest', 'expiry', 'strike', 'updated_at']
            );
        }
    }
}
