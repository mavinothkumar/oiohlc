<?php

// app/Console/Commands/RunStrangleBacktest.php

namespace App\Console\Commands;

use App\Models\BacktestTrade;
use App\Services\Backtest\BacktestEngine;
use App\Services\Backtest\StrategyRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RunStrangleBacktest extends Command {
    protected $signature = 'backtest:strangle
        {strategy             : Strategy name. Run with --list to see all available.}
        {--symbol=NIFTY       : Underlying symbol}
        {--from=              : From date YYYY-MM-DD}
        {--to=                : To date YYYY-MM-DD}
        {--entry-time=09:20   : Entry time HH:MM}
        {--strike-offset=300  : Fixed offset from ATM (fixed_offset strategy)}
        {--target=7000        : Combined day profit target ₹}
        {--stoploss=5000      : Combined day stop loss ₹}
        {--lot=65             : Qty per leg}
        {--exchange=NSE       : Exchange}
        {--min-offset=300     : Smart balanced minimum offset}
        {--max-offset=600     : Smart balanced maximum offset}
        {--step=100           : Strike step size}
        {--list               : List all available strategies and exit}
        {--dry-run            : Preview without saving}';

    protected $description = 'Backtest 4-leg strangle/straddle strategies.';

    public function handle(): int {
        // ── List mode ──────────────────────────────────────────────────────
        if ( $this->option( 'list' ) ) {
            $this->info( 'Available strategies:' );
            foreach ( StrategyRegistry::available() as $name ) {
                $this->line( "  • {$name}" );
            }

            return self::SUCCESS;
        }

        $strategyName = strtolower( str_replace( ' ', '_', trim( $this->argument( 'strategy' ) ) ) );

        // ── Validate ───────────────────────────────────────────────────────
        if ( ! StrategyRegistry::exists( $strategyName ) ) {
            $this->error( "Unknown strategy: \"{$strategyName}\"" );
            $this->line( 'Run with --list to see all available strategies.' );

            return self::FAILURE;
        }

        $from = $this->option( 'from' );
        $to   = $this->option( 'to' );

        if ( ! $from || ! $to ) {
            $this->error( '--from and --to are required.' );

            return self::FAILURE;
        }

        // ── Boot ───────────────────────────────────────────────────────────
        $runId     = (string) Str::uuid();
        $symbol    = strtoupper( $this->option( 'symbol' ) );
        $entryHHMM = $this->option( 'entry-time' );
        $target    = (float) $this->option( 'target' );
        $stoploss  = (float) $this->option( 'stoploss' );
        $qty       = (int) $this->option( 'lot' );
        $exchange  = strtoupper( $this->option( 'exchange' ) );
        $dryRun    = $this->option( 'dry-run' );

        // All options passed through to strategy
        $options = $this->options();

        $strategy = StrategyRegistry::resolve( $strategyName );
        $engine   = new BacktestEngine();

        $this->printHeader( $symbol, $from, $to, $entryHHMM, $target, $stoploss,
            $qty, $runId, $dryRun, $strategyName,
            $strategy->describe( $options ) );



        // ── Trading dates ──────────────────────────────────────────────────
        $tradingDates = DB::table( 'expired_ohlc' )
                          ->selectRaw( 'DATE(timestamp) as trade_date' )
                          ->where( 'underlying_symbol', $symbol )
                          ->where( 'instrument_type', 'INDEX' )
                          ->where( 'interval', '5minute' )
                          ->whereBetween( DB::raw( 'DATE(timestamp)' ), [ $from, $to ] )
                          ->groupBy( DB::raw( 'DATE(timestamp)' ) )
                          ->orderBy( 'trade_date' )
                          ->pluck( 'trade_date' )
                          ->toArray();

        if ( empty( $tradingDates ) ) {
            $this->warn( 'No INDEX data found for the given range.' );

            return self::FAILURE;
        }

        $this->info( 'Found ' . count( $tradingDates ) . " trading days.\n" );

        $bar = $this->output->createProgressBar( count( $tradingDates ) );
        $bar->setFormat( ' %current%/%max% [%bar%] %percent:3s%% | %message%' );
        $bar->start();

        $profitDays    = 0;
        $lossDays      = 0;
        $profitMinutes = [];
        $lossMinutes   = [];
        $totalPnlSum   = 0;
        $skippedDays   = 0;

        // ── Load all expiries for the symbol once ──────────────────────────
//        $allExpiries = DB::table( 'nse_expiries' )
//                         ->where( 'trading_symbol', $symbol )
//                         ->where( 'instrument_type', 'OPT' )
//                         ->orderBy( 'expiry_date' )
//                         ->pluck( 'expiry_date' )
//                         ->toArray();
//
//        if ( empty( $allExpiries ) ) {
//            $this->error( "No expiries found for {$symbol} in nse_expiries." );
//
//            return self::FAILURE;
//        }


        foreach ( $tradingDates as $tradeDate ) {

            $bar->setMessage( $tradeDate );
            $bar->advance();

            $entryTimestamp = "{$tradeDate} {$entryHHMM}:00";

            // ── Index candle ───────────────────────────────────────────────
            $indexCandle = DB::table( 'expired_ohlc' )
                             ->where( 'underlying_symbol', $symbol )
                             ->where( 'instrument_type', 'INDEX' )
                             ->where( 'interval', '5minute' )
                             ->where( 'timestamp', $entryTimestamp )
                             ->first()
                           ?? DB::table( 'expired_ohlc' )
                                ->where( 'underlying_symbol', $symbol )
                                ->where( 'instrument_type', 'INDEX' )
                                ->where( 'interval', '5minute' )
                                ->whereBetween( 'timestamp', [
                                    Carbon::parse( $entryTimestamp )->subMinutes( 5 )->toDateTimeString(),
                                    Carbon::parse( $entryTimestamp )->addMinutes( 5 )->toDateTimeString(),
                                ] )
                                ->orderBy( 'timestamp' )
                                ->first();

            if ( ! $indexCandle ) {
                $skippedDays ++;
                continue;
            }

            $indexOpen = (float) $indexCandle->open;

            // ── Expiry ─────────────────────────────────────────────────────
            $expiry = DB::table('expired_ohlc')
                        ->where('underlying_symbol', $symbol)
                        ->whereIn('instrument_type', ['CE', 'PE'])
                        ->where('interval', '5minute')
                        ->whereDate('timestamp', $tradeDate)
                        ->whereNotNull('expiry')
                        ->orderByRaw("ABS(DATEDIFF(expiry, ?))", [$tradeDate])
                        ->value('expiry');

            //$expiry = resolveExpiry( $tradeDate, $allExpiries );


            if ( ! $expiry ) {
                $this->warn( "  No expiry found for {$tradeDate} — skipping." );
                $skippedDays ++;
                continue;
            }

            // ── Strategy resolves the legs ─────────────────────────────────
            $legData = $strategy->resolveLegs(
                $symbol, $indexOpen, $tradeDate, $entryTimestamp, $options
            );

            if ( ! $legData ) {
                $skippedDays ++;
                continue;
            }

            // ── Engine walks the candles ───────────────────────────────────
            $result = $engine->run(
                $legData, $entryTimestamp, $tradeDate, $target, $stoploss, $qty
            );

            $legData          = $result['legData'];
            $dayOutcome       = $result['dayOutcome'];
            $exitReason       = $result['exitReason'];
            $dayMaxProfit     = $result['dayMaxProfit'];
            $dayMaxProfitTime = $result['dayMaxProfitTime'];
            $dayMaxLoss       = $result['dayMaxLoss'];
            $dayMaxLossTime   = $result['dayMaxLossTime'];
            $dayExitTime      = $result['dayExitTime'];

            // ── Day totals ─────────────────────────────────────────────────
            $dayTotalPnl = round( array_sum( array_map(
                fn( $leg ) => ( $leg['entry_price'] - ( $leg['exit_price'] ?? $leg['entry_price'] ) ) * $qty,
                $legData
            ) ), 2 );

            if ( $dayOutcome === 'open' ) {
                $dayOutcome = $dayTotalPnl >= 0 ? 'profit' : 'loss';
            }

            $dayDuration = $dayExitTime
                ? (int) Carbon::parse( $entryTimestamp )->diffInMinutes( Carbon::parse( $dayExitTime ) )
                : null;

            $dayOutcome === 'profit'
                ? ( $profitDays ++ && ( $dayDuration ? $profitMinutes[] = $dayDuration : null ) )
                : ( $lossDays ++ && ( $dayDuration ? $lossMinutes[] = $dayDuration : null ) );

            $totalPnlSum += $dayTotalPnl;

            // ── Insert immediately ─────────────────────────────────────────
            $dayGroupId = (string) Str::uuid();
            $now        = now()->toDateTimeString();

            $dayRows = array_map( fn( $leg ) => [
                'underlying_symbol'    => $symbol,
                'instrument_type'      => $leg['type'],
                'exchange'             => $exchange,
                'expiry'               => $expiry,
                'instrument_key'       => $leg['instrument_key'],
                'strike'               => $leg['strike'],
                'entry_price'          => $leg['entry_price'],
                'exit_price'           => $leg['exit_price'],
                'side'                 => 'SELL',
                'qty'                  => $qty,
                'pnl'                  => round( ( $leg['entry_price'] - ( $leg['exit_price'] ?? $leg['entry_price'] ) ) * $qty, 2 ),
                'strategy'             => $strategyName,
                'entry_time'           => $entryTimestamp,
                'exit_time'            => $leg['exit_time'],
                'trade_time_duration'  => $leg['exit_time']
                    ? (int) Carbon::parse( $entryTimestamp )->diffInMinutes( Carbon::parse( $leg['exit_time'] ) )
                    : null,
                'outcome'              => ( round( ( $leg['entry_price'] - ( $leg['exit_price'] ?? $leg['entry_price'] ) ) * $qty, 2 ) ) >= 0 ? 'profit' : 'loss',
                'trade_date'           => $tradeDate,
                'backtest_run_id'      => $runId,
                'day_group_id'         => $dayGroupId,
                'day_total_pnl'        => $dayTotalPnl,
                'day_outcome'          => $dayOutcome,
                'day_max_profit'       => $dayMaxProfit,
                'day_max_profit_time'  => $dayMaxProfitTime,
                'day_max_loss'         => $dayMaxLoss,
                'day_max_loss_time'    => $dayMaxLossTime,
                'index_price_at_entry' => $indexOpen,
                'target'               => $target,
                'stoploss'             => $stoploss,
                'lot_size'             => $qty,
                'strike_offset'        => (int) ( $options['strike-offset'] ?? 300 ),
                'created_at'           => $now,
                'updated_at'           => $now,
            ], $legData );

            if ( ! $dryRun ) {
                BacktestTrade::insert($dayRows);
            }

            // Console line
            $pnl = ( $dayTotalPnl >= 0 ? '+₹' : '-₹' ) . number_format( abs( $dayTotalPnl ), 0 );
            $tag = $dayOutcome === 'profit' ? "<fg=green>✓ {$pnl}</>" : "<fg=red>✗ {$pnl}</>";
            $this->line( "  <fg=gray>{$tradeDate}</> | {$tag} | {$exitReason}" );
        }

        $bar->finish();
        $this->newLine( 2 );

        $this->printSummary( $runId, $profitDays + $lossDays, $profitDays, $lossDays,
            $skippedDays, $totalPnlSum,
            ! empty( $profitMinutes ) ? round( array_sum( $profitMinutes ) / count( $profitMinutes ), 1 ) : 0,
            ! empty( $lossMinutes ) ? round( array_sum( $lossMinutes ) / count( $lossMinutes ), 1 ) : 0,
            $dryRun
        );

        return self::SUCCESS;
    }

    private function printHeader(
        string $symbol,
        string $from,
        string $to,
        string $entryHHMM,
        float $target,
        float $stoploss,
        int $qty,
        string $runId,
        bool $dryRun,
        string $strategyName,
        string $strategyDesc
    ): void {
        $this->info( "╔══════════════════════════════════════════════════════════════╗" );
        $this->info( "║         🔄  STRANGLE BACKTEST  (4-Leg Combined Exit)        ║" );
        $this->info( "╠══════════════════════════════════════════════════════════════╣" );
        $this->info( sprintf( "║  Strategy       : %-43s║", $strategyName ) );
        $this->info( sprintf( "║  Strike Mode    : %-43s║", $strategyDesc ) );
        $this->info( sprintf( "║  Symbol         : %-43s║", $symbol ) );
        $this->info( sprintf( "║  Date Range     : %-43s║", "$from  →  $to" ) );
        $this->info( sprintf( "║  Entry Time     : %-43s║", $entryHHMM ) );
        $this->info( sprintf( "║  Target / SL    : %-43s║", "₹$target / ₹$stoploss" ) );
        $this->info( sprintf( "║  Qty per leg    : %-43s║", $qty ) );
        $this->info( sprintf( "║  Run ID         : %-43s║", $runId ) );
        $this->info( sprintf( "║  Dry Run        : %-43s║", $dryRun ? 'YES' : 'NO' ) );
        $this->info( "╚══════════════════════════════════════════════════════════════╝\n" );
    }

    private function printSummary(
        string $runId,
        int $totalDays,
        int $profitDays,
        int $lossDays,
        int $skippedDays,
        float $totalPnl,
        float $avgProfitWait,
        float $avgLossWait,
        bool $dryRun
    ): void {
        $winRate = $totalDays > 0 ? round( $profitDays / $totalDays * 100, 2 ) : 0;
        $this->info( "╔══════════════════════════════════════════════════════════════╗" );
        $this->info( "║                    📊  BACKTEST SUMMARY                     ║" );
        $this->info( "╠══════════════════════════════════════════════════════════════╣" );
        $this->info( sprintf( "║  Total Days Processed  : %-35s║", $totalDays ) );
        $this->info( sprintf( "║  Skipped Days          : %-35s║", $skippedDays ) );
        $this->info( sprintf( "║  Profit Days           : %-35s║", $profitDays ) );
        $this->info( sprintf( "║  Loss Days             : %-35s║", $lossDays ) );
        $this->info( sprintf( "║  Win Rate              : %-35s║", "$winRate%" ) );
        $this->info( sprintf( "║  Total P&L             : %-35s║", "₹" . number_format( $totalPnl, 0 ) ) );
        $this->info( sprintf( "║  Avg Wait — Profit Day : %-35s║", "$avgProfitWait min" ) );
        $this->info( sprintf( "║  Avg Wait — Loss Day   : %-35s║", "$avgLossWait min" ) );
        $this->info( sprintf( "║  Run ID                : %-35s║", $runId ) );
        $this->info( "╚══════════════════════════════════════════════════════════════╝" );
        $dryRun
            ? $this->warn( "\n[DRY RUN] Nothing was saved." )
            : $this->info( "\n✅  Done. View at /backtest?run_id={$runId}" );
    }
}
