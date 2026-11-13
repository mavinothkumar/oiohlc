<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class HomeController extends Controller
{
    /**
     * Allowed commands for manual trigger
     */
    protected array $allowedCommands = [
        'app:run-trading-pipeline'          => 'Run All Pre-Market Tasks (Pipeline)',
        'nse:update-working-day-flags'      => 'Update Working Day Flags',
        'upstox:fetch-instruments'          => 'Fetch Upstox Instruments Master',
        'expiries:update-benchmarks'        => 'Update Benchmark Expiries',
        'indices:collect-daily-ohlc'        => 'Collect Previous Day Index OHLC',
        'trend:populate-daily'              => 'Populate Daily Trend & Earth Levels',
        'trend:update-index-open'           => 'Update Index Open & ATM Strikes',
        'trading:generate-daily-strategies' => 'Generate Daily Strategy Panels',
        'ohlc:collect-1min'                 => 'Collect 1-Min OHLC (Single Iteration)',
        'ohlc:sync-nifty50-stocks'          => 'Sync NIFTY 50 Stocks OHLC (Single Iteration)',
        'ohlc:aggregate-daily-5m'           => 'Aggregate Daily 5-Min Quotes',
        'option-chains:archive'             => 'Archive Option Chains',
        'ohlc:sync-nifty50'                 => 'Sync Nifty 50 5m EOD Candles',
    ];

    /**
     * Display the Home Page Dashboard with Current Day Cron Status
     */
    public function index()
    {
        $statusData = $this->gatherSystemStatus();

        return view('welcome', $statusData);
    }

    /**
     * API endpoint to get real-time status as JSON
     */
    public function getStatusApi()
    {
        $statusData = $this->gatherSystemStatus();

        return response()->json($statusData);
    }

    /**
     * Trigger a whitelisted artisan command manually
     */
    public function runCommand(Request $request)
    {
        $command = $request->input('command');
        $skipInstruments = $request->boolean('skip_instruments', false);

        if (!array_key_exists($command, $this->allowedCommands)) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized or invalid command specified.',
                ], 403);
            }

            return redirect()->route('home')->with('error', 'Unauthorized or invalid command specified.');
        }

        try {
            $params = [];
            if ($command === 'app:run-trading-pipeline' && $skipInstruments) {
                $params['--skip-instruments'] = true;
            }

            $startTime = microtime(true);
            $exitCode = Artisan::call($command, $params);
            $output = Artisan::output();
            $duration = round(microtime(true) - $startTime, 2);

            $commandLabel = $this->allowedCommands[$command] ?? $command;
            $success = ($exitCode === 0);

            $msg = $success
                ? "Successfully executed '{$commandLabel}' in {$duration}s."
                : "Command '{$commandLabel}' exited with code {$exitCode}.";

            if ($request->wantsJson()) {
                return response()->json([
                    'success'  => $success,
                    'command'  => $command,
                    'label'    => $commandLabel,
                    'duration' => $duration,
                    'output'   => $output,
                    'message'  => $msg,
                ]);
            }

            return redirect()->route('home')
                ->with($success ? 'success' : 'error', $msg)
                ->with('command_output', $output);

        } catch (\Throwable $e) {
            $msg = "Error executing '{$command}': " . $e->getMessage();

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'command' => $command,
                    'message' => $msg,
                    'error'   => $e->getMessage(),
                ], 500);
            }

            return redirect()->route('home')->with('error', $msg);
        }
    }

    /**
     * Clear stale scheduler locks from cache_locks
     */
    public function clearLocks(Request $request)
    {
        try {
            $deleted = DB::table('cache_locks')
                ->where('key', 'like', '%schedule%')
                ->delete();

            $msg = $deleted > 0 
                ? "Cleared {$deleted} active scheduler lock(s). Scheduler is unblocked." 
                : "No active scheduler locks found in cache_locks.";

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'deleted' => $deleted,
                ]);
            }

            return redirect()->route('home')->with('success', $msg);
        } catch (\Throwable $e) {
            $msg = "Error clearing locks: " . $e->getMessage();
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 500);
            }
            return redirect()->route('home')->with('error', $msg);
        }
    }

    /**
     * Clear stale scheduler locks and refill missed 1m & 5m OHLC candles
     */
    public function clearLocksAndRefill(Request $request)
    {
        @set_time_limit(180);

        try {
            // 1. Delete stuck schedule locks
            $deleted = DB::table('cache_locks')
                ->where('key', 'like', '%schedule%')
                ->delete();

            $logs = [];
            $logs[] = "Cleared {$deleted} schedule lock(s) from cache_locks.";

            // 2. Run 1-min index & stock quotes fetch (and syncs index intraday series)
            $start1 = microtime(true);
            Artisan::call('ohlc:collect-1min');
            $dur1 = round(microtime(true) - $start1, 2);
            $logs[] = "ohlc:collect-1min completed in {$dur1}s: " . trim(Artisan::output());

            // 3. Run full intraday 1m candle backfill for all 50 constituent stocks
            $start2 = microtime(true);
            Artisan::call('ohlc:sync-nifty50-stocks');
            $dur2 = round(microtime(true) - $start2, 2);
            $logs[] = "ohlc:sync-nifty50-stocks completed in {$dur2}s: " . trim(Artisan::output());

            $output = implode("\n", $logs);
            $msg = "Successfully cleared {$deleted} lock(s) and refilled missed 1m & 5m OHLC candles!";

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'output'  => $output,
                ]);
            }

            return redirect()->route('home')
                ->with('success', $msg)
                ->with('command_output', $output);

        } catch (\Throwable $e) {
            $msg = "Error during clear & refill: " . $e->getMessage();
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 500);
            }
            return redirect()->route('home')->with('error', $msg);
        }
    }

    /**
     * Fetch raw log content for inspection
     */
    public function getLog(Request $request)
    {
        $file = $request->query('file');
        $allowedLogs = [
            'update-working-day-flags.log',
            'instruments.log',
            'expiry.log',
            'working-days.log',
            'populate-daily.log',
            'update-index-open.log',
            'generate-daily-strategies.log',
            'ohlc-1min.log',
            'nifty50-stocks-sync.log',
            'ohlc-aggregate-daily-5m.log',
            'option-chains-archive.log',
            'nifty50-5m-eod-sync.log',
            'laravel.log',
        ];

        if (!in_array($file, $allowedLogs)) {
            return response()->json(['error' => 'Log file not accessible'], 403);
        }

        $path = storage_path('logs/' . $file);
        if (!File::exists($path)) {
            return response()->json([
                'file'    => $file,
                'content' => '(Log file does not exist yet)',
                'updated' => null,
            ]);
        }

        $lines = $this->tailFile($path, 60);

        return response()->json([
            'file'    => $file,
            'content' => implode("\n", $lines),
            'updated' => date('d M Y, h:i:s A', File::lastModified($path)),
        ]);
    }

    /**
     * Gather complete system status for current trading day
     */
    protected function gatherSystemStatus(): array
    {
        $now = Carbon::now('Asia/Kolkata');
        $today = $now->toDateString(); // e.g. '2026-11-06'
        $currentTime = $now->format('H:i');
        $isWeekday = $now->isWeekday();

        // 1. Session Status
        $session = 'closed';
        $sessionLabel = 'Market Closed';
        $sessionColor = 'slate';

        if ($isWeekday) {
            if ($currentTime >= '08:45' && $currentTime < '09:15') {
                $session = 'pre_market';
                $sessionLabel = 'Pre-Market Discovery (08:45 - 09:15)';
                $sessionColor = 'amber';
            } elseif ($currentTime >= '09:15' && $currentTime <= '15:30') {
                $session = 'open';
                $sessionLabel = 'Live Regular Trading (09:15 - 15:30)';
                $sessionColor = 'emerald';
            } elseif ($currentTime > '15:30' && $currentTime <= '16:00') {
                $session = 'post_market';
                $sessionLabel = 'Post-Market EOD Processing (15:30 - 16:00)';
                $sessionColor = 'indigo';
            } else {
                $session = 'closed';
                $sessionLabel = 'Market Closed (Night Session)';
                $sessionColor = 'slate';
            }
        } else {
            $sessionLabel = 'Weekend (Market Closed)';
        }

        // 2. Working Days Check
        $currentWorkingRow = DB::table('nse_working_days')->where('current', 1)->first();
        $previousWorkingRow = DB::table('nse_working_days')->where('previous', 1)->first();

        $currentTradingDate = $currentWorkingRow ? $currentWorkingRow->working_date : null;
        $previousTradingDate = $previousWorkingRow ? $previousWorkingRow->working_date : null;

        $workingDaysStatus = 'pending';
        $workingDaysLabel = 'Pending';
        if ($currentTradingDate) {
            if ($currentTradingDate === $today) {
                $workingDaysStatus = 'success';
                $workingDaysLabel = 'Active for Today';
            } elseif ($isWeekday && $currentTime > '08:50') {
                $workingDaysStatus = 'danger';
                $workingDaysLabel = 'Outdated (Flag not updated for today)';
            } else {
                $workingDaysStatus = 'info';
                $workingDaysLabel = "Set to {$currentTradingDate}";
            }
        } else {
            $workingDaysStatus = $currentTime > '08:50' ? 'danger' : 'pending';
            $workingDaysLabel = 'No current flag found';
        }

        // 3. Upstox Instruments Check
        $instrumentsCount = DB::table('instruments')->count();
        $instrumentsLog = storage_path('logs/instruments.log');
        $instrumentsLogTime = File::exists($instrumentsLog) ? File::lastModified($instrumentsLog) : null;
        $instrumentsUpdatedToday = $instrumentsLogTime && date('Y-m-d', $instrumentsLogTime) === $today;

        $instrumentsStatus = 'pending';
        if ($instrumentsCount > 50000) {
            $instrumentsStatus = $instrumentsUpdatedToday ? 'success' : 'success';
            $instrumentsLabel = number_format($instrumentsCount) . ' instruments active' . ($instrumentsUpdatedToday ? ' (Updated Today)' : '');
        } elseif ($instrumentsCount > 0) {
            $instrumentsStatus = 'warning';
            $instrumentsLabel = number_format($instrumentsCount) . ' instruments';
        } else {
            $instrumentsStatus = 'danger';
            $instrumentsLabel = '0 instruments found';
        }

        // 4. Benchmark Expiries Check
        $currentNiftyOpt = DB::table('nse_expiries')
            ->where('instrument_type', 'OPT')
            ->where('trading_symbol', 'NIFTY')
            ->where('is_current', 1)
            ->first();

        $currentBankNiftyOpt = DB::table('nse_expiries')
            ->where('instrument_type', 'OPT')
            ->where('trading_symbol', 'BANKNIFTY')
            ->where('is_current', 1)
            ->first();

        $totalExpiries = DB::table('nse_expiries')->count();
        $expiriesStatus = 'pending';
        $expiriesLabel = 'Pending';

        if ($currentNiftyOpt && $currentNiftyOpt->expiry_date >= $today) {
            $expiriesStatus = 'success';
            $expiriesLabel = "NIFTY: {$currentNiftyOpt->expiry_date}" . ($currentBankNiftyOpt ? " | BNF: {$currentBankNiftyOpt->expiry_date}" : '');
        } else {
            if ($currentTime > '09:00' && $isWeekday) {
                $expiriesStatus = 'danger';
                $expiriesLabel = $currentNiftyOpt ? "Expired current date ({$currentNiftyOpt->expiry_date})" : 'No current expiry defined';
            } else {
                $expiriesStatus = 'pending';
                $expiriesLabel = 'Scheduled at 09:00';
            }
        }

        // 5. Previous Day Index & Options OHLC Check
        $previousDayOhlcCount = 0;
        $previousDayDateUsed = $previousTradingDate ?? Carbon::yesterday()->toDateString();
        if ($previousTradingDate) {
            $previousDayOhlcCount = DB::table('daily_ohlc_quotes')
                ->whereDate('quote_date', $previousTradingDate)
                ->count();
        }

        $dailyOhlcStatus = 'pending';
        $dailyOhlcLabel = 'Pending';
        if ($previousDayOhlcCount > 0) {
            $dailyOhlcStatus = 'success';
            $dailyOhlcLabel = number_format($previousDayOhlcCount) . " quotes for {$previousTradingDate}";
        } else {
            if ($currentTime > '09:01' && $isWeekday) {
                $dailyOhlcStatus = 'danger';
                $dailyOhlcLabel = "0 quotes collected for {$previousTradingDate}";
            } else {
                $dailyOhlcStatus = 'pending';
                $dailyOhlcLabel = 'Scheduled at 09:01';
            }
        }

        // 6. Daily Trend & Earth Levels Check
        $dailyTrendRows = DB::table('daily_trend')
            ->whereDate('trading_date', $today)
            ->get();
        $dailyTrendCount = $dailyTrendRows->count();
        $niftyTrend = $dailyTrendRows->firstWhere('symbol_name', 'NIFTY');

        $trendDailyStatus = 'pending';
        $trendDailyLabel = 'Pending';
        if ($dailyTrendCount > 0) {
            $trendDailyStatus = 'success';
            $earthVal = $niftyTrend ? (float)$niftyTrend->earth_value : 0;
            $trendDailyLabel = "{$dailyTrendCount} symbols calculated (NIFTY Earth: {$earthVal})";
        } else {
            if ($currentTime > '09:09' && $isWeekday) {
                $trendDailyStatus = 'danger';
                $trendDailyLabel = 'Daily trend not populated for today';
            } else {
                $trendDailyStatus = 'pending';
                $trendDailyLabel = 'Scheduled at 09:09';
            }
        }

        // 7. Index Open & ATM Strike Check
        $hasIndexOpen = $niftyTrend && !empty($niftyTrend->current_day_index_open) && (float)$niftyTrend->current_day_index_open > 0;
        $indexOpenPrice = $niftyTrend ? (float)$niftyTrend->current_day_index_open : null;
        $atmIndexOpen = $niftyTrend ? (float)$niftyTrend->atm_index_open : null;
        $earthHigh = $niftyTrend ? (float)$niftyTrend->earth_high : null;
        $earthLow = $niftyTrend ? (float)$niftyTrend->earth_low : null;

        $indexOpenStatus = 'pending';
        $indexOpenLabel = 'Pending';
        if ($hasIndexOpen) {
            $indexOpenStatus = 'success';
            $indexOpenLabel = "NIFTY Open: " . number_format($indexOpenPrice, 2) . " | ATM: " . number_format($atmIndexOpen, 0) . " (EH: " . number_format($earthHigh, 1) . " / EL: " . number_format($earthLow, 1) . ")";
        } else {
            if ($currentTime > '09:13' && $isWeekday) {
                $indexOpenStatus = 'danger';
                $indexOpenLabel = 'Index Open missing in daily_trend';
            } else {
                $indexOpenStatus = 'pending';
                $indexOpenLabel = 'Scheduled at 09:13';
            }
        }

        // 8. Daily Strategy Panels Check
        $todayPanelsCount = DB::table('strategy_panels')->whereDate('created_at', $today)->count();
        $todayLegsCount = DB::table('strategy_panel_legs')->whereDate('created_at', $today)->count();

        $strategiesStatus = 'pending';
        $strategiesLabel = 'Pending';
        if ($todayPanelsCount > 0) {
            $strategiesStatus = 'success';
            $strategiesLabel = "{$todayPanelsCount} Strategy Panels generated ({$todayLegsCount} Legs)";
        } else {
            if ($currentTime > '09:14' && $isWeekday) {
                $strategiesStatus = 'danger';
                $strategiesLabel = 'No strategy panels generated for today';
            } else {
                $strategiesStatus = 'pending';
                $strategiesLabel = 'Scheduled at 09:14';
            }
        }

        // 9. Live 1-Minute OHLC Stream Check
        $latestOhlc = DB::table('ohlc_quotes')->latest('id')->first();
        $candlesTodayCount = DB::table('ohlc_quotes')->whereDate('created_at', $today)->count();
        $latestOhlcTime = null;
        $latestOhlcDiffMinutes = null;
        $ohlcStreamStatus = 'pending';
        $ohlcStreamLabel = 'Idle';

        if ($latestOhlc) {
            $tsString = $latestOhlc->ts_at ?? $latestOhlc->created_at;
            $latestOhlcTime = Carbon::parse($tsString, 'Asia/Kolkata');
            $latestOhlcDiffMinutes = $latestOhlcTime->diffInMinutes($now);

            if ($latestOhlcTime->isToday()) {
                if ($session === 'open') {
                    if ($latestOhlcDiffMinutes <= 3) {
                        $ohlcStreamStatus = 'success';
                        $ohlcStreamLabel = "Streaming active (Last candle: {$latestOhlcTime->format('h:i:s A')}, " . number_format($candlesTodayCount) . " candles today)";
                    } else {
                        $ohlcStreamStatus = 'danger';
                        $ohlcStreamLabel = "Delayed: Last candle was {$latestOhlcDiffMinutes} min ago ({$latestOhlcTime->format('h:i:s A')})";
                    }
                } else {
                    $ohlcStreamStatus = 'success';
                    $ohlcStreamLabel = "Completed for session (" . number_format($candlesTodayCount) . " candles recorded, last at {$latestOhlcTime->format('h:i:s A')})";
                }
            } else {
                if ($session === 'open') {
                    $ohlcStreamStatus = 'danger';
                    $ohlcStreamLabel = "No candles collected today! Last: {$latestOhlcTime->format('d M h:i A')}";
                } else {
                    $ohlcStreamStatus = 'info';
                    $ohlcStreamLabel = "Waiting for market open at 09:15";
                }
            }
        } else {
            $ohlcStreamStatus = $session === 'open' ? 'danger' : 'pending';
            $ohlcStreamLabel = 'No candles in database';
        }

        // 10. Nifty 50 Stocks 1m Intraday Sync
        $niftyStocksLog = storage_path('logs/nifty50-stocks-sync.log');
        $niftyStocksLogTime = File::exists($niftyStocksLog) ? File::lastModified($niftyStocksLog) : null;
        $niftyStocksStatus = 'pending';
        $niftyStocksLabel = 'Scheduled every 5m';

        if ($niftyStocksLogTime) {
            $diffMinutes = round((time() - $niftyStocksLogTime) / 60);
            $logDate = date('Y-m-d', $niftyStocksLogTime);

            if ($logDate === $today) {
                if ($session === 'open') {
                    if ($diffMinutes <= 8) {
                        $niftyStocksStatus = 'success';
                        $niftyStocksLabel = "Sync active (Last sync {$diffMinutes}m ago at " . date('h:i A', $niftyStocksLogTime) . ")";
                    } else {
                        $niftyStocksStatus = 'warning';
                        $niftyStocksLabel = "Last sync {$diffMinutes}m ago at " . date('h:i A', $niftyStocksLogTime);
                    }
                } else {
                    $niftyStocksStatus = 'success';
                    $niftyStocksLabel = "Synced today (Last at " . date('h:i A', $niftyStocksLogTime) . ")";
                }
            } else {
                $niftyStocksStatus = $session === 'open' ? 'danger' : 'pending';
                $niftyStocksLabel = "Last sync was on {$logDate}";
            }
        }

        // 11. EOD & Archival Jobs
        $agg5mLog = storage_path('logs/ohlc-aggregate-daily-5m.log');
        $agg5mLogTime = File::exists($agg5mLog) ? File::lastModified($agg5mLog) : null;
        $agg5mDoneToday = $agg5mLogTime && date('Y-m-d', $agg5mLogTime) === $today;

        $archiveLog = storage_path('logs/option-chains-archive.log');
        $archiveLogTime = File::exists($archiveLog) ? File::lastModified($archiveLog) : null;
        $archiveDoneToday = $archiveLogTime && date('Y-m-d', $archiveLogTime) === $today;

        // Structured Pre-Market Jobs List (The Core requirement)
        $preMarketJobs = [
            [
                'id'             => 'working_days',
                'name'           => 'NSE Working Day Flags',
                'time'           => '08:50 AM',
                'command'        => 'nse:update-working-day-flags',
                'status'         => $workingDaysStatus,
                'status_label'   => $workingDaysLabel,
                'description'    => 'Designates current trading date and previous reference date in nse_working_days.',
                'log_file'       => 'update-working-day-flags.log',
                'last_log'       => $this->getLastLogSnippet('update-working-day-flags.log'),
                'verified_value' => "Current: " . ($currentTradingDate ?: 'None') . " | Previous: " . ($previousTradingDate ?: 'None'),
                'critical'       => true,
            ],
            [
                'id'             => 'instruments',
                'name'           => 'Upstox Instrument Master',
                'time'           => '08:52 AM',
                'command'        => 'upstox:fetch-instruments',
                'status'         => $instrumentsStatus,
                'status_label'   => $instrumentsLabel,
                'description'    => 'Downloads and indexes complete contract master keys, lot sizes, and strike identifiers.',
                'log_file'       => 'instruments.log',
                'last_log'       => $this->getLastLogSnippet('instruments.log'),
                'verified_value' => number_format($instrumentsCount) . ' instruments registered',
                'critical'       => false,
            ],
            [
                'id'             => 'expiries',
                'name'           => 'Benchmark Index Expiries',
                'time'           => '09:00 AM',
                'command'        => 'expiries:update-benchmarks',
                'status'         => $expiriesStatus,
                'status_label'   => $expiriesLabel,
                'description'    => 'Updates current and next weekly/monthly benchmark expiries for NIFTY, BANKNIFTY, SENSEX.',
                'log_file'       => 'expiry.log',
                'last_log'       => $this->getLastLogSnippet('expiry.log'),
                'verified_value' => "Active Expiry: " . ($currentNiftyOpt ? $currentNiftyOpt->expiry_date : 'Missing') . " ({$totalExpiries} total expiries)",
                'critical'       => true,
            ],
            [
                'id'             => 'daily_ohlc',
                'name'           => 'Previous Day OHLC Collection',
                'time'           => '09:01 AM',
                'command'        => 'indices:collect-daily-ohlc',
                'status'         => $dailyOhlcStatus,
                'status_label'   => $dailyOhlcLabel,
                'description'    => 'Fetches official previous trading day Open, High, Low, Close quotes for all index options.',
                'log_file'       => 'working-days.log',
                'last_log'       => $this->getLastLogSnippet('working-days.log'),
                'verified_value' => number_format($previousDayOhlcCount) . " contracts saved for {$previousDayDateUsed}",
                'critical'       => true,
            ],
            [
                'id'             => 'trend_daily',
                'name'           => 'Daily Trend & Earth Level Calculation',
                'time'           => '09:09 AM',
                'command'        => 'trend:populate-daily',
                'status'         => $trendDailyStatus,
                'status_label'   => $trendDailyLabel,
                'description'    => 'Calculates Earth Values, Support/Resistance ranges, and initial trend rows for today.',
                'log_file'       => 'populate-daily.log',
                'last_log'       => $this->getLastLogSnippet('populate-daily.log'),
                'verified_value' => $dailyTrendCount > 0 ? "{$dailyTrendCount} records active for {$today} (Earth: " . ($niftyTrend ? (float)$niftyTrend->earth_value : 0) . ")" : 'Not populated',
                'critical'       => true,
            ],
            [
                'id'             => 'index_open',
                'name'           => 'Index Open & ATM Strike Discovery',
                'time'           => '09:13 AM',
                'command'        => 'trend:update-index-open',
                'status'         => $indexOpenStatus,
                'status_label'   => $indexOpenLabel,
                'description'    => 'Fetches pre-market discovered opening index prices from Upstox and establishes ATM strikes.',
                'log_file'       => 'update-index-open.log',
                'last_log'       => $this->getLastLogSnippet('update-index-open.log'),
                'verified_value' => $hasIndexOpen ? "Open: {$indexOpenPrice} | ATM: {$atmIndexOpen} | EH: {$earthHigh} | EL: {$earthLow}" : 'Open not recorded',
                'critical'       => true,
            ],
            [
                'id'             => 'strategies',
                'name'           => 'Daily Strategy Panels Generation',
                'time'           => '09:14 AM',
                'command'        => 'trading:generate-daily-strategies',
                'status'         => $strategiesStatus,
                'status_label'   => $strategiesLabel,
                'description'    => 'Builds ATM, ITM, OTM, Straddle and Strangle execution panels based on today\'s opening ATM.',
                'log_file'       => 'generate-daily-strategies.log',
                'last_log'       => $this->getLastLogSnippet('generate-daily-strategies.log'),
                'verified_value' => "{$todayPanelsCount} Panels ({$todayLegsCount} Strategy Legs) generated",
                'critical'       => true,
            ],
        ];

        // Intraday & EOD Jobs List
        $intradayJobs = [
            [
                'id'             => 'ohlc_1min',
                'name'           => '1-Minute OHLC Live Stream Collector',
                'schedule'       => 'Every 1 min (09:15 - 15:35)',
                'command'        => 'ohlc:collect-1min',
                'status'         => $ohlcStreamStatus,
                'status_label'   => $ohlcStreamLabel,
                'description'    => 'Polls Upstox market quotes every minute and aggregates live candle price flow.',
                'log_file'       => 'ohlc-1min.log',
                'last_log'       => $this->getLastLogSnippet('ohlc-1min.log'),
                'verified_value' => number_format($candlesTodayCount) . ' candles today' . ($latestOhlcTime ? " (Last: {$latestOhlcTime->format('h:i:s A')})" : ''),
            ],
            [
                'id'             => 'nifty50_stocks',
                'name'           => 'NIFTY 50 Constituent Stocks Sync',
                'schedule'       => 'Every 5 mins (09:16 - 15:30)',
                'command'        => 'ohlc:sync-nifty50-stocks',
                'status'         => $niftyStocksStatus,
                'status_label'   => $niftyStocksLabel,
                'description'    => 'Syncs 1-minute intraday candles for all 50 constituent stocks for weightage & breadth analytics.',
                'log_file'       => 'nifty50-stocks-sync.log',
                'last_log'       => $this->getLastLogSnippet('nifty50-stocks-sync.log'),
                'verified_value' => $niftyStocksLogTime ? 'Last run: ' . date('h:i A', $niftyStocksLogTime) : 'Pending',
            ],
            [
                'id'             => 'aggregate_5m',
                'name'           => 'Aggregate Daily 5-Minute Candles',
                'schedule'       => 'Weekdays at 15:36',
                'command'        => 'ohlc:aggregate-daily-5m',
                'status'         => $agg5mDoneToday ? 'success' : ($currentTime > '15:36' && $isWeekday ? 'warning' : 'pending'),
                'status_label'   => $agg5mDoneToday ? 'Completed today' : ($currentTime > '15:36' && $isWeekday ? 'Pending execution' : 'Scheduled at 15:36'),
                'description'    => 'Permanently aggregates 1m intraday candles into historical 5m OHLC dataset.',
                'log_file'       => 'ohlc-aggregate-daily-5m.log',
                'last_log'       => $this->getLastLogSnippet('ohlc-aggregate-daily-5m.log'),
                'verified_value' => $agg5mDoneToday ? 'Archived today' : 'Pending',
            ],
            [
                'id'             => 'archive_chains',
                'name'           => 'Option Chains Daily Archive',
                'schedule'       => 'Daily at 15:40',
                'command'        => 'option-chains:archive',
                'status'         => $archiveDoneToday ? 'success' : ($currentTime > '15:40' && $isWeekday ? 'warning' : 'pending'),
                'status_label'   => $archiveDoneToday ? 'Archived today' : ($currentTime > '15:40' && $isWeekday ? 'Pending execution' : 'Scheduled at 15:40'),
                'description'    => 'Moves high-frequency intraday option chains to historical cold storage.',
                'log_file'       => 'option-chains-archive.log',
                'last_log'       => $this->getLastLogSnippet('option-chains-archive.log'),
                'verified_value' => $archiveDoneToday ? 'Archived today' : 'Pending',
            ],
        ];

        // Overall Health Assessment
        $preMarketCriticalTotal = count(array_filter($preMarketJobs, fn($j) => $j['critical']));
        $preMarketSuccessCount = count(array_filter($preMarketJobs, fn($j) => $j['critical'] && $j['status'] === 'success'));
        $missedCriticalJobs = array_filter($preMarketJobs, fn($j) => $j['critical'] && $j['status'] === 'danger');
        $preMarketAllReady = ($preMarketSuccessCount === $preMarketCriticalTotal);

        // Dependent Pages Readiness Matrix
        $pageReadiness = [
            [
                'name'        => 'Order Flow Cockpit & Strategies',
                'route'       => 'order-flow-depth.index',
                'category'    => 'Trading Cockpit',
                'is_ready'    => ($todayPanelsCount > 0 && $hasIndexOpen),
                'reason'      => ($todayPanelsCount > 0 && $hasIndexOpen)
                    ? '12 Strategy Panels and ATM discovery ready.'
                    : 'Missing today\'s ATM discovery or strategy panels generation.',
                'fix_command' => 'trading:generate-daily-strategies',
            ],
            [
                'name'        => 'Live Strike Chart & Delta Neutral',
                'route'       => 'live.strike.chart',
                'category'    => 'Monitors',
                'is_ready'    => ($expiriesStatus === 'success' && $hasIndexOpen),
                'reason'      => ($expiriesStatus === 'success' && $hasIndexOpen)
                    ? 'Active expiry identified with live strike mapping.'
                    : 'Missing active expiry date or ATM strike discovery.',
                'fix_command' => 'trend:update-index-open',
            ],
            [
                'name'        => 'Daily Trend & Earth Levels',
                'route'       => 'trend.index',
                'category'    => 'Trend & Levels',
                'is_ready'    => ($dailyTrendCount > 0 && $hasIndexOpen),
                'reason'      => ($dailyTrendCount > 0 && $hasIndexOpen)
                    ? 'Earth High, Earth Low, and ATM Resistance/Support active.'
                    : 'Missing index open or earth value in daily_trend.',
                'fix_command' => 'trend:update-index-open',
            ],
            [
                'name'        => 'HLC & Mid-Point Analysis',
                'route'       => 'hlc.index',
                'category'    => 'Trend & Levels',
                'is_ready'    => ($dailyTrendCount > 0),
                'reason'      => ($dailyTrendCount > 0)
                    ? 'Calculated from yesterday\'s OHLC.'
                    : 'Previous day OHLC not populated in daily_trend.',
                'fix_command' => 'trend:populate-daily',
            ],
            [
                'name'        => 'Option Chain View & Trending OI',
                'route'       => 'option-chain-view',
                'category'    => 'Option Analytics',
                'is_ready'    => ($expiriesStatus === 'success' && $instrumentsCount > 50000),
                'reason'      => ($expiriesStatus === 'success' && $instrumentsCount > 50000)
                    ? 'Instruments catalog & current weekly expiry linked.'
                    : 'Expiries or instrument definitions incomplete.',
                'fix_command' => 'expiries:update-benchmarks',
            ],
            [
                'name'        => 'NIFTY Weightage & Breadth Matrix',
                'route'       => 'nifty.weightage.monitor',
                'category'    => 'Market Breadth',
                'is_ready'    => ($candlesTodayCount > 0 || $niftyStocksStatus === 'success'),
                'reason'      => ($candlesTodayCount > 0 || $niftyStocksStatus === 'success')
                    ? '1m constituent candle sync active.'
                    : 'Constituent stock candles not streaming yet.',
                'fix_command' => 'ohlc:sync-nifty50-stocks',
            ],
        ];

        // Check for active/stale scheduler mutex locks
        $stuckLocks = $this->getScheduleLocks();

        return [
            'now'                    => $now,
            'today'                  => $today,
            'currentTime'            => $currentTime,
            'isWeekday'              => $isWeekday,
            'session'                => $session,
            'sessionLabel'           => $sessionLabel,
            'sessionColor'           => $sessionColor,
            'currentTradingDate'     => $currentTradingDate,
            'previousTradingDate'    => $previousTradingDate,
            'stuckLocks'             => $stuckLocks,
            'hasStuckLocks'          => !empty($stuckLocks),
            'preMarketJobs'          => $preMarketJobs,
            'intradayJobs'           => $intradayJobs,
            'preMarketCriticalTotal' => $preMarketCriticalTotal,
            'preMarketSuccessCount'  => $preMarketSuccessCount,
            'missedCriticalJobs'     => $missedCriticalJobs,
            'preMarketAllReady'      => $preMarketAllReady,
            'pageReadiness'          => $pageReadiness,
            'allowedCommands'        => $this->allowedCommands,
            'metrics'                => [
                'instrumentsCount'     => $instrumentsCount,
                'expiriesCount'        => $totalExpiries,
                'currentExpiry'        => $currentNiftyOpt ? $currentNiftyOpt->expiry_date : 'N/A',
                'previousOhlcCount'    => $previousDayOhlcCount,
                'dailyTrendCount'      => $dailyTrendCount,
                'niftyOpen'            => $indexOpenPrice,
                'atmStrike'            => $atmIndexOpen,
                'earthHigh'            => $earthHigh,
                'earthLow'             => $earthLow,
                'panelsCount'          => $todayPanelsCount,
                'legsCount'            => $todayLegsCount,
                'candlesCount'         => $candlesTodayCount,
                'latestCandleTime'     => $latestOhlcTime ? $latestOhlcTime->format('h:i:s A') : 'N/A',
            ],
        ];
    }

    /**
     * Efficiently read the last N lines of a file
     */
    protected function tailFile(string $filePath, int $lines = 10): array
    {
        if (!File::exists($filePath)) {
            return [];
        }

        $f = fopen($filePath, 'rb');
        if (!$f) {
            return [];
        }

        $buffer = '';
        $chunkSize = 4096;
        fseek($f, 0, SEEK_END);
        $fileSize = ftell($f);
        $pointer = $fileSize;

        $lineCount = 0;
        $output = '';

        while ($pointer > 0 && $lineCount <= $lines) {
            $readSize = min($chunkSize, $pointer);
            $pointer -= $readSize;
            fseek($f, $pointer);
            $chunk = fread($f, $readSize);
            $output = $chunk . $output;
            $lineCount = substr_count($output, "\n");
        }

        fclose($f);

        $allLines = explode("\n", trim($output));
        return array_slice($allLines, -$lines);
    }

    /**
     * Return a short snippet of the last 3-4 lines from a log file
     */
    protected function getLastLogSnippet(string $filename, int $lines = 4): string
    {
        $path = storage_path('logs/' . $filename);
        if (!File::exists($path)) {
            return 'No log file generated yet';
        }

        $tail = $this->tailFile($path, $lines);
        if (empty($tail)) {
            return 'Log is empty';
        }

        return implode("\n", array_filter(array_map('trim', $tail)));
    }

    /**
     * Inspect cache_locks table to detect any stuck schedule mutex locks
     */
    protected function getScheduleLocks(): array
    {
        try {
            $locks = DB::table('cache_locks')
                ->where('key', 'like', '%schedule%')
                ->get();

            if ($locks->isEmpty()) {
                return [];
            }

            // Map schedule events to commands using mutex names
            $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
            $eventMap = [];
            foreach ($schedule->events() as $event) {
                $cmd = $event->command;
                if (preg_match('/artisan"?\s+([a-zA-Z0-9\:\-_]+)/', $cmd, $matches)) {
                    $cmd = $matches[1];
                }
                $eventMap[$event->mutexName()] = $cmd;
            }

            $result = [];
            $now = time();
            foreach ($locks as $lock) {
                $matchedCommand = null;
                foreach ($eventMap as $mutexName => $cmd) {
                    if (str_contains($lock->key, $mutexName)) {
                        $matchedCommand = $cmd;
                        break;
                    }
                }

                $result[] = [
                    'key'                => $lock->key,
                    'owner'              => $lock->owner,
                    'expiration'         => $lock->expiration,
                    'expires_in_seconds' => max(0, $lock->expiration - $now),
                    'expires_at_human'   => date('d M Y, h:i:s A', $lock->expiration),
                    'command'            => $matchedCommand ?: 'Scheduled Task',
                ];
            }

            return $result;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
