<?php

namespace App\Console\Commands;

use App\Models\ExpiredExpiry;
use App\Services\BacktestIndexService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class Fill3MinutesExpiriesOHLC extends Command
{
    protected $signature = 'backtest:fix-3mins-expired-ohlc
                            {--symbol=NIFTY : Underlying symbol (e.g. NIFTY)}';

    protected $description = 'Backfill first trading day (day OHLC only) for each NEXT expiry using CURRENT expiry day';

    public function handle(BacktestIndexService $indexService): int
    {
        $symbol = $this->option('symbol');

        // Get all expiries for this symbol, sorted ascending
        $expiries = ExpiredExpiry::query()
                                 ->where('underlying_symbol', $symbol)
                                 ->where('instrument_type', 'OPT')
                                 ->where('expiry_date','>', '2024-10-24')
                                 ->orderBy('expiry_date')
                                 ->pluck('expiry_date')
                                 ->values()
                                 ->all();


        if (count($expiries) < 2) {
            $this->error("Need at least two expiries for {$symbol} to build CURRENT/NEXT pairs.");

            return self::FAILURE;
        }

        // Build pairs: (current, next)
        $pairs = [];
        for ($i = 0; $i < count($expiries) - 1; $i++) {
            $current_date = DB::table('nse_working_days')->where('working_date', '>', $expiries[$i])->value('working_date');
            $pairs[]      = [
                'current'      => Carbon::createFromDate($expiries[$i])->format('Y-m-d'),
                'next'         => Carbon::createFromDate($expiries[$i + 1])->format('Y-m-d'),
                'current_date' => $current_date,
            ];
        }

        foreach ($pairs as $pair) {
            $currentExpiry = $pair['current_date'];
            $nextExpiry    = $pair['next'];
            $expiry        = $pair['next'];
            // Call existing command: from/to = CURRENT_EXPIRY_DAY, expiry = NEXT_EXPIRY, skip 5m
            info('From Date: '.$currentExpiry.' End: '.$nextExpiry.' Expires: '.$expiry);
            Artisan::call('upstox:sync-nifty-option-ohlc', [
                'from'     => $currentExpiry,
                'to'       => $nextExpiry,
                '--no-5m'  => true,
                '--no-day' => true,
            ]);
            $this->line(trim(Artisan::output()));
        }

        $this->info('Done backfilling first-day daily OHLC for all NEXT expiries.');

        return self::SUCCESS;
    }
}
