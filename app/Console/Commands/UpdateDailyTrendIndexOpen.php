<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\DailyTrend;

class UpdateDailyTrendIndexOpen extends Command
{
    protected $signature = 'trend:update-index-open';
    protected $description = 'Fetch Nifty / BankNifty / Sensex open from Upstox API and update daily_trend.current_day_index_open';

    public function handle(): int
    {
        // 1. Resolve current working day
        $days = DB::table('nse_working_days')
                  ->where(function ($q) {
                      $q->where('previous', 1)
                        ->orWhere('current', 1);
                  })
                  ->orderByDesc('id')
                  ->get();

        $currentDayRow  = $days->firstWhere('current', 1);
        $previousDayRow = $days->firstWhere('previous', 1);

        $currentDay = $currentDayRow->working_date ?? null;
        $quoteDate  = $previousDayRow->working_date ?? null;

        if ( ! $currentDay || ! $quoteDate) {
            $this->error('Working days not configured (previous/current missing in nse_working_days)');

            return 1;
        }

        $this->info("Current working day: {$currentDay}");
        $this->info("Quote date (previous working day): {$quoteDate}");

        // 2. Instrument keys for indices (replace with real keys)
        $instrumentKeys = [
            'NIFTY'     => 'NSE_INDEX|Nifty 50',    // example placeholder
            'BANKNIFTY' => 'NSE_INDEX|Nifty Bank',  // example placeholder
            'SENSEX'    => 'BSE_INDEX|SENSEX',     // example placeholder
        ];

        $interval    = '1d';
        $accessToken = config('services.upstox.access_token'); // or however you store it

        $instrumentKeyParam = implode(',', $instrumentKeys);

        // 3. Call Upstox OHLC API
        $response = Http::withHeaders([
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer '.$accessToken,
        ])
                        ->timeout(10)
                        ->get('https://api.upstox.com/v3/market-quote/ohlc', [
                            'instrument_key' => $instrumentKeyParam,
                            'interval'       => $interval,
                        ]);

        if ( ! $response->ok()) {
            $this->error('Upstox API error: HTTP '.$response->status());

            return 1;
        }

        $body = $response->json();


        $data = $body['data'] ?? [];

        if (empty($data)) {
            $this->error('Upstox API: no data field in response');

            return 1;
        }

        $updated = 0;

        foreach ($instrumentKeys as $symbolName => $instrumentKey) {
            $escapePipe = str_ireplace('|', ':', $instrumentKey);
            $item       = $data[$escapePipe] ?? null;
            if ( ! $item || empty($item['live_ohlc']['open'])) {
                $this->warn("No open value for {$symbolName} ({$instrumentKey}) in API response");
                continue;
            }

            $openPrice = (float) $item['live_ohlc']['open'];

            $trend = DailyTrend::whereDate('quote_date', $quoteDate)
                               ->where('symbol_name', $symbolName)
                               ->first();

            if ( ! $trend) {
                $this->warn("No DailyTrend row found for {$symbolName} on {$quoteDate}");
                continue;
            }

            if ('NIFTY' === $symbolName) {
                // Round to nearest 50 for NIFTY
                $atm_index_open = round($openPrice / 50) * 50;
            } else {
                $atm_index_open = round($openPrice / 100) * 100;
            }
            $trend->atm_index_open = $atm_index_open;

            // ATM R/S levels
            $trend->atm_r_avg = $atm_index_open + (($trend->atm_ce_close + $trend->atm_pe_close) / 2);
            $trend->atm_s_avg = $atm_index_open - (($trend->atm_ce_close + $trend->atm_pe_close) / 2);

            $trend->atm_r = $atm_index_open + ($trend->atm_ce_close / 2);
            $trend->atm_s = $atm_index_open - ($trend->atm_pe_close / 2);

            $trend->atm_r_1 = $atm_index_open + $trend->atm_ce_close;
            $trend->atm_s_1 = $atm_index_open - $trend->atm_pe_close;

            $trend->atm_r_2 = $atm_index_open + $trend->atm_ce_close + ($trend->atm_ce_close / 2);
            $trend->atm_s_2 = $atm_index_open - $trend->atm_pe_close - ($trend->atm_pe_close / 2);

            $trend->atm_r_3 = $atm_index_open + $trend->atm_ce_close + $trend->atm_ce_close;
            $trend->atm_s_3 = $atm_index_open - $trend->atm_pe_close - $trend->atm_pe_close;

            $trend->open_type  = (new BuildExpiredDailyTrend())->buildOpenType($trend->index_close, $trend->index_high, $trend->index_low, $openPrice);
            $trend->open_value = $openPrice - $trend->index_close;


            $earthValue = (float) $trend->earth_value;

            $trend->current_day_index_open = $openPrice;
            $trend->earth_high             = $openPrice + $earthValue;
            $trend->earth_low              = $openPrice - $earthValue;
            $trend->save();

            $updated++;

            $this->info(
                "Updated {$symbolName}: open={$openPrice}, earth_value={$earthValue}, ".
                'EH='.($openPrice + $earthValue).', EL='.($openPrice - $earthValue)
            );
        }

        $this->info("Total rows updated: {$updated}");

        return 0;
    }
}
