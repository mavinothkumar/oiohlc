<?php

namespace App\Console\Commands;

use App\Models\ExpiredOhlc;
use App\Services\UpstoxHistoryService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncNiftyIndexOhlc extends Command
{
    protected $signature = 'upstox:sync-nifty-index-ohlc
                            {from : From date YYYY-MM-DD}
                            {to   : To date YYYY-MM-DD}';

    protected $description = 'Sync NIFTY 50 index daily and 5-minute OHLC into expired_ohlc';

    public function handle(UpstoxHistoryService $service): int
    {
        $from = $this->argument('from');
        $to   = $this->argument('to');

        $instrumentKey    = 'NSE_INDEX|Nifty 50';
        $underlyingSymbol = 'NIFTY';
        $exchange         = 'NSE';

        // 1) Daily candles (unit = days, interval = 1)
        $this->info("Fetching daily candles {$from} to {$to}...");
        $dailyCandles = $service->getHistoricalCandles(
            $instrumentKey,
            'days',
            1,
            $from,
            $to
        );

        $this->storeCandles(
            $dailyCandles,
            $underlyingSymbol,
            $exchange,
            $instrumentKey,
            'INDEX',
            'day'
        );

        // 2) 5-minute candles (unit = minutes, interval = 5) in chunks
        $this->info("Fetching 5-minute candles {$from} to {$to} in chunks...");

        $chunkDays = 5; // keep within Upstox minutes date-range limits
        $start = Carbon::parse($from);
        $end   = Carbon::parse($to);

        $period = new CarbonPeriod($start, $chunkDays . ' days', $end);

        foreach ($period as $chunkStart) {
            $chunkEnd = $chunkStart->copy()->addDays($chunkDays - 1);
            if ($chunkEnd->gt($end)) {
                $chunkEnd = $end;
            }

            $fromDateChunk = $chunkStart->format('Y-m-d');
            $toDateChunk   = $chunkEnd->format('Y-m-d');

            $this->info("Fetching 5-minute candles {$fromDateChunk} to {$toDateChunk}...");

            $fiveMinCandles = $service->getHistoricalCandles(
                $instrumentKey,
                'minutes',
                5,
                $fromDateChunk,
                $toDateChunk
            );

            $this->storeCandles(
                $fiveMinCandles,
                $underlyingSymbol,
                $exchange,
                $instrumentKey,
                'INDEX',
                '5minute'
            );
        }

        $this->info('Done syncing NIFTY index OHLC.');
        return self::SUCCESS;
    }

    /**
     * Store index candles into expired_ohlc.
     *
     * @param array  $candles [ [ts, o, h, l, c, v, oi], ... ] or associative
     */
    protected function storeCandles(
        array $candles,
        string $underlyingSymbol,
        string $exchange,
        string $instrumentKey,
        string $instrumentType,
        string $interval
    ): void {
        if (empty($candles)) {
            $this->warn("No candles to store for interval {$interval}.");
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

            // Convert timestamp (epoch ms or ISO) to Y-m-d H:i:s
            if (is_numeric($ts)) {
                $timestamp = gmdate('Y-m-d H:i:s', (int) ($ts / 1000));
            } else {
                $timestamp = date('Y-m-d H:i:s', strtotime($ts));
            }

            $rows[] = [
                'underlying_symbol' => $underlyingSymbol,
                'exchange'          => $exchange,
                'expiry'            => null,              // index has no expiry
                'instrument_key'    => $instrumentKey,
                'instrument_type'   => $instrumentType,   // 'INDEX'
                'strike'            => null,              // only used for options
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
                ['open', 'high', 'low', 'close', 'volume', 'open_interest', 'expiry', 'strike', 'updated_at']
            );
        }

        $this->info('Stored ' . count($candles) . " candles for interval {$interval}.");
    }
}
