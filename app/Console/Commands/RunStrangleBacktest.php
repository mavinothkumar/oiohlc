<?php

// app/Console/Commands/RunStrangleBacktest.php

# Strangle Straddle
// php artisan backtest:strangle strangle_straddle  --from="2025-01-01" --to="2026-04-13" --entry-time="09:45" --target="12000" --stoploss="5000" --lot="130"
// php artisan backtest:strangle strangle_straddle  --from="2025-01-01" --to="2026-04-28" --entry-time="09:45" --stoploss="5500" --lot="130"


# Strangle Straddle Smart Balanced
// php artisan backtest:strangle smart_balanced  --from="2025-01-01" --to="2026-04-13" --entry-time="09:20" --target="12000" --stoploss="5000" --lot="130"

// php artisan backtest:strangle first_candle_breakout --from="2025-01-01" --to="2026-04-13" --entry-time="09:15" --target="7000" --stoploss="4000" --lot="130"
# Strategy  — ATM Straddle
// php artisan backtest:strangle atm_straddle  --from="2025-01-01" --to="2025-01-05" --entry-time="09:20" --target="6000" --stoploss="5000" --lot="130"


# Strategy — Near Straddle
// php artisan backtest:strangle near_straddle --from="2025-01-01" --to="2025-01-05" --entry-time="09:20" --target="7000" --stoploss="5000" --lot="65"

# Strategy — OTM Strangle (min ₹50 premium, starts ATM±200)
// php artisan backtest:strangle otm_strangle --from="2025-01-01" --to="2025-01-05" --entry-time="09:20" --target="7000" --stoploss="5000" --lot="130" --min-premium="50"

# 15min_breakout
// php artisan backtest:strangle 15min_breakout --from="2025-01-01" --to="2025-01-05" --entry-time="09:15" --target="6000" --stoploss="5000" --lot="130"

# Iron Condor Ladder Strategy
// php artisan backtest:strangle iron_condor_ladder --from="2025-01-01" --to="2025-01-05" --target="12000" --stoploss="5500" --lot="65"
// php artisan backtest:strangle iron_condor_ladder --from="2025-01-01" --to="2025-01-05" --stoploss="5500" --lot="65"

//php artisan tinker --execute="DB::table('backtest_trades')->where('strategy', 'oi_volume_weighted_sell')->delete(); echo 'Done';"
//php artisan tinker --execute="DB::table('backtest_trades')->where('strategy', 'strangle_straddle')->whereRaw(\"TIME(entry_time) = '09:45:00'\")->delete(); echo 'Done';"


// php artisan backtest:strangle oi_volume_weighted_sell --from=2025-01-01 --to=2025-01-03 --entry-time=09:50 --min-premium=70 --target=8000 --stoploss=4000 --lot=130

# Tune the thresholds
// php artisan backtest:strangle strangle_straddle --from=... --to=... --leg-sl-pct=50 --combined-sl-pct=35

# Disable only the leg guard
// php artisan backtest:strangle strangle_straddle --from=... --to=... --no-leg-sl

# Disable only the combined guard
// php artisan backtest:strangle strangle_straddle --from=... --to=... --no-combined-sl

# Disable both (original behaviour)
// php artisan backtest:strangle strangle_straddle --from=... --to=... --no-leg-sl --no-combined-sl

// php artisan backtest:strangle oi_volume_weighted_sell --from=2025-01-10 --to=2025-01-28 --entry-time=09:50 --min-premium=50 --target=8000 --stoploss=3500 --lot=130 --min-gap=0


# Dry run first — no data saved
// php artisan backtest:weekly weekly_iron_condor --symbol=NIFTY --from=2025-01-06 --to=2025-01-29 --strike-offset=600 --sell-lots=2 --hedge-lots=8 --hedge-price=10 --target=100000 --stoploss=30000 --leg-double-pct=100 --trailing-lock-pct=60 --gap-shift-threshold=100 --gap-skip-threshold=300 --dry-run

# Live run
// php artisan backtest:weekly weekly_iron_condor --from=2025-01-06 --to=2026-04-25 --strike-offset=600 --sell-lots=2 --target=100000 --stoploss=30000

# atm_ratio_backspread
// php artisan backtest:strangle atm_ratio_backspread --from=2025-01-01 --to=2025-01-31 --entry-time=09:15 --lot=65 --target=7000 --stoploss=5000 --step=50 --dry-run --verbose-skips

namespace App\Console\Commands;

use App\Models\BacktestTrade;
use App\Services\Backtest\BacktestEngine;
use App\Services\Backtest\Contracts\BacktestStrategy;
use App\Services\Backtest\HybridIntradayEngine;
use App\Services\Backtest\StrategyRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RunStrangleBacktest extends Command {
    protected $signature = 'backtest:strangle
        {strategy : Strategy name. Run with --list to see all available.}
        {--symbol=NIFTY : Underlying symbol}
        {--from= : From date YYYY-MM-DD}
        {--to= : To date YYYY-MM-DD}
        {--entry-time=09:20 : Entry time HH:MM}
        {--strike-offset=300 : Fixed offset from ATM (fixed_offset strategy)}
        {--target=7000 : Combined day profit target ₹}
        {--stoploss=5000 : Combined day stop loss ₹}
        {--lot=65 : Qty per leg}
        {--exchange=NSE : Exchange}
        {--min-offset=300 : Smart balanced minimum offset}
        {--max-offset=600 : Smart balanced maximum offset}
        {--step=100 : Strike step size}
        {--list : List all available strategies and exit}
        {--min-premium=50 : Minimum option premium for OTM strangle strategy}
        {--dry-run : Preview without saving}
        {--no-leg-sl : Disable individual leg loss% exit guard (default: enabled at 60%)}
        {--no-combined-sl : Disable combined premium breach% exit guard (default: enabled at 40%)}
        {--leg-sl-pct=60 : Individual leg loss threshold % — exit whole trade when any leg rises this % above entry}
        {--combined-sl-pct=40 : Combined premium breach threshold % — exit when combined current price rises this % above combined entry}
        {--dry-run : Preview without saving}
        {--min-gap=0 : Min gap_abs to trade (0 = disabled)}
        {--min-gap-pct=0 : Min gap as %% of previous close (0 = disabled)}
        {--min-range-pct=0 : Min gap as %% of previous day range (0 = disabled)}
        {--gap-mode=abs : Gap filter mode: abs|pct|range|any|all}
        {--verbose-skips : Print each skipped day with its exact reason}';

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
        $runId        = (string) Str::uuid();
        $symbol       = strtoupper( $this->option( 'symbol' ) );
        $entryHHMM    = $this->option( 'entry-time' );
        $target       = abs((float) $this->option( 'target' ));
        $stoploss     = abs((float) $this->option( 'stoploss' ));
        $exchange     = strtoupper( $this->option( 'exchange' ) );
        $dryRun       = $this->option( 'dry-run' );
        $verboseSkips = $this->option( 'verbose-skips' );
        $skipReasons  = [];

        $qty = (int) $this->option( 'lot' );

        // If lot was not explicitly passed by user, apply strategy default
        if ( ! $this->input->hasParameterOption( '--lot' ) ) {
            $qty = match ( $strategyName ) {
                'first_candle_breakout',
                '15min_breakout' => 130,
                'iron_condor_ladder' => 65,
                default => 65,
            };
            $this->line( "  Note: Using default qty={$qty} for {$strategyName}" );
        }

        // Auto-correct entry time for straddle/strangle strategies
        if ( ! $this->input->hasParameterOption( '--entry-time' ) ) {
            $entryHHMM = match ( $strategyName ) {
                'atm_straddle',
                'near_straddle',
                'otm_strangle' => '09:15',
                'first_candle_breakout',
                '15min_breakout' => '09:15',
                'iron_condor_ladder' => '09:15',
                default => '09:20',
            };
            $this->line( "  Note: Entry time auto-set to {$entryHHMM} for {$strategyName}" );
        }

        // ── Guard flags ────────────────────────────────────────────────────
        $useLegSl      = ! $this->option( 'no-leg-sl' );
        $useCombinedSl = ! $this->option( 'no-combined-sl' );
        $legSlPct      = (float) $this->option( 'leg-sl-pct' );
        $combinedSlPct = (float) $this->option( 'combined-sl-pct' );

        $engineOptions = [
            'use-leg-sl'      => $useLegSl,
            'use-combined-sl' => $useCombinedSl,
            'leg-sl-pct'      => $legSlPct,
            'combined-sl-pct' => $combinedSlPct,
        ];

        $options        = $this->options();
        $options['lot'] = $qty;

        $strategy = StrategyRegistry::resolve( $strategyName );
        $engine = match ($strategyName) {
            'atm_ratio_backspread' => new HybridIntradayEngine(),
            default => new BacktestEngine(),
        };

        $this->printHeader(
            $symbol, $from, $to, $entryHHMM, $target, $stoploss,
            $qty, $runId, $dryRun, $strategyName,
            $strategy->describe( $options ),
            $useLegSl, $legSlPct,
            $useCombinedSl, $combinedSlPct
        );

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

        // ── Skip reason counters ───────────────────────────────────────────
        // Each key maps to a human-readable label printed in the summary.
        $skipReasons = [
            'no_index_candle' => 0,  // INDEX candle missing at entry time
            'no_expiry'       => 0,  // No expiry found in expired_expiries
            'strategy_filter' => 0,  // resolveLegs() returned null (all sub-reasons logged via Log::info)
        ];

        // ── Load all expiries for the symbol once ──────────────────────────
        $allExpiries = DB::table( 'expired_expiries' )
                         ->where( 'underlying_symbol', $symbol )
                         ->where( 'instrument_type', 'OPT' )
                         ->orderBy( 'expiry_date' )
                         ->pluck( 'expiry_date' )
                         ->toArray();

        if ( empty( $allExpiries ) ) {
            $this->error( "No expiries found for {$symbol} in expired_expiries." );

            return self::FAILURE;
        }

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
                $skippedDays++;
                $skipReasons['no_index_candle'] = ( $skipReasons['no_index_candle'] ?? 0 ) + 1;
                if ( $verboseSkips ) $this->line( "  ⊘ SKIP {$tradeDate} — no_index_candle" );
                continue;
            }

            $indexOpen = (float) $indexCandle->open;

            // ── Expiry ─────────────────────────────────────────────────────
            $expiry = resolveExpiry( $tradeDate, $allExpiries );

            if ( ! $expiry ) {
                $skippedDays++;
                $skipReasons['no_expiry'] = ( $skipReasons['no_expiry'] ?? 0 ) + 1;
                if ( $verboseSkips ) $this->line( "  ⊘ SKIP {$tradeDate} — no_expiry" );
                continue;
            }

            // ── Strategy resolves the legs ─────────────────────────────────
            // resolveLegs() returns null for strategy-level filters:
            //   gap_abs < 50, no OI data, LTP below min-premium,
            //   strike distance imbalance, leg candle missing, etc.
            // Each sub-reason is already written to Log::info() inside the strategy.
            // Here we only track the aggregate count and surface via --verbose-skips.
            $legData = $strategy->resolveLegs(
                $symbol, $indexOpen, $tradeDate, $entryTimestamp, $options
            );

            if ( ! $legData || \App\Services\Backtest\Contracts\BacktestStrategy::isSkip( $legData ) ) {
                $reason = \App\Services\Backtest\Contracts\BacktestStrategy::isSkip( $legData )
                    ? \App\Services\Backtest\Contracts\BacktestStrategy::skipReason( $legData )
                    : 'unknown_filter';
                $skippedDays++;
                $skipReasons[$reason] = ( $skipReasons[$reason] ?? 0 ) + 1;
                if ( $verboseSkips ) $this->line( "  ⊘ SKIP {$tradeDate} — {$reason}" );
                continue;
            }

            // ── Use dynamic target if strategy suggests one ────────────────
            $effectiveTarget = $target;
            if ( ! $this->input->hasParameterOption( '--target' ) ) {
                $effectiveTarget = $legData[0]['suggested_target'] ?? $target;
            }

            // ── Engine walks the candles ───────────────────────────────────
            $result = $engine->run(
                $legData,
                $entryTimestamp,
                $tradeDate,
                $effectiveTarget,
                $stoploss,
                $qty,
                $engineOptions
            );

            $legData          = $result['legData'];
            $dayOutcome       = $result['dayOutcome'];
            $exitReason       = $result['exitReason'];
            $dayMaxProfit     = $result['dayMaxProfit'];
            $dayMaxProfitTime = $result['dayMaxProfitTime'];
            $dayMaxLoss       = $result['dayMaxLoss'];
            $dayMaxLossTime   = $result['dayMaxLossTime'];
            $dayExitTime      = $result['dayExitTime'];
            $effectiveQty     = $legData[0]['qty_override'] ?? $qty;

            // ── Day totals ─────────────────────────────────────────────────
            $calcLegPnl = function (array $leg) use ($qty) {
                $legQty = (int) ($leg['qty_override'] ?? $qty);
                $side = strtoupper($leg['side'] ?? 'SELL');
                $exit = (float) ($leg['exit_price'] ?? $leg['entry_price']);

                return round(
                    $side === 'BUY'
                        ? ($exit - $leg['entry_price']) * $legQty
                        : ($leg['entry_price'] - $exit) * $legQty,
                    2
                );
            };

            $dayTotalPnl = round(array_sum(array_map($calcLegPnl, $legData)), 2);

            if ( $dayOutcome === 'open' ) {
                $dayOutcome = $dayTotalPnl >= 0 ? 'profit' : 'loss';
            }

            $actualEntryTime = $legData[0]['entry_time'] ?? $entryTimestamp;
            $dayDuration     = $dayExitTime
                ? (int) Carbon::parse( $actualEntryTime )->diffInMinutes( Carbon::parse( $dayExitTime ) )
                : null;

            $dayOutcome === 'profit'
                ? ( $profitDays ++ && ( $dayDuration ? $profitMinutes[] = $dayDuration : null ) )
                : ( $lossDays ++ && ( $dayDuration ? $lossMinutes[] = $dayDuration : null ) );

            $totalPnlSum += $dayTotalPnl;

            // ── Insert immediately ─────────────────────────────────────────
            $dayGroupId = (string) Str::uuid();
            $now        = now()->toDateTimeString();

            $ceStrike = collect( $legData )->firstWhere( 'type', 'CE' )['strike'] ?? null;
            $peStrike = collect( $legData )->firstWhere( 'type', 'PE' )['strike'] ?? null;

            $gapRow = DB::table( 'index_gap' )
                        ->where( 'symbol_name', $symbol )
                        ->whereDate( 'trading_date', $tradeDate )
                        ->first();

            $gapUsed          = $gapRow?->gap_abs;
            $previousDayRange = $gapRow?->previous_day_range;
            $gapPctPrevRange  = $gapRow?->gap_pct_prev_range;

            $dayRows = array_map(fn ($leg) => [
                'underlying_symbol' => $symbol,
                'instrument_type' => $leg['type'],
                'exchange' => $exchange,
                'expiry' => $expiry,
                'instrument_key' => $leg['instrument_key'],
                'strike' => $leg['strike'],
                'ce_strike' => $ceStrike,
                'pe_strike' => $peStrike,
                'entry_price' => $leg['entry_price'],
                'exit_price' => $leg['exit_price'],
                'side' => strtoupper($leg['side'] ?? 'SELL'),
                'qty' => (int) ($leg['qty_override'] ?? $qty),
                'pnl' => $calcLegPnl($leg),
                'lot_size' => (int) ($leg['qty_override'] ?? $qty),
                'strategy' => $strategyName,
                'entry_time' => $leg['entry_time'] ?? $entryTimestamp,
                'exit_time' => $leg['exit_time'],
                'signal_time' => $leg['signal_time'] ?? null,
                'trade_time_duration' => $leg['exit_time']
                    ? (int) Carbon::parse($leg['entry_time'] ?? $entryTimestamp)
                                  ->diffInMinutes(Carbon::parse($leg['exit_time']))
                    : null,
                'outcome' => $calcLegPnl($leg) >= 0 ? 'profit' : 'loss',
                'trade_date' => $tradeDate,
                'backtest_run_id' => $runId,
                'day_group_id' => $dayGroupId,
                'day_total_pnl' => $dayTotalPnl,
                'day_outcome' => $dayOutcome,
                'day_max_profit' => $dayMaxProfit,
                'day_max_profit_time' => $dayMaxProfitTime,
                'day_max_loss' => $dayMaxLoss,
                'day_max_loss_time' => $dayMaxLossTime,
                'index_price_at_entry' => $indexOpen,
                'target' => $effectiveTarget,
                'stoploss' => $stoploss,
                'strike_offset' => (int) ($options['strike-offset'] ?? 300),
                'created_at' => $now,
                'updated_at' => $now,
                'gap_used' => $gapUsed,
                'previous_day_range' => $previousDayRange,
                'gap_pct_prev_range' => $gapPctPrevRange,
            ], $legData);

            if ( ! $dryRun ) {
                BacktestTrade::insert( $dayRows );
            }

            $pnl = ( $dayTotalPnl >= 0 ? '+₹' : '-₹' ) . number_format( abs( $dayTotalPnl ), 0 );
            $tag = $dayOutcome === 'profit' ? "✓ {$pnl}" : "✗ {$pnl}";
            $this->line( "  {$tradeDate} | {$tag} | {$exitReason}" );
        }

        $bar->finish();
        $this->newLine( 2 );

        $this->printSummary(
            $runId,
            $profitDays + $lossDays,
            $profitDays,
            $lossDays,
            $skippedDays,
            $skipReasons,
            $totalPnlSum,
            ! empty( $profitMinutes ) ? round( array_sum( $profitMinutes ) / count( $profitMinutes ), 1 ) : 0,
            ! empty( $lossMinutes ) ? round( array_sum( $lossMinutes ) / count( $lossMinutes ), 1 ) : 0,
            $dryRun
        );

        return self::SUCCESS;
    }

    private function recordSkip( int &$skippedDays, array &$skipReasons, string $reason ): void {
        $skippedDays ++;
        $skipReasons[ $reason ] = ( $skipReasons[ $reason ] ?? 0 ) + 1;
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
        string $strategyDesc,
        bool $useLegSl,
        float $legSlPct,
        bool $useCombinedSl,
        float $combinedSlPct
    ): void {
        $legSlLabel      = $useLegSl ? "ON @ {$legSlPct}%" : 'OFF';
        $combinedSlLabel = $useCombinedSl ? "ON @ {$combinedSlPct}%" : 'OFF';

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
        $this->info( sprintf( "║  Leg SL Guard   : %-43s║", $legSlLabel ) );
        $this->info( sprintf( "║  Combined SL    : %-43s║", $combinedSlLabel ) );
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
        array $skipReasons,
        float $totalPnl,
        float $avgProfitWait,
        float $avgLossWait,
        bool $dryRun
    ): void {
        $winRate = $totalDays > 0 ? round( $profitDays / $totalDays * 100, 2 ) : 0;
        arsort( $skipReasons );

        // ── add these lines ──
        $skipLabels = [
            'no_index_candle'      => 'No INDEX candle at entry time',
            'no_expiry'            => 'No expiry in expired_expiries',
            'gap_too_small'        => 'Gap too small (gap_abs < threshold)',
            'no_oi_data'           => 'No OI/volume data in scan window',
            'no_ce_strike'         => 'No CE strike found above ATM',
            'no_pe_strike'         => 'No PE strike found below ATM',
            'below_min_premium_ce' => 'CE below min-premium (after walk)',
            'below_min_premium_pe' => 'PE below min-premium (after walk)',
            'imbalance'            => 'CE/PE distance imbalance > threshold',
            'no_entry_candle'      => 'Entry candle missing in expired_ohlc',
            'unknown_filter'       => 'Unknown filter (legacy null return)',
        ];
        arsort( $skipReasons );

        $this->info( "╔══════════════════════════════════════════════════════════════╗" );
        $this->info( "║                    📊  BACKTEST SUMMARY                     ║" );
        $this->info( "╠══════════════════════════════════════════════════════════════╣" );
        $this->info( sprintf( "║  Total Days Processed  : %-35s║", $totalDays ) );
        $this->info( sprintf( "║  Skipped Days          : %-35s║", $skippedDays ) );

        // ── Print each skip reason breakdown ──────────────────────────────
        foreach ( $skipLabels as $key => $label ) {
            $count = $skipReasons[ $key ] ?? 0;
            if ( $count > 0 ) {
                $this->info( sprintf( "║    ↳ %-20s : %-28s║", $label, $count . ' days' ) );
            }
        }

        foreach ( $skipReasons as $key => $count ) {
            if ( $count === 0 ) continue;
            $label = $skipLabels[$key] ?? $key;
            $pct   = $skippedDays > 0 ? round( $count / $skippedDays * 100, 1 ) : 0;
            $this->info( sprintf( "║    ↳ %-30s : %-21s║", $label, "{$count} days ({$pct}%)" ) );
        }

        $this->info( "╠══════════════════════════════════════════════════════════════╣" );
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
            : $this->info( "\n✅ Done. View at /backtest?run_id={$runId}" );
    }
}
