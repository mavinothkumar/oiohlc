<?php

namespace App\Console\Commands;

use App\Models\ExpiredExpiry;
use App\Services\BacktestIndexService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class FixNextExpiryFirstDayForAll extends Command
{
    protected $signature = 'backtest:fix-next-expiry-first-day-all
                            {--symbol=NIFTY : Underlying symbol (e.g. NIFTY)}';

    protected $description = 'Backfill first trading day (day OHLC only) for each NEXT expiry using CURRENT expiry day';

    public function handle(BacktestIndexService $indexService): int
    {
        $symbol = $this->option('symbol');

        // Get all expiries for this symbol, sorted ascending
        $expiries = ExpiredExpiry::query()
                                 ->where('underlying_symbol', $symbol)
                                 ->where('instrument_type', 'OPT')
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
            $pairs[] = [
                'current' => $expiries[$i],
                'next'    => $expiries[$i + 1],
            ];
        }

        foreach ($pairs as $pair) {
            $currentExpiry = $pair['current'];
            $nextExpiry    = $pair['next'];

//            $this->info("Processing CURRENT expiry {$currentExpiry} → NEXT expiry {$nextExpiry}...");
//
//            // Define "CURRENT_EXPIRY_DAY" as first working day before NEXT expiry
//            $currentExpiryDay = DB::table('nse_working_days')
//                                  ->where('working_date', '<', $nextExpiry)
//                                  ->orderByDesc('working_date')
//                                  ->value('working_date');
//
//            if (! $currentExpiryDay) {
//                $this->warn("  No working day found before NEXT expiry {$nextExpiry}, skipping pair.");
//                continue;
//            }
//
//            // Optional sanity check: ensure there is a previous working day and NIFTY close
//            $prevDate = $indexService->getPreviousWorkingDate($currentExpiryDay);
//            if (! $prevDate) {
//                $this->warn("  No previous working day for {$currentExpiryDay}, skipping pair.");
//                continue;
//            }
//
//            $close = $indexService->getNiftyCloseForDate($prevDate);
//            if ($close === null) {
//                $this->warn("  No NIFTY index close in expired_ohlc for {$prevDate}, skipping pair.");
//                continue;
//            }
//
//            $this->line("  CURRENT_EXPIRY_DAY: {$currentExpiryDay}, prev index close {$prevDate} = {$close}");
//            $this->line("  Calling upstox:sync-nifty-option-ohlc for NEXT expiry (day only)...");

//            dd([
//                $currentExpiry,
//                $nextExpiry
//            ]);


            // Call existing command: from/to = CURRENT_EXPIRY_DAY, expiry = NEXT_EXPIRY, skip 5m
            Artisan::call('upstox:sync-nifty-option-ohlc', [
                'from'     => $currentExpiry,
                'to'       => $currentExpiry,
                '--expiry' => $nextExpiry,
                '--no-5m'  => true,
            ]);
            $this->line(trim(Artisan::output()));
        }

        $this->info('Done backfilling first-day daily OHLC for all NEXT expiries.');
        return self::SUCCESS;
    }
}
