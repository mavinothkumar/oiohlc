<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncNifty50StockOhlcCommand extends Command
{
    protected $signature = 'ohlc:sync-nifty50-stocks {--date= : The date to sync (default: today)} {--full : Sync full day history instead of latest 3 candles}';
    protected $description = 'Sync 1-minute intraday OHLC candles for all 50 NIFTY constituent stocks from Upstox via high-speed concurrent pool';

    public function handle(): int
    {
        $date = $this->option('date') ?: today()->toDateString();
        $isFull = $this->option('full');
        $modeStr = $isFull ? 'FULL HISTORY' : 'FAST LIVE (latest 3m)';
        $this->info("Starting 1-min OHLC sync ({$modeStr}) for 50 NIFTY stocks on {$date}...");

        $token = config('services.upstox.analytics_token');
        if (!$token) {
            $this->error('Upstox analytics token not configured in services.upstox.analytics_token');
            return self::FAILURE;
        }

        $stockMap = CollectOneMinOhlcCommand::$nifty50EquityStocks;
        $totalUpserted = 0;
        $now = now();
        $startTime = microtime(true);
        $allRows = [];

        try {
            // Concurrent HTTP pool: fetches all 57 stocks in parallel in ~1 second
            $responses = Http::pool(function ($pool) use ($stockMap, $token) {
                foreach ($stockMap as $symbol => $instrumentKey) {
                    $encodedKey = rawurlencode($instrumentKey);
                    $pool->as($symbol)->withHeaders([
                        'Accept'        => 'application/json',
                        'Authorization' => 'Bearer ' . $token,
                    ])->timeout(6)->get("https://api.upstox.com/v2/historical-candle/intraday/{$encodedKey}/1minute");
                }
            });

            foreach ($stockMap as $symbol => $instrumentKey) {
                $resp = $responses[$symbol] ?? null;
                if ($resp instanceof Response && $resp->ok()) {
                    $candles = $resp->json('data.candles') ?? [];
                    if (empty($candles)) continue;

                    // In live 1-min mode, only take latest 3 candles (Upstox returns descending order)
                    // to prevent heavy database writes every minute.
                    if (!$isFull && count($candles) > 3) {
                        $candles = array_slice($candles, 0, 3);
                    }

                    foreach ($candles as $c) {
                        $tsAt = Carbon::parse($c[0])->setTimezone(config('app.timezone', 'Asia/Kolkata'))->second(0);
                        if ($tsAt->toDateString() !== $date) continue;

                        $allRows[] = [
                            'instrument_key'  => $instrumentKey,
                            'instrument_type' => 'EQ',
                            'trading_symbol'  => $symbol,
                            'expiry_date'     => null,
                            'strike_price'    => 0,
                            'open'            => (float) $c[1],
                            'high'            => (float) $c[2],
                            'low'             => (float) $c[3],
                            'close'           => (float) $c[4],
                            'volume'          => (int) ($c[5] ?? 0),
                            'ts'              => $tsAt->timestamp,
                            'ts_at'           => $tsAt->toDateTimeString(),
                            'last_price'      => (float) $c[4],
                            'created_at'      => $now,
                            'updated_at'      => $now,
                        ];
                    }
                } else {
                    $status = $resp instanceof Response ? $resp->status() : 'exception';
                    Log::warning("Failed to fetch 1m candle for {$symbol}: HTTP {$status}");
                }
            }
        } catch (\Throwable $e) {
            Log::error("Concurrent pool error during 1m stock sync: " . $e->getMessage());
        }

        // Single batch upsert into database in chunks of 500 rows
        if (!empty($allRows)) {
            foreach (array_chunk($allRows, 500) as $rowChunk) {
                try {
                    retry(3, function () use ($rowChunk) {
                        DB::table('ohlc_quotes')->upsert(
                            $rowChunk,
                            ['instrument_key', 'ts'],
                            ['open', 'high', 'low', 'close', 'volume', 'last_price', 'ts_at', 'updated_at']
                        );
                    }, 200);
                    $totalUpserted += count($rowChunk);
                } catch (\Throwable $e) {
                    Log::error("Failed to upsert 1m stock quotes chunk: " . $e->getMessage());
                }
            }
        }

        $elapsed = round(microtime(true) - $startTime, 2);
        $this->info("Successfully synced {$totalUpserted} rows for " . count($stockMap) . " Nifty stocks in {$elapsed}s!");

        return self::SUCCESS;
    }
}
