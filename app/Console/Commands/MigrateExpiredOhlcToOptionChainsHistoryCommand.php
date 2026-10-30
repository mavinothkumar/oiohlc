<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateExpiredOhlcToOptionChainsHistoryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'options:fill-history-from-expired 
                            {--from= : Start date (YYYY-MM-DD)} 
                            {--to= : End date (YYYY-MM-DD)} 
                            {--date= : Specific single date (YYYY-MM-DD)} 
                            {--force : Overwrite existing records for the dates}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate actual historical 5m CE/PE options data from expired_ohlc into option_chains_history (Strictly pure Price/OI/Volume, no Black-Scholes)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!Schema::hasTable('expired_ohlc') || !Schema::hasTable('option_chains_history')) {
            $this->error('Required tables (expired_ohlc or option_chains_history) do not exist.');
            return 1;
        }

        $specificDate = $this->option('date');
        $fromDate = $this->option('from');
        $toDate = $this->option('to');
        $force = $this->option('force');

        // Resolve dates to process
        if ($specificDate) {
            $dates = [$specificDate];
        } else {
            $from = $fromDate ?: '2024-09-27';
            $to = $toDate ?: '2026-05-13'; // Up to where option_chains_history starts

            $this->info("Scanning trading dates from expired_ohlc between {$from} and {$to}...");

            $dates = DB::table('expired_ohlc')
                ->where('underlying_symbol', 'NIFTY')
                ->where('interval', '5minute')
                ->where('timestamp', '>=', "{$from} 00:00:00")
                ->where('timestamp', '<=', "{$to} 23:59:59")
                ->selectRaw('DISTINCT DATE(timestamp) as trade_date')
                ->orderBy('trade_date', 'asc')
                ->pluck('trade_date')
                ->toArray();
        }

        if (empty($dates)) {
            $this->warn('No trading dates found in expired_ohlc for the specified range.');
            return 0;
        }

        $totalDays = count($dates);
        $this->info("Found {$totalDays} trading dates to process.");

        $totalInserted = 0;
        $t0 = microtime(true);

        foreach ($dates as $idx => $date) {
            $dayNum = $idx + 1;

            // Check if option_chains_history already has data for this date
            $existingCount = DB::table('option_chains_history')
                ->where('captured_at', '>=', "{$date} 09:15:00")
                ->where('captured_at', '<=', "{$date} 15:30:00")
                ->where('trading_symbol', 'NIFTY')
                ->count();

            if ($existingCount > 0 && !$force) {
                $this->line("<comment>[{$dayNum}/{$totalDays}] {$date}</comment>: Already has {$existingCount} rows in option_chains_history. Skipping (use --force to overwrite).");
                continue;
            }

            if ($existingCount > 0 && $force) {
                DB::table('option_chains_history')
                    ->where('captured_at', '>=', "{$date} 09:15:00")
                    ->where('captured_at', '<=', "{$date} 15:30:00")
                    ->where('trading_symbol', 'NIFTY')
                    ->delete();
            }

            // Check if expired_ohlc has 5m options data for this date
            $dayT0 = microtime(true);

            $insertSql = "
                INSERT INTO option_chains_history (
                    instrument_key, underlying_key, trading_symbol, expiry, strike_price,
                    option_type, ltp, diff_ltp, volume, diff_volume, oi, diff_oi,
                    close_price, underlying_spot_price, captured_at, created_at, updated_at, build_up
                )
                SELECT 
                    e.instrument_key,
                    'NSE_INDEX|Nifty 50',
                    'NIFTY',
                    e.expiry,
                    e.strike,
                    e.instrument_type,
                    e.close,
                    COALESCE(e.diff_ltp, 0),
                    e.volume,
                    COALESCE(e.diff_volume, 0),
                    e.open_interest,
                    COALESCE(e.diff_oi, 0),
                    e.close,
                    COALESCE(s.close, e.close),
                    e.timestamp,
                    e.timestamp,
                    e.timestamp,
                    CASE WHEN e.build_up = 'Neutral' THEN NULL ELSE e.build_up END
                FROM expired_ohlc e
                LEFT JOIN ohlc_5m_quotes s ON s.ts_at = e.timestamp 
                    AND s.instrument_type = 'INDEX' 
                    AND (s.trading_symbol = 'NIFTY' OR s.trading_symbol = 'Nifty 50' OR s.instrument_key LIKE '%Nifty 50%')
                WHERE e.underlying_symbol = 'NIFTY'
                  AND e.instrument_type IN ('CE', 'PE')
                  AND e.interval = '5minute'
                  AND e.timestamp >= '{$date} 09:15:00'
                  AND e.timestamp <= '{$date} 15:30:00'
            ";

            $affected = DB::affectingStatement($insertSql);
            $daySec = round(microtime(true) - $dayT0, 2);

            $totalInserted += $affected;
            $this->info("<info>[{$dayNum}/{$totalDays}] {$date}</info>: Inserted {$affected} rows ({$daySec}s)");
        }

        $totalSec = round(microtime(true) - $t0, 2);
        $this->info("\nAll done! Total inserted: " . number_format($totalInserted) . " rows across {$totalDays} days in {$totalSec}s.");

        return 0;
    }
}
