<?php

namespace App\Console\Commands;

use App\Services\NiftyConstituentManager;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncNifty50HistoricalOhlcCommand extends Command
{
    protected $signature = 'ohlc:sync-nifty50
                            {--from= : Start date (YYYY-MM-DD)}
                            {--to= : End date (YYYY-MM-DD)}
                            {--date= : Single date to sync (YYYY-MM-DD, e.g. yesterday)}
                            {--chunk-days=25 : Date chunk window in days (max 28)}
                            {--stocks-only : Only sync constituent stocks}
                            {--index-only : Only sync Nifty 50 index}';

    protected $description = 'Sync historical 5-minute OHLC candles for NIFTY 50 and constituent stocks into ohlc_5m_quotes';

    protected string $token = '';

    public function handle(): int
    {
        $this->token = config('services.upstox.analytics_token') ?: config('services.upstox.access_token');
        if (!$this->token) {
            $this->error('Upstox token not configured in services.upstox.');
            return self::FAILURE;
        }

        // Determine date range
        $singleDate = $this->option('date');
        if ($singleDate) {
            $fromDate = Carbon::parse($singleDate)->toDateString();
            $toDate   = Carbon::parse($singleDate)->toDateString();
        } else {
            $yesterday = Carbon::yesterday('Asia/Kolkata')->toDateString();
            $toDate    = $this->option('to') ?: $yesterday;
            // Default --from is 2 years back if not specified
            $fromDate  = $this->option('from') ?: Carbon::parse($toDate)->subYears(2)->toDateString();
        }

        $this->info("==========================================================");
        $this->info(" NIFTY 50 & Constituents Historical 5M OHLC Sync");
        $this->info(" Range: {$fromDate} to {$toDate}");
        $this->info("==========================================================");

        $chunkDays = min(28, max(5, (int)$this->option('chunk-days')));
        $startPeriod = Carbon::parse($fromDate);
        $endPeriod   = Carbon::parse($toDate);

        // Generate date chunks in reverse chronological order (newest to oldest)
        $chunks = [];
        $curEnd = $endPeriod->copy();
        while ($curEnd->gte($startPeriod)) {
            $curStart = $curEnd->copy()->subDays($chunkDays - 1);
            if ($curStart->lt($startPeriod)) {
                $curStart = $startPeriod->copy();
            }
            $chunks[] = [
                'from' => $curStart->toDateString(),
                'to'   => $curEnd->toDateString(),
            ];
            $curEnd = $curStart->copy()->subDay();
        }

        $this->info("Total chunks to process: " . count($chunks) . " (approx {$chunkDays} days each)");

        $syncIndex  = !$this->option('stocks-only');
        $syncStocks = !$this->option('index-only');

        $totalUpsertedIndex = 0;
        $totalUpsertedStocks = 0;
        $startTime = microtime(true);

        foreach ($chunks as $idx => $chunk) {
            $cFrom = $chunk['from'];
            $cTo   = $chunk['to'];
            $chunkNum = $idx + 1;
            $this->line("\n<comment>[Chunk {$chunkNum}/" . count($chunks) . "] Syncing {$cFrom} to {$cTo}...</comment>");

            // 1. Sync NIFTY 50 Index 5m candles
            if ($syncIndex) {
                $indexRows = $this->fetchCandles(
                    'NSE_INDEX|Nifty 50',
                    'NIFTY',
                    'INDEX',
                    $cFrom,
                    $cTo
                );
                if (!empty($indexRows)) {
                    $this->upsertRows($indexRows);
                    $totalUpsertedIndex += count($indexRows);
                    $this->line("  ✓ NIFTY Index: " . count($indexRows) . " candles upserted");
                }
            }

            // 2. Sync all constituent stocks active in this chunk
            if ($syncStocks) {
                // Get all constituents active on the midpoint of this chunk
                $midDate = Carbon::parse($cFrom)->addDays((int)($chunkDays / 2))->toDateString();
                $periodData = NiftyConstituentManager::getConstituentsForDate($midDate);
                $stockMap = $periodData['stock_map'];

                $this->line("  Constituents period: <info>{$periodData['period_name']}</info> (" . count($stockMap) . " stocks)");

                $bar = $this->output->createProgressBar(count($stockMap));
                $bar->start();

                $chunkStockRows = 0;
                foreach ($stockMap as $symbol => $instrumentKey) {
                    $sRows = $this->fetchCandles(
                        $instrumentKey,
                        $symbol,
                        'EQ',
                        $cFrom,
                        $cTo
                    );

                    if (!empty($sRows)) {
                        $this->upsertRows($sRows);
                        $chunkStockRows += count($sRows);
                        $totalUpsertedStocks += count($sRows);
                    }

                    $bar->advance();
                    // Slight delay to stay well within Upstox rate limits (25 req/s)
                    usleep(40000); // 40ms = max ~25 req/s
                }

                $bar->finish();
                $this->newLine();
                $this->line("  ✓ Stocks: {$chunkStockRows} candles upserted for this chunk");
            }
        }

        $elapsed = round(microtime(true) - $startTime, 2);
        $this->newLine();
        $this->info("==========================================================");
        $this->info(" Sync Finished in {$elapsed}s!");
        $this->info(" Total NIFTY Index candles: {$totalUpsertedIndex}");
        $this->info(" Total Stock candles:       {$totalUpsertedStocks}");
        $this->info("==========================================================");

        return self::SUCCESS;
    }

    /**
     * Fetch 5-minute candles from Upstox Historical API
     */
    protected function fetchCandles(string $instrumentKey, string $symbol, string $type, string $fromDate, string $toDate): array
    {
        $encodedKey = rawurlencode($instrumentKey);
        $url = "https://api.upstox.com/v3/historical-candle/{$encodedKey}/minutes/5/{$toDate}/{$fromDate}";

        try {
            $resp = Http::withHeaders([
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $this->token,
            ])->timeout(12)->get($url);

            if ($resp->status() === 429) {
                // Rate limited: wait 1s and retry once
                sleep(1);
                $resp = Http::withHeaders([
                    'Accept'        => 'application/json',
                    'Authorization' => 'Bearer ' . $this->token,
                ])->timeout(12)->get($url);
            }

            if (!$resp->ok()) {
                if ($resp->status() !== 404) {
                    Log::warning("Upstox historical 5m error for {$symbol} ({$fromDate} to {$toDate}): HTTP " . $resp->status());
                }
                return [];
            }

            $rawCandles = $resp->json('data.candles') ?? [];
            if (empty($rawCandles)) {
                return [];
            }

            $rows = [];
            $now = now();

            foreach ($rawCandles as $c) {
                // $c = [ts_iso, open, high, low, close, volume, oi]
                $ts = Carbon::parse($c[0])->setTimezone('Asia/Kolkata');
                $timeStr = $ts->format('H:i');

                // Keep strictly within regular NSE trading session (09:15 to 15:30)
                if ($timeStr < '09:15' || $timeStr > '15:25') {
                    continue;
                }

                $close = round((float)$c[4], 2);
                $rows[] = [
                    'instrument_key'  => $instrumentKey,
                    'instrument_type' => $type,
                    'trading_symbol'  => $symbol,
                    'expiry_date'     => null,
                    'strike_price'    => 0,
                    'open'            => round((float)$c[1], 2),
                    'high'            => round((float)$c[2], 2),
                    'low'             => round((float)$c[3], 2),
                    'close'           => $close,
                    'volume'          => (int)($c[5] ?? 0),
                    'ts'              => $ts->timestamp * 1000,
                    'ts_at'           => $ts->format('Y-m-d H:i:00'),
                    'last_price'      => $close,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
            }

            return $rows;
        } catch (\Throwable $e) {
            Log::error("Exception fetching 5m candles for {$symbol}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Upsert rows into ohlc_5m_quotes table in chunks of 500
     */
    protected function upsertRows(array $rows): void
    {
        if (empty($rows)) return;

        foreach (array_chunk($rows, 500) as $batch) {
            DB::table('ohlc_5m_quotes')->upsert(
                $batch,
                ['instrument_key', 'ts'],
                ['open', 'high', 'low', 'close', 'volume', 'last_price', 'ts_at', 'updated_at']
            );
        }
    }
}
