<?php

namespace App\Console\Commands;

use App\Services\Backtest\UnifiedBreadthBacktestEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RunUnifiedBreadthBacktest extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'backtest:unified-breadth
                            {--mode=both : Backtest mode: stocks (2+ years), confluence (options+stocks), or both}
                            {--from= : Start date (YYYY-MM-DD)}
                            {--to= : End date (YYYY-MM-DD)}
                            {--stock-threshold=80 : Stock Breadth threshold percentage}
                            {--option-threshold=75 : Options Breadth flow threshold percentage}
                            {--sl-buffer=15 : Points buffer for alarm candle High/Low SL}
                            {--tp1=100 : Target 1 points}
                            {--tp2=150 : Target 2 points}
                            {--basket-mode=dynamic : Options basket mode (dynamic or fixed)}
                            {--max-trades=2 : Max trades allowed per day}
                            {--limit= : Limit number of trading days to test}
                            {--save-json= : Save detailed trade log and stats to a JSON file}';

    /**
     * The console command description.
     */
    protected $description = 'Run unified backtest for NIFTY 50 Stock Breadth & Options Flow Confluence strategies';

    /**
     * Execute the console command.
     */
    public function handle(UnifiedBreadthBacktestEngine $engine): int
    {
        $mode = strtolower($this->option('mode') ?: 'both');
        $fromDate = $this->option('from');
        $toDate = $this->option('to');
        $stockThreshold = (float)$this->option('stock-threshold');
        $optionThreshold = (float)$this->option('option-threshold');
        $slBuffer = (float)$this->option('sl-buffer');
        $tp1 = (float)$this->option('tp1');
        $tp2 = (float)$this->option('tp2');
        $basketMode = $this->option('basket-mode');
        $maxTrades = (int)$this->option('max-trades');
        $limit = $this->option('limit') ? (int)$this->option('limit') : null;

        $this->newLine();
        $this->info("===============================================================================");
        $this->info("          🚀 NIFTY 50 UNIFIED BREADTH STRATEGY BACKTEST ENGINE 🚀             ");
        $this->info("===============================================================================");
        $this->line(" Strategy Mode        : <fg=bright-cyan>" . strtoupper($mode) . "</>");
        $this->line(" Stock Surge Target   : <fg=yellow>±{$stockThreshold}% Advance / Decline Weight/Count</>");
        $this->line(" Options Flow Target  : <fg=yellow>±{$optionThreshold}% Flow Parity (Dynamic Floating ATM Basket)</>");
        $this->line(" Stop Loss Rule       : <fg=magenta>Alarm Candle Low/High ± {$slBuffer} pts</>");
        $this->line(" Target Profit Rules  : <fg=green>TP1 = {$tp1} pts | TP2 = {$tp2} pts (Runner)</>");
        $this->line(" Max Trades / Day     : <fg=white>{$maxTrades}</>");
        $this->line(" Execution Horizon    : <fg=bright-blue>" . ($fromDate ?: 'Start of Data') . " → " . ($toDate ?: 'Latest Available') . "</>");
        $this->info("-------------------------------------------------------------------------------");
        $this->newLine();

        $allDates = $engine->resolveDates($mode, $fromDate, $toDate);
        if ($limit && $limit > 0) {
            $allDates = array_slice($allDates, -$limit); // Take last N days if limited
        }

        $totalDays = count($allDates);
        if ($totalDays === 0) {
            $this->error("No trading dates found matching criteria.");
            return 1;
        }

        $this->comment("Discovered {$totalDays} trading days to backtest. Starting simulation...");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($totalDays);
        $progressBar->setFormat(" %current%/%max% [%bar%] %percent:3s%% -- %message%");
        $progressBar->setMessage("Initializing...");
        $progressBar->start();

        $config = [
            'mode' => $mode,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'stock_threshold' => $stockThreshold,
            'option_threshold' => $optionThreshold,
            'sl_buffer' => $slBuffer,
            'tp1' => $tp1,
            'tp2' => $tp2,
            'basket_mode' => $basketMode,
            'max_trades_per_day' => $maxTrades,
        ];

        // Override dates in engine if limited
        $results = $engine->run($config, function (string $date, int $cur, int $tot, array $dayTrades) use ($progressBar) {
            $tradeCount = count($dayTrades);
            $msg = "{$date} | " . ($tradeCount > 0 ? "⚡ {$tradeCount} trade(s)" : "No setup");
            $progressBar->setMessage($msg);
            $progressBar->advance();
        });

        $progressBar->finish();
        $this->newLine(2);

        $stats = $results['stats'];
        $trades = $results['trades'];

        // ── DISPLAY PERFORMANCE SUMMARY ──
        $this->info("===============================================================================");
        $this->info("                         📊 BACKTEST PERFORMANCE SUMMARY                        ");
        $this->info("===============================================================================");

        $winColor = ($stats['win_rate_pct'] >= 50.0) ? 'green' : 'yellow';
        $pnlColor = ($stats['net_pnl_pts'] >= 0) ? 'bright-green' : 'red';
        $pfColor = ($stats['profit_factor'] >= 1.5) ? 'bright-green' : (($stats['profit_factor'] >= 1.0) ? 'yellow' : 'red');

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Trading Days Analyzed', number_format($stats['total_trading_days'])],
                ['Total Trades Executed', number_format($stats['total_trades'])],
                ['Winning Trades', "<fg=green>{$stats['wins']}</>"],
                ['Losing Trades', "<fg=red>{$stats['losses']}</>"],
                ['Win Rate %', "<fg={$winColor}>{$stats['win_rate_pct']}%</>"],
                ['Profit Factor', "<fg={$pfColor}>{$stats['profit_factor']}</>"],
                ['Net P&L (Total Points)', "<fg={$pnlColor}>" . ($stats['net_pnl_pts'] >= 0 ? "+{$stats['net_pnl_pts']}" : "{$stats['net_pnl_pts']}") . " pts</>"],
                ['Gross Wins', "<fg=green>+{$stats['gross_wins_pts']} pts</>"],
                ['Gross Losses', "<fg=red>-{$stats['gross_losses_pts']} pts</>"],
                ['Average Trade P&L', ($stats['avg_trade_pts'] >= 0 ? "+{$stats['avg_trade_pts']}" : "{$stats['avg_trade_pts']}") . " pts"],
                ['Average Winning Trade', "+{$stats['avg_win_pts']} pts"],
                ['Average Losing Trade', "-{$stats['avg_loss_pts']} pts"],
                ['Maximum Drawdown', "<fg=magenta>-{$stats['max_drawdown_pts']} pts</>"],
                ['Average Realized R:R', "1 : {$stats['avg_rr_ratio']}"],
                ['Average Trade Duration', "{$stats['avg_duration_minutes']} minutes"],
                ['Max Consecutive Wins', "<fg=green>{$stats['max_win_streak']}</>"],
                ['Max Consecutive Losses', "<fg=red>{$stats['max_loss_streak']}</>"],
            ]
        );

        // ── OUTCOME BREAKDOWN ──
        $this->newLine();
        $this->info("---------------------------- 🎯 OUTCOME DISTRIBUTION ---------------------------");
        $oc = $stats['outcomes'];
        $this->table(
            ['Target 2 Reached (+150 pts)', 'Target 1 Reached (+100 pts)', 'Stop Loss Hit (Candle SL)', 'EOD Profit Exit', 'EOD Loss Exit'],
            [
                [
                    "<fg=bright-green>{$oc['tp2_hits']}</>",
                    "<fg=green>{$oc['tp1_hits']}</>",
                    "<fg=red>{$oc['sl_hits']}</>",
                    "<fg=cyan>{$oc['eod_profits']}</>",
                    "<fg=yellow>{$oc['eod_losses']}</>",
                ]
            ]
        );

        // ── DIRECTION BREAKDOWN ──
        $this->newLine();
        $this->info("---------------------------- 🐂 LONG vs 🐻 SHORT BIAS ---------------------------");
        $db = $stats['direction_breakdown'];
        $this->table(
            ['Side', 'Trades', 'Wins', 'Win Rate %'],
            [
                ['🐂 BULL (Long Bias)', $db['bull']['trades'], $db['bull']['wins'], "{$db['bull']['win_rate_pct']}%"],
                ['🐻 BEAR (Short Bias)', $db['bear']['trades'], $db['bear']['wins'], "{$db['bear']['win_rate_pct']}%"],
            ]
        );

        // ── RECENT SAMPLE TRADES ──
        if (!empty($trades)) {
            $this->newLine();
            $this->info("----------------------- 📋 RECENT TRADES SAMPLE (LAST 10) ----------------------");
            $recent = array_slice($trades, -10);
            $rows = [];
            foreach ($recent as $t) {
                $dirTag = ($t['direction'] === 'BULL') ? "<fg=green>🐂 BULL</>" : "<fg=red>🐻 BEAR</>";
                $pnlStr = ($t['net_pts'] >= 0) ? "<fg=green>+{$t['net_pts']}</>" : "<fg=red>{$t['net_pts']}</>";
                $rows[] = [
                    $t['date'],
                    $dirTag,
                    $t['entry_time'],
                    $t['entry_price'],
                    $t['sl_price'] . " ({$t['risk_pts']}p)",
                    $t['exit_time'],
                    $t['exit_price'],
                    $t['outcome'],
                    $pnlStr,
                ];
            }
            $this->table(
                ['Date', 'Side', 'Entry Time', 'Entry Spot', 'SL Price (Risk)', 'Exit Time', 'Exit Spot', 'Outcome', 'Net Pts'],
                $rows
            );
        }

        // Save JSON report if requested
        $savePath = $this->option('save-json');
        if ($savePath) {
            file_put_contents($savePath, json_encode($results, JSON_PRETTY_PRINT));
            $this->info("Saved detailed report to: {$savePath}");
        }

        $this->newLine();
        return 0;
    }
}
