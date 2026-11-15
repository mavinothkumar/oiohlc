<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class SyncBankNiftyOptionsHistoryCommand extends Command
{
    protected $signature = 'options:sync-banknifty-history 
                            {--from= : Start date (YYYY-MM-DD)} 
                            {--to= : End date (YYYY-MM-DD)} 
                            {--date= : Specific date (YYYY-MM-DD)} 
                            {--expiry= : Target expiry (YYYY-MM-DD)}';

    protected $description = 'Sync historical 5m CE/PE candles with OI for Bank Nifty options from Upstox API into option_chains_history';

    public function handle(): int
    {
        $specificDate = $this->option('date');
        $fromDate = $this->option('from');
        $toDate = $this->option('to');
        $targetExpiry = $this->option('expiry');

        $nowKolkata = Carbon::now('Asia/Kolkata');
        $todayStr = $nowKolkata->toDateString();

        if ($specificDate) {
            $from = $specificDate;
            $to = $specificDate;
        } else {
            $from = $fromDate ?: Carbon::parse($todayStr)->subDays(7)->toDateString();
            $to = $toDate ?: $todayStr;
        }

        // 1. Resolve nearest expiry if not passed
        if (!$targetExpiry) {
            $targetExpiry = DB::table('nse_expiries')
                ->where('trading_symbol', 'BANKNIFTY')
                ->where('instrument_type', 'OPT')
                ->where('expiry_date', '>=', $to)
                ->orderBy('expiry_date')
                ->value('expiry_date');

            if (!$targetExpiry) {
                $targetExpiry = DB::table('option_chains')
                    ->where('trading_symbol', 'BANKNIFTY')
                    ->where('expiry', '>=', $to)
                    ->orderBy('expiry')
                    ->value('expiry');
            }
        }

        if (!$targetExpiry) {
            $this->error("No active Bank Nifty expiry found for range {$from} to {$to}.");
            return 1;
        }

        $this->info("Syncing Bank Nifty options history: range {$from} to {$to}, expiry: {$targetExpiry}");

        // 2. Fetch contracts around ATM (e.g. 58,000 to 64,000)
        $contracts = DB::table('option_chains')
            ->where('trading_symbol', 'BANKNIFTY')
            ->where('expiry', $targetExpiry)
            ->whereBetween('strike_price', [58000, 64000])
            ->select('instrument_key', 'strike_price', 'option_type')
            ->distinct()
            ->get();

        if ($contracts->isEmpty()) {
            $this->warn("No contract instrument keys found in option_chains for {$targetExpiry}. Querying all available...");
            $contracts = DB::table('option_chains')
                ->where('trading_symbol', 'BANKNIFTY')
                ->select('instrument_key', 'strike_price', 'option_type')
                ->distinct()
                ->get();
        }

        if ($contracts->isEmpty()) {
            $this->error("No Bank Nifty option contracts available to sync.");
            return 1;
        }

        $this->info("Found " . $contracts->count() . " active strike contracts to fetch.");

        // 3. Preload index 5m spot candles from ohlc_5m_quotes
        $indexSpots = DB::table('ohlc_5m_quotes')
            ->where(function($q) {
                $q->where('instrument_key', 'like', '%Nifty Bank%')
                  ->orWhere('trading_symbol', 'Nifty Bank')
                  ->orWhere('trading_symbol', 'BANKNIFTY');
            })
            ->where('ts_at', '>=', "{$from} 09:00:00")
            ->where('ts_at', '<=', "{$to} 15:35:00")
            ->pluck('close', 'ts_at')
            ->toArray();

        $token = config('services.upstox.analytics_token');
        $totalInserted = 0;
        $batch = [];

        foreach ($contracts as $cIdx => $contract) {
            $encodedKey = urlencode($contract->instrument_key);
            $url = "https://api.upstox.com/v3/historical-candle/{$encodedKey}/minutes/5/{$to}/{$from}";

            try {
                $response = Http::withHeaders([
                    'Accept' => 'application/json',
                    'Authorization' => "Bearer {$token}",
                ])->timeout(8)->get($url);

                if ($response->successful()) {
                    $candles = $response->json('data.candles') ?? [];
                    foreach ($candles as $candle) {
                        // candle: [timestamp, open, high, low, close, volume, open_interest]
                        $tsStr = substr($candle[0], 0, 19);
                        $tsKolkata = Carbon::parse($tsStr, 'Asia/Kolkata')->format('Y-m-d H:i:s');
                        $candleDate = substr($tsKolkata, 0, 10);

                        if ($candleDate < $from || $candleDate > $to) continue;

                        $open = (float) $candle[1];
                        $high = (float) $candle[2];
                        $low = (float) $candle[3];
                        $close = (float) $candle[4];
                        $vol = (int) $candle[5];
                        $oi = (int) ($candle[6] ?? 0);

                        $spotPrice = $indexSpots[$tsKolkata] 
                            ?? ($indexSpots["{$candleDate} 15:25:00"] ?? null)
                            ?? (!empty($indexSpots) ? end($indexSpots) : 61000.0);

                        $batch[] = [
                            'instrument_key'        => $contract->instrument_key,
                            'underlying_key'        => 'NSE_INDEX|Nifty Bank',
                            'trading_symbol'        => 'BANKNIFTY',
                            'expiry'                => $targetExpiry,
                            'strike_price'          => $contract->strike_price,
                            'option_type'           => $contract->option_type,
                            'ltp'                   => $close,
                            'diff_ltp'              => round($close - $open, 2),
                            'volume'                => $vol,
                            'diff_volume'           => 0,
                            'oi'                    => $oi,
                            'diff_oi'               => 0,
                            'close_price'           => $close,
                            'underlying_spot_price' => $spotPrice,
                            'captured_at'           => $tsKolkata,
                            'created_at'            => $tsKolkata,
                            'updated_at'            => $tsKolkata,
                        ];

                        if (count($batch) >= 500) {
                            DB::table('option_chains_history')->insertOrIgnore($batch);
                            $totalInserted += count($batch);
                            $batch = [];
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Ignore single request failure and continue
            }

            // Sleep 50ms to be kind to rate limit
            usleep(50000);
        }

        if (!empty($batch)) {
            DB::table('option_chains_history')->insertOrIgnore($batch);
            $totalInserted += count($batch);
        }

        $this->info("Completed sync! Inserted {$totalInserted} Bank Nifty options records into option_chains_history.");
        return 0;
    }
}
