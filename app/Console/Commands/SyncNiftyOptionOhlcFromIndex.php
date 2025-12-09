<?php

namespace App\Console\Commands;

use App\Services\BacktestIndexService;
use App\Services\UpstoxExpiredService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncNiftyOptionOhlcFromIndex extends Command
{
    protected $signature = 'upstox:sync-nifty-option-ohlc
                            {from : From working date YYYY-MM-DD}
                            {to   : To working date YYYY-MM-DD}
                            {--expiry= : Optional explicit expiry date YYYY-MM-DD}';

    protected $description = 'Sync NIFTY option OHLC (day & 5m) based on previous index close and strike range';

    public function handle(
        BacktestIndexService $indexService,
        UpstoxExpiredService $expiredService
    ): int {
        $from      = $this->argument('from');
        $to        = $this->argument('to');
        $expiryOpt = $this->option('expiry');

        // All working days in given range
        $workingDays = DB::table('nse_working_days')
                         ->whereBetween('working_date', [$from, $to])
                         ->orderBy('working_date')
                         ->pluck('working_date')
                         ->all();

        if (empty($workingDays)) {
            $this->warn('No working days in given range.');
            return self::FAILURE;
        }

        foreach ($workingDays as $workingDate) {
            $this->info("Processing working date {$workingDate}...");

            // 1) Previous working date
            $prevDate = $indexService->getPreviousWorkingDate($workingDate);
            if (! $prevDate) {
                $this->warn("  No previous working date for {$workingDate}, skipping.");
                continue;
            }

            // 2) Previous NIFTY close from expired_ohlc (INDEX, day)
            $close = $indexService->getNiftyCloseForDate($prevDate);
            if ($close === null) {
                $this->warn("  No NIFTY close found for {$prevDate}, skipping {$workingDate}.");
                continue;
            }

            // 3) Generate strikes ±500 around rounded 50
            $strikes = $indexService->generateNiftyStrikes($close);
            $this->line("  Prev close {$prevDate} = {$close}, strikes from "
                        . reset($strikes) . ' to ' . end($strikes));

            // 4) Resolve expiry: CLI option or current expiry for working date
            $expiryDate = $expiryOpt ?: $indexService->getCurrentExpiryForDate($workingDate);
            if (! $expiryDate) {
                $this->warn("  No expiry found for {$workingDate}, skipping.");
                continue;
            }

            $this->line("  Using expiry {$expiryDate}.");

            // 5) Get CE & PE contracts for those strikes and expiry
            $contracts = $indexService->getNiftyOptionContractsForStrikes($expiryDate, $strikes);

            if (empty($contracts)) {
                $this->warn("  No option contracts for expiry {$expiryDate} and strikes, skipping.");
                continue;
            }

            // 6) For each contract, fetch day + 5minute expired candles
            foreach ($contracts as $contract) {
                $instrumentKey = $contract->instrument_key;
                $this->line("   -> {$instrumentKey} ({$contract->instrument_type} {$contract->strike_price})");

                // Choose your historical range for this contract:
                // here: just this workingDate for both from/to.
                $fromDate = $workingDate;
                $toDate   = $workingDate;

                // 6a) Day candles (no chunking needed)
                $dayCandles = $expiredService->getExpiredHistoricalCandles(
                    $instrumentKey,
                    'day',
                    $fromDate,
                    $toDate
                );
                $this->storeOptionCandles($dayCandles, $contract, 'day');

                // 6b) 5-minute candles in chunks to avoid UDAPI1148
                $chunkDays = 5;
                $start     = Carbon::parse($fromDate);
                $end       = Carbon::parse($toDate);
                $period    = new CarbonPeriod($start, $chunkDays . ' days', $end);

                foreach ($period as $chunkStart) {
                    $chunkEnd = $chunkStart->copy()->addDays($chunkDays - 1);
                    if ($chunkEnd->gt($end)) {
                        $chunkEnd = $end;
                    }

                    $fromChunk = $chunkStart->format('Y-m-d');
                    $toChunk   = $chunkEnd->format('Y-m-d');

                    $this->line("      5m {$fromChunk} → {$toChunk}");

                    $fiveMinCandles = $expiredService->getExpiredHistoricalCandles(
                        $instrumentKey,
                        '5minute',
                        $fromChunk,
                        $toChunk
                    );

                    $this->storeOptionCandles($fiveMinCandles, $contract, '5minute');
                    usleep(400_000);
                }
            }
        }

        $this->info('Finished syncing NIFTY option OHLC.');
        return self::SUCCESS;
    }

    /**
     * Store option candles (day or 5m) into expired_ohlc.
     *
     * @param array $candles [ [ts, o, h, l, c, v, oi], ... ] or associative
     */
    protected function storeOptionCandles(
        array $candles,
        $contract,
        string $interval
    ): void {
        if (empty($candles)) {
            return;
        }

        $rows = [];

        foreach ($candles as $candle) {
            // Handle both numeric-indexed and associative formats
            $ts     = $candle[0]       ?? $candle['timestamp']     ?? null;
            $open   = $candle[1]       ?? $candle['open']          ?? null;
            $high   = $candle[2]       ?? $candle['high']          ?? null;
            $low    = $candle[3]       ?? $candle['low']           ?? null;
            $close  = $candle[4]       ?? $candle['close']         ?? null;
            $volume = $candle[5]       ?? $candle['volume']        ?? null;
            $oi     = $candle[6]       ?? $candle['open_interest'] ?? null;

            if ($ts === null) {
                continue;
            }

            if (is_numeric($ts)) {
                $timestamp = gmdate('Y-m-d H:i:s', (int) ($ts / 1000));
            } else {
                $timestamp = date('Y-m-d H:i:s', strtotime($ts));
            }

            $rows[] = [
                'underlying_symbol' => $contract->underlying_symbol, // NIFTY
                'exchange'          => $contract->exchange,          // NSE
                'expiry'            => $contract->expiry,
                'instrument_key'    => $contract->instrument_key,
                'instrument_type'   => $contract->instrument_type,   // CE / PE
                'strike'            => $contract->strike_price,
                'open'              => $open,
                'high'              => $high,
                'low'               => $low,
                'close'             => $close,
                'interval'          => $interval,
                'volume'            => $volume,
                'open_interest'     => $oi,
                'timestamp'         => $timestamp,
                'created_at'        => now(),
                'updated_at'        => now(),
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('expired_ohlc')->upsert(
                $chunk,
                ['instrument_key', 'interval', 'timestamp'],
                [
                    'open',
                    'high',
                    'low',
                    'close',
                    'volume',
                    'open_interest',
                    'expiry',
                    'strike',
                    'updated_at',
                ]
            );
        }
    }
}
