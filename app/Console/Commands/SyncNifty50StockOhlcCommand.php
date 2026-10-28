<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncNifty50StockOhlcCommand extends Command
{
    protected $signature = 'ohlc:sync-nifty50-stocks {--date= : The date to sync (default: today)}';
    protected $description = 'Sync 1-minute intraday OHLC candles for all 50 NIFTY constituent stocks from Upstox';

    public function handle(): int
    {
        $date = $this->option('date') ?: today()->toDateString();
        $this->info("Starting 1-min OHLC sync for 50 NIFTY stocks on {$date}...");

        $token = config('services.upstox.analytics_token');
        if (!$token) {
            $this->error('Upstox analytics token not configured in services.upstox.analytics_token');
            return self::FAILURE;
        }

        $stockMap = CollectOneMinOhlcCommand::$nifty50EquityStocks;
        $totalUpserted = 0;
        $now = now();
        $startTime = microtime(true);

        $bar = $this->output->createProgressBar(count($stockMap));
        $bar->start();

        foreach ($stockMap as $symbol => $instrumentKey) {
            try {
                $encodedKey = rawurlencode($instrumentKey);
                $resp = Http::withHeaders([
                    'Accept'        => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                ])->timeout(8)->get("https://api.upstox.com/v2/historical-candle/intraday/{$encodedKey}/1minute");

                if ($resp->ok()) {
                    $candles = $resp->json('data.candles') ?? [];
                    $rows = [];
                    foreach ($candles as $c) {
                        $tsAt = Carbon::parse($c[0])->setTimezone(config('app.timezone', 'Asia/Kolkata'))->second(0);
                        if ($tsAt->toDateString() !== $date) continue;

                        $rows[] = [
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

                    if (!empty($rows)) {
                        DB::table('ohlc_quotes')->upsert(
                            $rows,
                            ['instrument_key', 'ts'],
                            ['open', 'high', 'low', 'close', 'volume', 'last_price', 'ts_at', 'updated_at']
                        );
                        $totalUpserted += count($rows);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Failed to sync 1m candles for {$symbol}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $elapsed = round(microtime(true) - $startTime, 2);
        $this->info("Successfully synced {$totalUpserted} rows for 50 Nifty stocks in {$elapsed}s!");

        return self::SUCCESS;
    }
}
