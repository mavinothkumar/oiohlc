<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;
use Carbon\Carbon;

class CollectOhlcForIndices extends Command
{
    protected $signature = 'indices:collect-daily-ohlc {--date= : The date to collect OHLC for (defaults to today if after 15:30 or previous working day)}';
    protected $description = 'Collect daily OHLC for all instruments of NIFTY, BANKNIFTY, and SENSEX options';

    public $index_instruments = [
        'NSE_INDEX|Nifty 50',
    ];
    public $indices = ['NIFTY'];
    public $quoteDate;
    public $workingDay;
    public $currentDay;

    public function handle()
    {
        $this->currentDay = DB::table('nse_working_days')->where('current', 1)->first();
        $this->workingDay = DB::table('nse_working_days')->where('previous', 1)->first();

        // Determine target date to collect
        $targetDate = $this->option('date');
        if (!$targetDate) {
            $now = Carbon::now('Asia/Kolkata');
            // If evening after market close (>= 15:30), default to current working day (today)
            if ($now->format('H:i') >= '15:30' && $this->currentDay) {
                $targetDate = $this->currentDay->working_date;
            } elseif ($this->workingDay) {
                $targetDate = $this->workingDay->working_date;
            } else {
                $targetDate = Carbon::yesterday()->toDateString();
            }
        }

        // Determine the previous trading date relative to targetDate
        $prevWorkingDate = DB::table('nse_working_days')
            ->where('working_date', '<', $targetDate)
            ->orderByDesc('working_date')
            ->value('working_date');

        if (!$prevWorkingDate) {
            $prevWorkingDate = Carbon::parse($targetDate)->subDay()->toDateString();
        }

        $this->info("Collecting Daily OHLC for Target Date: {$targetDate} (Window from: {$prevWorkingDate})");

        // 1. Fetch spot index instruments
        $index_instruments = DB::table('instruments')
            ->whereIn('instrument_key', $this->index_instruments)
            ->get();

        $this->updateInstruments($index_instruments, '', $targetDate, $prevWorkingDate);

        // 2. Fetch options for indices across 4 weekly expiries + 1 monthly expiry
        foreach ($this->indices as $index) {
            $expiries = $this->getTargetExpiries($index, $targetDate);

            foreach ($expiries as $expiryRecord) {
                $instruments = DB::table('instruments')
                    ->where('underlying_symbol', $index)
                    ->where('expiry', $expiryRecord->expiry)
                    ->get();

                $expiryLabel = $expiryRecord->is_current ? 'Current' : ($expiryRecord->is_next ? 'Next' : 'Far');
                $this->info(
                    "Processing {$index} expiry {$expiryRecord->expiry_date} ({$expiryLabel}) with {$instruments->count()} instruments..."
                );

                $this->updateInstruments($instruments, $index, $targetDate, $prevWorkingDate);
            }
        }

        $this->info("Complete! Stored daily OHLC for date: {$targetDate}");
    }

    /**
     * Get 4 weekly expiries + at least 1 monthly expiry for the given index and target date.
     */
    private function getTargetExpiries(string $index, string $targetDate)
    {
        $allOptExpiries = DB::table('nse_expiries')
            ->where('instrument_type', 'OPT')
            ->where('trading_symbol', $index)
            ->where('expiry_date', '>=', $targetDate)
            ->orderBy('expiry_date', 'asc')
            ->get();

        if ($allOptExpiries->isEmpty()) {
            return DB::table('nse_expiries')
                ->where('instrument_type', 'OPT')
                ->where('trading_symbol', $index)
                ->where(function ($q) {
                    $q->where('is_current', 1)
                      ->orWhere('is_next', 1);
                })
                ->orderBy('expiry_date', 'asc')
                ->get();
        }

        // 1. Take 4 upcoming weekly expiries
        $targetExpiries = $allOptExpiries->take(4)->keyBy('expiry_date');

        // 2. Identify monthly expiries via FUT contract expiries and last expiry of month
        $futExpiryDates = DB::table('nse_expiries')
            ->where('instrument_type', 'FUT')
            ->where('trading_symbol', $index)
            ->where('expiry_date', '>=', $targetDate)
            ->orderBy('expiry_date', 'asc')
            ->pluck('expiry_date')
            ->toArray();

        $monthlyDates = $allOptExpiries->groupBy(function ($item) {
            return substr($item->expiry_date, 0, 7);
        })->map(function ($group) {
            return $group->sortByDesc('expiry_date')->first()->expiry_date;
        })->values()->toArray();

        $allMonthlyDates = array_unique(array_merge($futExpiryDates, $monthlyDates));

        // Find the first upcoming monthly expiry
        $firstMonthly = null;
        foreach ($allOptExpiries as $exp) {
            if (in_array($exp->expiry_date, $allMonthlyDates)) {
                $firstMonthly = $exp;
                break;
            }
        }

        // If the first monthly is already within the 4 weeks, also include the next monthly expiry
        if ($firstMonthly && $targetExpiries->has($firstMonthly->expiry_date)) {
            foreach ($allOptExpiries as $exp) {
                if (in_array($exp->expiry_date, $allMonthlyDates) && $exp->expiry_date > $firstMonthly->expiry_date) {
                    $targetExpiries->put($exp->expiry_date, $exp);
                    break;
                }
            }
        } elseif ($firstMonthly) {
            $targetExpiries->put($firstMonthly->expiry_date, $firstMonthly);
        }

        return $targetExpiries->sortBy('expiry_date')->values();
    }

    private function updateInstruments($instruments, $index = '', $targetDate, $prevDate)
    {
        $apiToken = config('services.upstox.analytics_token');
        if (empty($apiToken)) {
            $apiToken = config('services.upstox.access_token');
        }

        // Concurrency chunk size of 10 to prevent Cloudflare Error 1015 bursts
        $chunks = $instruments->chunk(10);
        $totalSaved = 0;

        foreach ($chunks as $chunkIndex => $chunk) {
            $attempts = 0;
            $maxAttempts = 5;
            $chunkDone = false;

            while (!$chunkDone && $attempts < $maxAttempts) {
                $attempts++;

                $responses = Http::pool(function (Pool $pool) use ($chunk, $apiToken, $targetDate, $prevDate) {
                    foreach ($chunk as $inst) {
                        $url = "https://api.upstox.com/v3/historical-candle/{$inst->instrument_key}/days/1/{$targetDate}/{$prevDate}";
                        $pool->as($inst->instrument_key)->withToken($apiToken)->timeout(15)->get($url);
                    }
                });

                $has429 = false;
                $retryAfter = 0;

                foreach ($responses as $res) {
                    if ($res->status() === 429) {
                        $has429 = true;
                        $ra = (int) $res->header('Retry-After');
                        if ($ra > $retryAfter) {
                            $retryAfter = $ra;
                        }
                    }
                }

                if ($has429) {
                    $waitTime = $retryAfter > 0 ? ($retryAfter + 1) : ($attempts * 3);
                    $this->warn("HTTP 429 rate limit hit on chunk " . ($chunkIndex + 1) . "/{$chunks->count()} (attempt {$attempts}/{$maxAttempts}). Pausing {$waitTime}s...");
                    sleep($waitTime);
                    continue; // Retry this chunk
                }

                $instKeyMap = $chunk->keyBy('instrument_key');

                foreach ($responses as $key => $res) {
                    if (!$res->ok()) {
                        continue;
                    }

                    $candles = $res->json('data.candles') ?? [];
                    if (empty($candles)) {
                        continue;
                    }

                    // The first candle (index 0) is the latest one
                    $candle = $candles[0];
                    if (count($candle) < 7) {
                        continue;
                    }

                    $inst = $instKeyMap->get($key);
                    if (!$inst) {
                        continue;
                    }

                    $expiryTimestamp = $inst->expiry ?? null;
                    $expiryDate = null;
                    if ($expiryTimestamp) {
                        if (is_numeric($expiryTimestamp) && strlen((string) $expiryTimestamp) > 10) {
                            $expiryDate = date('Y-m-d', $expiryTimestamp / 1000);
                        } else {
                            $expiryDate = date('Y-m-d', strtotime($expiryTimestamp));
                        }
                    }

                    DB::table('daily_ohlc_quotes')->updateOrInsert(
                        [
                            'symbol_name'    => empty($index) ? ($inst->trading_symbol ?? 'NIFTY') : $index,
                            'instrument_key' => $inst->instrument_key,
                            'expiry'         => $expiryDate ?? null,
                            'strike'         => $inst->strike_price ?? null,
                            'option_type'    => $inst->instrument_type ?? null,
                            'quote_date'     => $targetDate,
                        ],
                        [
                            'expiry_date'   => $expiryDate ?? null,
                            'open'          => $candle[1],
                            'high'          => $candle[2],
                            'low'           => $candle[3],
                            'close'         => $candle[4],
                            'volume'        => $candle[5],
                            'open_interest' => $candle[6],
                            'updated_at'    => now(),
                            'created_at'    => now(),
                        ]
                    );
                    $totalSaved++;
                }

                $chunkDone = true;
            }

            // Pacing delay between chunks: 350ms pause to stay safely below rate limit thresholds
            usleep(350000);
        }

        $this->info("Saved {$totalSaved} quotes for {$index} on date {$targetDate}");
    }
}
