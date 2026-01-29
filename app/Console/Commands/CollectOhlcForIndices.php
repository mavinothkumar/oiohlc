<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class CollectOhlcForIndices extends Command
{
    protected $signature = 'indices:collect-daily-ohlc';
    protected $description = 'Collect daily OHLC for all instruments of NIFTY, BANKNIFTY, and SENSEX options';
    public $index_instruments = [
       //'BSE_INDEX|BANKEX',
       'BSE_INDEX|SENSEX',
       'NSE_INDEX|Nifty 50',
       'NSE_INDEX|Nifty Bank',
      // 'NSE_INDEX|Nifty Fin Service',
    ];
    public $indices = ['NIFTY','BANKNIFTY', 'SENSEX' ]; //, 'FINNIFTY'
    public $quoteDate;
    public $workingDay;

    public function handle()
    {
        $this->quoteDate  = Carbon::now()->toDateString();
        $this->workingDay = DB::table('nse_working_days')->where('previous', 1)->first();
        if ( ! $this->workingDay) {
            $this->error("No previous working day found!");

            return;
        }

        $index_instruments = DB::table('instruments')
                               ->whereIn('instrument_key', $this->index_instruments)
                               ->get()->toArray();



        $this->update($index_instruments);


        foreach ($this->indices as $index) {
            // 1. Get current expiry for the instrument
            $expiry = DB::table('nse_expiries')
                        ->where('instrument_type', 'OPT')
                        ->where('is_current', 1)
                        ->where('trading_symbol', $index)
                        ->value('expiry');

            if ( ! $expiry) {
                $this->warn("No current expiry found for $index.");
                continue;
            }

            // 2. Get ALL instruments for underlying symbol and expiry
            $instruments = DB::table('instruments')
                             ->where('underlying_symbol', $index)
                             ->where('expiry', $expiry)
                             ->get()->toArray();

            if (empty($instruments)) {
                $this->warn("No instruments found for $index and expiry $expiry.");
                continue;
            }

            $this->update($instruments, $index);

//            foreach ($instruments as $instrument) {
//                $instrumentKey = $instrument->instrument_key;
//                $apiDate       = $workingDay->working_date;
//                $fromDate      = $apiDate;
//                $toDate        = $apiDate;
//
//                // Correct API endpoint as per documentation/reference
//                $apiUrl   = "https://api.upstox.com/v3/historical-candle/{$instrumentKey}/days/1/{$toDate}/{$fromDate}";
//                $apiToken = config('services.upstox.api_token'); // Store/update this in config/services.php
//
//                $response = Http::withToken($apiToken)->get($apiUrl);
//
//                if ($response->failed()) {
//                    $this->error("API failed for $index ({$instrumentKey}) on $quoteDate: ".$response->body());
//                    continue;
//                }
//
//                $result = $response->json();
//
//                // Defensive: Support both 'data' wrapping and direct 'candles'
//                $candles = [];
//                if ( ! empty($result['data']['candles'])) {
//                    $candles = $result['data']['candles'];
//                } elseif ( ! empty($result['candles'])) {
//                    $candles = $result['candles'];
//                }
//
//                foreach ($candles as $candle) {
//                    // Expected candle array: [datetime, open, high, low, close, volume, open_interest]
//                    if (count($candle) < 7) {
//                        continue;
//                    }
//
//                    $expiryTimestamp = $instrument->expiry ?? null;
//                    $expiryDate      = null;
//
//                    if ($expiryTimestamp) {
//                        // If expiry is a millisecond timestamp, convert it to seconds first
//                        if (is_numeric($expiryTimestamp) && strlen((string) $expiryTimestamp) > 10) {
//                            $expiryDate = date('Y-m-d', $expiryTimestamp / 1000);
//                        } else {
//                            // If it is already a date string or timestamp in seconds
//                            $expiryDate = date('Y-m-d', strtotime($expiryTimestamp));
//                        }
//                    }
//
//                    DB::table('daily_ohlc_quotes')->updateOrInsert(
//                        [
//                            'symbol_name'    => $index,
//                            'instrument_key' => $instrumentKey,
//                            'expiry'         => $expiry ?? null,
//                            'strike'         => $instrument->strike_price ?? null,
//                            'option_type'    => $instrument->instrument_type ?? null,
//                            'quote_date'     => Carbon::parse($candle[0])->toDateString(),
//                        ],
//                        [
//                            'expiry_date'   => $expiryDate ?? null,
//                            'open'          => $candle[1],
//                            'high'          => $candle[2],
//                            'low'           => $candle[3],
//                            'close'         => $candle[4],
//                            'volume'        => $candle[5],
//                            'open_interest' => $candle[6],
//                            'updated_at'    => now(),
//                            'created_at'    => now(),
//                        ]
//                    );
//
//                    $this->info("Inserted/Updated OHLC for $index, {$instrumentKey}, expiry $expiry, date ".Carbon::parse($candle[0])->toDateString());
//                }
//            }
        }
    }

    private function update($instruments, $index = '')
    {
        foreach ($instruments as $instrument) {
            $instrumentKey = $instrument->instrument_key;
            $apiDate       = $this->workingDay->working_date;
            $fromDate      = $apiDate;
            $toDate        = Carbon::parse($apiDate)->addDay()->format('Y-m-d');


            // Correct API endpoint as per documentation/reference
            $apiUrl   = "https://api.upstox.com/v3/historical-candle/{$instrumentKey}/days/1/{$fromDate}/{$fromDate}";
            $apiToken = config('services.upstox.access_token'); // Store/update this in config/services.php

            $response = Http::withToken($apiToken)->get($apiUrl);


            if ($response->failed()) {
                $this->error("API failed for $index ({$instrumentKey})".$response->body());
                continue;
            }

            $result = $response->json();


            // Defensive: Support both 'data' wrapping and direct 'candles'
            $candles = [];
            if ( ! empty($result['data']['candles'])) {
                $candles = $result['data']['candles'];
            } elseif ( ! empty($result['candles'])) {
                $candles = $result['candles'];
            }

            foreach ($candles as $candle) {
                // Expected candle array: [datetime, open, high, low, close, volume, open_interest]
                if (count($candle) < 7) {
                    continue;
                }

                $expiryTimestamp = $instrument->expiry ?? null;
                $expiryDate      = null;

                if ($expiryTimestamp) {
                    // If expiry is a millisecond timestamp, convert it to seconds first
                    if (is_numeric($expiryTimestamp) && strlen((string) $expiryTimestamp) > 10) {
                        $expiryDate = date('Y-m-d', $expiryTimestamp / 1000);
                    } else {
                        // If it is already a date string or timestamp in seconds
                        $expiryDate = date('Y-m-d', strtotime($expiryTimestamp));
                    }
                }

                DB::table('daily_ohlc_quotes')->updateOrInsert(
                    [
                        'symbol_name'    => empty($index) ? $instrument->trading_symbol : $index,
                        'instrument_key' => $instrumentKey,
                        'expiry'         => $expiryDate ?? null,
                        'strike'         => $instrument->strike_price ?? null,
                        'option_type'    => $instrument->instrument_type ?? null,
                        'quote_date'     => Carbon::parse($candle[0])->toDateString(),
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
                $expiry = $expiry ?? null;
                $this->info("Inserted/Updated OHLC for $index, {$instrumentKey}, expiry $expiryDate, date ".Carbon::parse($candle[0])->toDateString());
            }
        }

    }
}
