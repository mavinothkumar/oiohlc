<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RegenerateFiveMinuteCandles extends Command
{
    protected $signature   = 'ohlc:regenerate-5min {--date= : Date in Y-m-d format, defaults to today}';
    protected $description = 'Regenerate 5-minute OHLC candles for a given date';

    public function handle(): int
    {
        $dateInput = $this->option('date');

        $date = $dateInput
            ? Carbon::createFromFormat('Y-m-d', $dateInput)->startOfDay()
            : Carbon::today();

        $this->info("Regenerating 5-min candles for: {$date->toDateString()}");

        $now            = now()->copy()->second(0);
        $currentBucket  = $now->copy()->minute(intdiv((int) $now->format('i'), 5) * 5)->second(0);
        $completedBucket = $currentBucket->copy()->subMinutes(5);

        $marketOpen  = $date->copy()->setTime(9, 15);
        $marketClose = $date->copy()->setTime(15, 25);

        // For today, end at last completed bucket; for past dates, run full day
        $endBucket = $date->isToday() ? $completedBucket : $marketClose;

        // Guard: first candle (09:15–09:19) only completable after 09:20
        if ($date->isToday() && $now->lt($date->copy()->setTime(9, 20))) {
            $this->warn('Market has not completed the first 5-min candle yet (before 09:20).');
            return self::FAILURE;
        }

        $underlyings = [
            ['symbol' => 'NIFTY', 'exchange' => 'NSE'],
            // ['symbol' => 'BANKNIFTY', 'exchange' => 'NSE'],
            // ['symbol' => 'FINNIFTY',  'exchange' => 'NSE'],
            // ['symbol' => 'SENSEX',    'exchange' => 'BSE'],
        ];

        foreach ($underlyings as $inst) {
            $this->processUnderlying($inst, $date, $marketOpen, $endBucket, $now);
        }

        $this->info('Done.');
        return self::SUCCESS;
    }

    private function processUnderlying(array $inst, Carbon $date, Carbon $marketOpen, Carbon $endBucket, Carbon $now): void
    {
        $optExpiry = DB::table('nse_expiries')
                       ->where('trading_symbol', $inst['symbol'])
                       ->where('is_current', 1)
                       ->where('instrument_type', 'OPT')
                       ->first();

        $futExpiry = DB::table('nse_expiries')
                       ->where('trading_symbol', $inst['symbol'])
                       ->where('is_current', 1)
                       ->where('instrument_type', 'FUT')
                       ->first();

        $instrumentRows = collect();

        if ($optExpiry) {
            $instrumentRows = $instrumentRows->merge(
                DB::table('instruments')
                  ->where('underlying_symbol', $inst['symbol'])
                  ->where('expiry', $optExpiry->expiry)
                  ->whereIn('instrument_type', ['CE', 'PE'])
                  ->get(['instrument_key', 'instrument_type', 'strike_price'])
            );
        }

        if ($futExpiry) {
            $instrumentRows = $instrumentRows->merge(
                DB::table('instruments')
                  ->where('underlying_symbol', $inst['symbol'])
                  ->where('expiry', $futExpiry->expiry)
                  ->where('instrument_type', 'FUT')
                  ->get(['instrument_key', 'instrument_type', 'strike_price'])
            );
        }

        if ($instrumentRows->isEmpty()) {
            $this->warn("No instruments found for {$inst['symbol']}");
            return;
        }

        // Always start from 09:15 — ignore lastTimestamp for full regeneration
        $startBucket = $marketOpen->copy();

        $bucketCount = 0;

        for ($bucket = $startBucket->copy(); $bucket->lte($endBucket); $bucket->addMinutes(5)) {
            $windowStart = $bucket->copy();
            $windowEnd   = $bucket->copy()->addMinutes(4)->endOfMinute();

            // Get the latest option chain snapshot within this 5-min window
            $chainCapturedAt = DB::table('option_chains')
                                 ->where('trading_symbol', $inst['symbol'])
                                 ->whereBetween('captured_at', [$windowStart, $windowEnd])
                                 ->max('captured_at');

            $chainMap = collect();
            if ($chainCapturedAt) {
                $chainMap = DB::table('option_chains')
                              ->where('trading_symbol', $inst['symbol'])
                              ->where('captured_at', $chainCapturedAt)
                              ->get([
                                  'strike_price',
                                  'option_type',
                                  'oi',
                                  'volume',
                                  'diff_oi',
                                  'diff_volume',
                                  'diff_ltp',
                                  'build_up',
                              ])
                              ->keyBy(fn($row) => number_format((float) $row->strike_price, 2, '.', '') . '|' . $row->option_type);
            }

            $rows = [];

            foreach ($instrumentRows as $instrument) {
                $candles = DB::table('ohlc_quotes')
                             ->where('instrument_key', $instrument->instrument_key)
                             ->whereBetween('ts_at', [$windowStart, $windowEnd])
                             ->orderBy('ts_at')
                             ->get();

                if ($candles->isEmpty()) {
                    continue;
                }

                $chain = null;
                if (in_array($instrument->instrument_type, ['CE', 'PE'], true)) {
                    $chainKey = number_format((float) $instrument->strike_price, 2, '.', '') . '|' . $instrument->instrument_type;
                    $chain    = $chainMap->get($chainKey);
                }

                $rows[] = [
                    'instrument_key'    => $instrument->instrument_key,
                    'underlying_symbol' => $inst['symbol'],
                    'expiry_date'       => in_array($instrument->instrument_type, ['CE', 'PE'], true)
                        ? ($optExpiry?->expiry_date ?? null)
                        : ($futExpiry?->expiry_date ?? null),
                    'strike'            => $instrument->strike_price,
                    'instrument_type'   => $instrument->instrument_type,
                    'open'              => $candles->first()->open,
                    'high'              => $candles->max('high'),
                    'low'               => $candles->min('low'),
                    'close'             => $candles->last()->close,
                    'oi'                => $chain?->oi,
                    'volume'            => $chain?->volume,
                    'diff_oi'           => $chain?->diff_oi,
                    'diff_volume'       => $chain?->diff_volume,
                    'diff_ltp'          => $chain?->diff_ltp,
                    'build_up'          => $chain?->build_up,
                    'exchange'          => $inst['exchange'],
                    'interval'          => '5minute',
                    'timestamp'         => $bucket->copy(),
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];
            }

            if (!empty($rows)) {
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('ohlc_live_snapshots')->upsert(
                        $chunk,
                        ['instrument_key', 'timestamp'],
                        ['open', 'high', 'low', 'close', 'oi', 'volume', 'build_up', 'diff_oi', 'diff_volume', 'diff_ltp', 'updated_at']
                    );
                }
                $bucketCount++;
                $this->line("  ✓ {$bucket->toTimeString()} — {$inst['symbol']} — " . count($rows) . " rows upserted");
            } else {
                $this->warn("  ✗ {$bucket->toTimeString()} — no 1-min candle data found in ohlc_quotes");
            }
        }

        $this->info("Completed {$inst['symbol']}: {$bucketCount} buckets processed.");
        Log::info("5-min regeneration done for {$inst['symbol']} on {$endBucket->toDateString()} | buckets: {$bucketCount}");
    }
}
