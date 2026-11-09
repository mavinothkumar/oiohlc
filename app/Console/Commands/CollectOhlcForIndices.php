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

        // 2. Fetch options for indices
        foreach ($this->indices as $index) {
            $expiries = DB::table('nse_expiries')
                ->where('instrument_type', 'OPT')
                ->where('trading_symbol', $index)
                ->where(function ($q) {
                    $q->where('is_current', 1)
                      ->orWhere('is_next', 1);
                })
                ->get();

            foreach ($expiries as $expiryRecord) {
                $instruments = DB::table('instruments')
                    ->where('underlying_symbol', $index)
                    ->where('expiry', $expiryRecord->expiry)
                    ->get();

                $this->info(
                    "Processing {$index} expiry {$expiryRecord->expiry_date} (" .
                    ($expiryRecord->is_current ? 'Current' : 'Next') . ") with {$instruments->count()} instruments..."
                );

                $this->updateInstruments($instruments, $index, $targetDate, $prevWorkingDate);
            }
        }

        $this->info("Complete! Stored daily OHLC for date: {$targetDate}");
    }

    private function updateInstruments($instruments, $index = '', $targetDate, $prevDate)
    {
        $apiToken = config('services.upstox.analytics_token');
        if (empty($apiToken)) {
            $apiToken = config('services.upstox.access_token');
        }

        $chunks = $instruments->chunk(20);
        $totalSaved = 0;

        foreach ($chunks as $chunk) {
            $responses = Http::pool(function (Pool $pool) use ($chunk, $apiToken, $targetDate, $prevDate) {
                foreach ($chunk as $inst) {
                    $url = "https://api.upstox.com/v3/historical-candle/{$inst->instrument_key}/days/1/{$targetDate}/{$prevDate}";
                    $pool->as($inst->instrument_key)->withToken($apiToken)->timeout(15)->get($url);
                }
            });

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
        }

        $this->info("Saved {$totalSaved} quotes for {$index} on date {$targetDate}");
    }
}
