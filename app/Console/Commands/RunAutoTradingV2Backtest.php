<?php

namespace App\Console\Commands;

use App\Models\AutoTradeV2Day;
use App\Models\AutoTradeV2Trade;
use App\Services\Backtest\AutoTradingV2Engine;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RunAutoTradingV2Backtest extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'trade:auto-engine-backtest
                            {--from= : Start date (YYYY-MM-DD)}
                            {--to= : End date (YYYY-MM-DD)}
                            {--days= : Number of recent trading days to process (e.g. 1, 7, 30)}
                            {--all : Process all available trading days in the database}
                            {--fresh : Clear previous backtest tables before running}
                            {--start-time=09:35 : Earliest trade scan time}
                            {--end-time=14:45 : Latest trade entry time}
                            {--vwap-mode=points : VWAP distance mode (points, pct, sigma)}
                            {--vwap-max-upper=165 : Max upper distance from VWAP (0 to disable)}
                            {--vwap-max-lower=170 : Max lower distance from VWAP (0 to disable)}
                            {--vwap-min-dist=0 : Min distance from VWAP (0 to disable)}
                            {--oi-unwind-pct=3 : Min OI % drop over 1-3 bars for Unwinding Squeeze}
                            {--armed-lookback=4 : Lookback bars (5m) for recently blocked impulse}
                            {--max-armed-drift=80 : Max price drift (pts) from missed impulse (0 to disable)}
                            {--armed-cont-pct=60 : Min continuation STK% on delayed OI confirmation}
                            {--min-stk-pct=70 : Mandatory min Nifty 50 Stock % (weight or count)}';

    /**
     * The console command description.
     */
    protected $description = 'Execute High-Probability V2 Automated Trading Engine Backtest and store unique results into database';

    /**
     * Execute the console command.
     */
    public function handle(AutoTradingV2Engine $engine): int
    {
        $this->newLine();
        $this->info("===============================================================================");
        $this->info("   🚀 NIFTY V2 HIGH-PROBABILITY AUTO TRADING ENGINE BACKTEST PIPELINE 🚀       ");
        $this->info("===============================================================================");

        if ($this->option('fresh')) {
            $this->warn("Clearing existing backtest tables...");
            AutoTradeV2Trade::truncate();
            AutoTradeV2Day::truncate();
            $this->info("Tables truncated.");
        }

        $nowKolkata = Carbon::now('Asia/Kolkata');
        $todayStr = $nowKolkata->toDateString();

        // Resolve dates
        $query = DB::table('nse_working_days')
            ->where('working_date', '<=', $todayStr)
            ->orderBy('working_date', 'asc');

        $fromDate = $this->option('from');
        $toDate = $this->option('to');
        $daysCount = $this->option('days') ? (int)$this->option('days') : null;

        if ($fromDate) {
            $query->where('working_date', '>=', $fromDate);
        }
        if ($toDate) {
            $query->where('working_date', '<=', $toDate);
        }

        $allEligibleDates = $query->pluck('working_date')->toArray();

        if (empty($allEligibleDates)) {
            $this->error("No trading dates found matching criteria.");
            return 1;
        }

        if ($daysCount !== null && $daysCount > 0 && !$fromDate && !$this->option('all')) {
            $allEligibleDates = array_slice($allEligibleDates, -$daysCount);
        }

        $totalDays = count($allEligibleDates);
        $this->line(" Trading Days Found   : <fg=bright-cyan>{$totalDays} Days</> [{$allEligibleDates[0]} to " . end($allEligibleDates) . "]");
        $this->line(" Session Scan Window  : <fg=bright-yellow>" . ($this->option('start-time') ?: '09:35') . " to " . ($this->option('end-time') ?: '14:45') . "</>");
        $this->line(" Strategy Rules       : <fg=bright-green>80% Breadth | 70% Flow | OI Skew & Wall Veto | 45m Stagnation 1:1 Trail | 3-Lot Scaling</>");
        $this->newLine();

        $customConfig = [
            'startTime'        => $this->option('start-time') ?: '09:35',
            'endTime'          => $this->option('end-time') ?: '14:45',
            'vwapDistMode'     => $this->option('vwap-mode') ?: 'points',
            'vwapMaxUpperDist' => (float)$this->option('vwap-max-upper'),
            'vwapMaxLowerDist' => (float)$this->option('vwap-max-lower'),
            'vwapMinDist'      => (float)$this->option('vwap-min-dist'),
            'oiUnwindDropPct'   => (float)$this->option('oi-unwind-pct'),
            'armedLookbackBars' => (int)$this->option('armed-lookback'),
            'maxArmedDriftPts'  => (float)$this->option('max-armed-drift'),
            'armedContMinPct'   => (float)$this->option('armed-cont-pct'),
            'minStkImpulsePct'  => (float)$this->option('min-stk-pct'),
        ];

        $progressBar = $this->output->createProgressBar($totalDays);
        $progressBar->start();

        $results = $engine->runDays($allEligibleDates, $customConfig, function ($date, $current, $total, $dayResult) use ($progressBar) {
            $progressBar->advance();
        });

        $progressBar->finish();
        $this->newLine(2);

        // Display Summary
        $dayRows = AutoTradeV2Day::whereIn('date', $allEligibleDates)->get();
        $totalWins = $dayRows->where('day_status', 'WIN')->count();
        $totalLosses = $dayRows->where('day_status', 'LOSS')->count();
        $totalScratch = $dayRows->where('day_status', 'SCRATCH')->count();
        $totalNoTrade = $dayRows->where('day_status', 'NO_TRADE')->count();
        $netRupees = $dayRows->sum('net_pnl_rupees');
        $netPoints = $dayRows->sum('net_points');

        $tradesCount = AutoTradeV2Trade::whereIn('date', $allEligibleDates)->count();
        $tradeWins = AutoTradeV2Trade::whereIn('date', $allEligibleDates)->where('pnl_rupees', '>', 0)->count();
        $tradeLosses = AutoTradeV2Trade::whereIn('date', $allEligibleDates)->where('pnl_rupees', '<', 0)->count();
        $tradeWinRate = $tradesCount > 0 ? round(($tradeWins / $tradesCount) * 100, 1) : 0;

        $this->info("===============================================================================");
        $this->info("                           PERFORMANCE SUMMARY                                 ");
        $this->info("===============================================================================");
        $this->table(
            ['Metric', 'Result'],
            [
                ['Total Trading Days Evaluated', $totalDays],
                ['Days with Executed Trades', $totalWins + $totalLosses + $totalScratch],
                ['Winning Days (Green)', "<fg=bright-green>{$totalWins}</>"],
                ['Losing Days (Red)', "<fg=bright-red>{$totalLosses}</>"],
                ['No Trade / Standby Days', $totalNoTrade],
                ['Total Trades Executed', $tradesCount],
                ['Trade Win Rate', "{$tradeWinRate}% ({$tradeWins}W / {$tradeLosses}L)"],
                ['Net P&L Points', ($netPoints >= 0 ? '+' : '') . number_format($netPoints, 1) . ' pts'],
                ['Net P&L Rupees (3 Lots)', ($netRupees >= 0 ? '<fg=bright-green>+₹' : '<fg=bright-red>-₹') . number_format($netRupees) . '</>'],
            ]
        );

        $this->info("✓ All data successfully committed to auto_trade_v2_days and auto_trade_v2_trades.");
        $this->info("✓ Ready for instant web dashboard query at /automated-trading-backtest.");
        return 0;
    }
}
