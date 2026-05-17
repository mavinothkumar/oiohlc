<?php

namespace App\Console\Commands;

use App\Models\BacktestTrade;
use App\Services\Backtest\WeeklyEngine;
use App\Services\Backtest\StrategyRegistry;
use App\Services\Backtest\Contracts\BacktestStrategy;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RunWeeklyBacktest extends Command {
    protected $signature = 'backtest:weekly
        {strategy : Strategy key. Use --list to see all.}
        {--symbol=NIFTY}
        {--from= : From date YYYY-MM-DD}
        {--to= : To date YYYY-MM-DD}
        {--entry-time=09:20 : Entry time HH:MM (first day of week)}
        {--strike-offset=600 : Offset from ATM for sell strikes}
        {--sell-lots=2 : Lots per sell leg}
        {--hedge-lots=8 : Lots per hedge leg}
        {--hedge-price=10 : Target hedge premium in ₹}
       {--hedge-max-price=50 : Max acceptable hedge premium}
        {--hedge-search-steps=10 : Max strikes to walk looking for hedge}
        {--target=100000 : Weekly profit target ₹}
        {--stoploss=30000 : Weekly stoploss ₹}
        {--leg-double-pct=100 : Exit all if any SELL leg rises by this %}
        {--trailing-lock-pct=60 : Lock SL to breakeven once P&L hits this % of target}
        {--gap-shift-threshold=100 : Shift condor if gap_abs >= this}
        {--gap-skip-threshold=300 : Skip week entirely if gap_abs >= this}
        {--step=50 : Strike step size}
        {--exchange=NSE}
        {--dry-run : Preview without saving}
        {--verbose-weeks : Print each timestamp evaluation}
        {--list : List available strategies}';

    protected $description = 'Backtest weekly hold strategies (Iron Condor, etc.)';

    public function handle(): int {
        if ( $this->option( 'list' ) ) {
            $this->info( 'Available strategies:' );
            foreach ( StrategyRegistry::available() as $name ) {
                $this->line( "  • {$name}" );
            }

            return self::SUCCESS;
        }

        $strategyName = strtolower( str_replace( ' ', '_', trim( $this->argument( 'strategy' ) ) ) );

        if ( ! StrategyRegistry::exists( $strategyName ) ) {
            $this->error( "Unknown strategy: \"{$strategyName}\"" );

            return self::FAILURE;
        }

        $from = $this->option( 'from' );
        $to   = $this->option( 'to' );

        if ( ! $from || ! $to ) {
            $this->error( '--from and --to are required.' );

            return self::FAILURE;
        }

        $symbol    = strtoupper( $this->option( 'symbol' ) );
        $entryHHMM = $this->option( 'entry-time' );
        $target    = (float) $this->option( 'target' );
        $stoploss  = (float) $this->option( 'stoploss' );
        $exchange  = strtoupper( $this->option( 'exchange' ) );
        $dryRun    = $this->option( 'dry-run' );
        $verbose   = $this->option( 'verbose-weeks' );
        $runId     = (string) Str::uuid();
        $options   = $this->options();

        $strategy = StrategyRegistry::resolve( $strategyName );
        $engine   = new WeeklyEngine();

        // ── Load all expiries ─────────────────────────────────────────────
        $allExpiries = DB::table( 'expired_expiries' )
                         ->where( 'underlying_symbol', $symbol )
                         ->where( 'instrument_type', 'OPT' )
                         ->whereBetween( 'expiry_date', [ $from, $to ] )
                         ->orderBy( 'expiry_date' )
                         ->pluck( 'expiry_date' )
                         ->toArray();

        if ( empty( $allExpiries ) ) {
            $this->error( "No expiries found for {$symbol} between {$from} and {$to}." );

            return self::FAILURE;
        }

        // ── Load all trading dates ────────────────────────────────────────
        $allTradingDates = DB::table( 'expired_ohlc' )
                             ->selectRaw( 'DATE(timestamp) as trade_date' )
                             ->where( 'underlying_symbol', $symbol )
                             ->where( 'instrument_type', 'INDEX' )
                             ->where( 'interval', '5minute' )
                             ->whereBetween( DB::raw( 'DATE(timestamp)' ), [ $from, $to ] )
                             ->groupBy( DB::raw( 'DATE(timestamp)' ) )
                             ->orderBy( 'trade_date' )
                             ->pluck( 'trade_date' )
                             ->toArray();

        // ── Group trading dates by expiry week ────────────────────────────
        $weeks = $this->buildWeekMap( $symbol, $from, $to );

        $this->printHeader( $symbol, $from, $to, $entryHHMM, $target, $stoploss, $runId, $dryRun, $strategyName, $strategy->describe( $options ) );
        $this->info( 'Found ' . count( $weeks ) . " expiry weeks.\n" );

        $profitWeeks  = 0;
        $lossWeeks    = 0;
        $skippedWeeks = 0;
        $skipReasons  = [];
        $totalPnlSum  = 0;

        foreach ( $weeks as $expiry => $entryDate ) {
            $entryTimestamp = "{$entryDate} {$entryHHMM}:00";

            // ── Index candle ──────────────────────────────────────────────
            $indexCandle = DB::table( 'expired_ohlc' )
                             ->where( 'underlying_symbol', $symbol )
                             ->where( 'instrument_type', 'INDEX' )
                             ->where( 'interval', '5minute' )
                             ->where( 'timestamp', $entryTimestamp )
                             ->first();

            if ( ! $indexCandle ) {
                $this->line( "  ⊘ SKIP {$entryDate} expiry={$expiry} — no_index_candle" );
                $skippedWeeks ++;
                $skipReasons['no_index_candle'] = ( $skipReasons['no_index_candle'] ?? 0 ) + 1;
                continue;
            }

            $indexOpen = (float) $indexCandle->open;

            // ── Strategy resolves legs ────────────────────────────────────
            $legData = $strategy->resolveLegs(
                $symbol, $indexOpen, $entryDate, $entryTimestamp, $options
            );

            if ( $legData === null || BacktestStrategy::isSkip( $legData ) ) {
                $reason = BacktestStrategy::isSkip( $legData )
                    ? BacktestStrategy::skipReason( $legData )
                    : 'unknown_filter';
                $skippedWeeks ++;
                $skipReasons[ $reason ] = ( $skipReasons[ $reason ] ?? 0 ) + 1;
                $this->line( "  ⊘ SKIP {$entryDate} expiry={$expiry} — {$reason}" );
                continue;
            }

            // ── Weekly engine runs across full expiry week ────────────────
            $result = $engine->run(
                $legData, $entryTimestamp, $entryDate,
                $target, $stoploss,
                (int) ( $options['sell-lots'] ?? 2 ),
                $options
            );

            $legData      = $result['legData'];
            $dayOutcome   = $result['dayOutcome'];
            $exitReason   = $result['exitReason'];
            $dayExitTime  = $result['dayExitTime'];
            $dayMaxProfit = $result['dayMaxProfit'];
            $dayMaxLoss   = $result['dayMaxLoss'];

            // ── Compute realised P&L ──────────────────────────────────────
            $weekPnl = 0;
            foreach ( $legData as $leg ) {
                $qty     = $leg['qty_override'] ?? (int) ( $options['sell-lots'] ?? 2 );
                $legPnl  = $leg['side'] === 'BUY'
                    ? ( ( $leg['exit_price'] ?? $leg['entry_price'] ) - $leg['entry_price'] ) * $qty
                    : ( $leg['entry_price'] - ( $leg['exit_price'] ?? $leg['entry_price'] ) ) * $qty;
                $weekPnl += $legPnl;
            }
            $weekPnl = round( $weekPnl, 2 );

            if ( $dayOutcome === 'open' ) {
                $dayOutcome = $weekPnl >= 0 ? 'profit' : 'loss';
            }

            $dayOutcome === 'profit' ? $profitWeeks ++ : $lossWeeks ++;
            $totalPnlSum += $weekPnl;

            $pnlTag = ( $weekPnl >= 0 ? '+₹' : '-₹' ) . number_format( abs( $weekPnl ), 0 );
            $icon   = $dayOutcome === 'profit' ? '✓' : '✗';
            $this->line( "  {$icon} {$entryDate}→{$expiry} | {$pnlTag} | {$exitReason}" );

            if ( $dryRun ) {
                continue;
            }

            // ── Insert rows ───────────────────────────────────────────────
            $dayGroupId = (string) Str::uuid();
            $now        = now()->toDateTimeString();

            $ceStrike = collect( $legData )->where( 'type', 'CE' )->where( 'side', 'SELL' )->first()['strike'] ?? null;
            $peStrike = collect( $legData )->where( 'type', 'PE' )->where( 'side', 'SELL' )->first()['strike'] ?? null;

            $gapRow = DB::table( 'index_gap' )
                        ->where( 'symbol_name', $symbol )
                        ->whereDate( 'trading_date', $entryDate )
                        ->first();

            $rows = array_map( fn( $leg ) => [
                'underlying_symbol'    => $symbol,
                'instrument_type'      => $leg['type'],
                'exchange'             => $exchange,
                'expiry'               => $expiry,
                'instrument_key'       => $leg['instrument_key'],
                'strike'               => $leg['strike'],
                'ce_strike'            => $ceStrike,
                'pe_strike'            => $peStrike,
                'entry_price'          => $leg['entry_price'],
                'exit_price'           => $leg['exit_price'],
                'side'                 => $leg['side'],
                'qty'                  => $leg['qty_override'] ?? (int) ( $options['sell-lots'] ?? 2 ),
                'pnl'                  => $leg['side'] === 'BUY'
                    ? round( ( ( $leg['exit_price'] ?? $leg['entry_price'] ) - $leg['entry_price'] ) * ( $leg['qty_override'] ?? 2 ), 2 )
                    : round( ( $leg['entry_price'] - ( $leg['exit_price'] ?? $leg['entry_price'] ) ) * ( $leg['qty_override'] ?? 2 ), 2 ),
                'lot_size'             => $leg['qty_override'] ?? (int) ( $options['sell-lots'] ?? 2 ),
                'strategy'             => $strategyName,
                'entry_time'           => $entryTimestamp,
                'exit_time'            => $leg['exit_time'],
                'signal_time'          => null,
                'trade_time_duration'  => $leg['exit_time']
                    ? (int) Carbon::parse( $entryTimestamp )->diffInMinutes( Carbon::parse( $leg['exit_time'] ) )
                    : null,
                'outcome'              => $dayOutcome,
                'trade_date'           => $entryDate,
                'backtest_run_id'      => $runId,
                'day_group_id'         => $dayGroupId,
                'day_total_pnl'        => $weekPnl,
                'day_outcome'          => $dayOutcome,
                'day_max_profit'       => $dayMaxProfit,
                'day_max_profit_time'  => $result['dayMaxProfitTime'],
                'day_max_loss'         => $dayMaxLoss,
                'day_max_loss_time'    => $result['dayMaxLossTime'],
                'index_price_at_entry' => $indexOpen,
                'target'               => $target,
                'stoploss'             => $stoploss,
                'strike_offset'        => (int) ( $options['strike-offset'] ?? 600 ),
                'gap_used'             => $gapRow?->gap_abs,
                'previous_day_range'   => $gapRow?->previous_day_range,
                'gap_pct_prev_range'   => $gapRow?->gap_pct_prev_range,
                'created_at'           => $now,
                'updated_at'           => $now,
            ], $legData );

            BacktestTrade::insert( $rows );
        }

        $this->newLine();
        $this->printSummary( $runId, $profitWeeks + $lossWeeks, $profitWeeks, $lossWeeks, $skippedWeeks, $skipReasons, $totalPnlSum, $dryRun );

        return self::SUCCESS;
    }

    /**
     * Build week map: expiry_date => entry_date
     *
     * Entry date = first NSE working day AFTER the previous expiry.
     * If no previous expiry exists, use the first working day >= --from.
     */
    private function buildWeekMap( string $symbol, string $from, string $to ): array {
        // Load all OPT expiries for the symbol ordered ascending
        $expiries = DB::table( 'expired_expiries' )
                      ->where( 'underlying_symbol', $symbol )
                      ->where( 'instrument_type', 'OPT' )
                      ->whereBetween( 'expiry_date', [ $from, $to ] )
                      ->orderBy( 'expiry_date' )
                      ->pluck( 'expiry_date' )
                      ->toArray();

        if ( empty( $expiries ) ) {
            return [];
        }

        // Load all NSE working days in range (with a buffer before $from)
        $workingDays = DB::table( 'nse_working_days' )
                         ->where( 'working_date', '>=',
                             // buffer 30 days before $from to catch previous expiry's next day
                             Carbon::parse( $from )->subDays( 30 )->toDateString()
                         )
                         ->where( 'working_date', '<=', $to )
                         ->orderBy( 'working_date' )
                         ->pluck( 'working_date' )
                         ->toArray();

        $weeks = [];

        foreach ( $expiries as $i => $expiry ) {
            // The "start" of this expiry cycle = day after previous expiry
            $prevExpiry = $expiries[ $i - 1 ] ?? null;

            if ( $prevExpiry ) {
                // First working day strictly after previous expiry
                $entryDate = $this->firstWorkingDayAfter( $prevExpiry, $workingDays );
            } else {
                // First expiry in range: first working day >= $from
                $entryDate = $this->firstWorkingDayOnOrAfter( $from, $workingDays );
            }

            // Entry date must be within the backtest range and before this expiry
            if ( ! $entryDate || $entryDate > $to || $entryDate >= $expiry ) {
                continue;
            }

            $weeks[ $expiry ] = $entryDate;
        }

        return $weeks;
    }

    /** First working day strictly AFTER $afterDate */
    private function firstWorkingDayAfter( string $afterDate, array $workingDays ): ?string {
        foreach ( $workingDays as $day ) {
            if ( $day > $afterDate ) {
                return $day;
            }
        }

        return null;
    }

    /** First working day on or after $onOrAfterDate */
    private function firstWorkingDayOnOrAfter( string $onOrAfterDate, array $workingDays ): ?string {
        foreach ( $workingDays as $day ) {
            if ( $day >= $onOrAfterDate ) {
                return $day;
            }
        }

        return null;
    }

    private function printHeader(
        string $symbol,
        string $from,
        string $to,
        string $entryHHMM,
        float $target,
        float $stoploss,
        string $runId,
        bool $dryRun,
        string $strategyName,
        string $strategyDesc
    ): void {
        $this->info( "╔══════════════════════════════════════════════════════════════╗" );
        $this->info( "║         📅  WEEKLY BACKTEST  (Hold-Until-Expiry)            ║" );
        $this->info( "╠══════════════════════════════════════════════════════════════╣" );
        $this->info( sprintf( "║  Strategy       : %-43s║", $strategyName ) );
        $this->info( sprintf( "║  Description    : %-43s║", $strategyDesc ) );
        $this->info( sprintf( "║  Symbol         : %-43s║", $symbol ) );
        $this->info( sprintf( "║  Date Range     : %-43s║", "$from  →  $to" ) );
        $this->info( sprintf( "║  Entry Time     : %-43s║", $entryHHMM ) );
        $this->info( sprintf( "║  Target / SL    : %-43s║", "₹" . number_format( $target, 0 ) . " / ₹" . number_format( $stoploss, 0 ) ) );
        $this->info( sprintf( "║  Run ID         : %-43s║", $runId ) );
        $this->info( sprintf( "║  Dry Run        : %-43s║", $dryRun ? 'YES' : 'NO' ) );
        $this->info( "╚══════════════════════════════════════════════════════════════╝\n" );
    }

    private function printSummary(
        string $runId,
        int $totalWeeks,
        int $profitWeeks,
        int $lossWeeks,
        int $skippedWeeks,
        array $skipReasons,
        float $totalPnl,
        bool $dryRun
    ): void {
        $winRate = $totalWeeks > 0 ? round( $profitWeeks / $totalWeeks * 100, 2 ) : 0;

        $skipLabels = [
            'no_entry_date'   => 'No valid entry date in week',
            'no_index_candle' => 'No INDEX candle at entry time',
            'no_expiry'       => 'No expiry in expired_expiries',
            'gap_skip'        => 'Gap too extreme — week skipped',
            'not_enough_legs' => 'Fewer than 4 sell legs found',
            'no_hedge_ce'     => 'Hedge CE not found near target price',
            'no_hedge_pe'     => 'Hedge PE not found near target price',
            'unknown_filter'  => 'Unknown filter (legacy null)',
        ];

        arsort( $skipReasons );

        $this->info( "╔══════════════════════════════════════════════════════════════╗" );
        $this->info( "║                  📊  WEEKLY BACKTEST SUMMARY                ║" );
        $this->info( "╠══════════════════════════════════════════════════════════════╣" );
        $this->info( sprintf( "║  Total Weeks Processed : %-34s║", $totalWeeks ) );
        $this->info( sprintf( "║  Skipped Weeks         : %-34s║", $skippedWeeks ) );

        foreach ( $skipReasons as $key => $count ) {
            if ( $count === 0 ) {
                continue;
            }
            $label = $skipLabels[ $key ] ?? $key;
            $pct   = $skippedWeeks > 0 ? round( $count / $skippedWeeks * 100, 1 ) : 0;
            $this->info( sprintf( "║    ↳ %-30s : %-20s║", $label, "{$count} wks ({$pct}%)" ) );
        }

        $this->info( "╠══════════════════════════════════════════════════════════════╣" );
        $this->info( sprintf( "║  Profit Weeks          : %-34s║", $profitWeeks ) );
        $this->info( sprintf( "║  Loss Weeks            : %-34s║", $lossWeeks ) );
        $this->info( sprintf( "║  Win Rate              : %-34s║", "$winRate%" ) );
        $this->info( sprintf( "║  Total P&L             : %-34s║", "₹" . number_format( $totalPnl, 0 ) ) );
        $this->info( sprintf( "║  Run ID                : %-34s║", $runId ) );
        $this->info( "╚══════════════════════════════════════════════════════════════╝" );

        $dryRun
            ? $this->warn( "\n[DRY RUN] Nothing was saved." )
            : $this->info( "\n✅ Done. View at /backtest?run_id={$runId}" );
    }
}
