<?php

// database/migrations/xxxx_create_backtest_trades_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create( 'backtest_trades', function ( Blueprint $table ) {
            $table->id();
            $table->string( 'underlying_symbol', 20 )->default( 'NIFTY' );
            $table->enum( 'instrument_type', [ 'CE', 'PE' ] );
            $table->string( 'exchange', 10 )->default( 'NSE' );
            $table->date( 'expiry' );
            $table->string( 'instrument_key' );
            $table->integer( 'strike' );
            $table->decimal( 'entry_price', 10, 2 );
            $table->decimal( 'exit_price', 10, 2 )->nullable();
            $table->enum( 'side', [ 'BUY', 'SELL' ] )->default( 'SELL' );
            $table->integer( 'qty' )->default( 65 );
            $table->decimal( 'pnl', 12, 2 )->nullable()->comment( 'Per leg P&L' );
            $table->string( 'strategy', 50 )->default( 'strangle_straddle' );
            $table->datetime( 'entry_time' );
            $table->datetime( 'exit_time' )->nullable();
            $table->integer( 'trade_time_duration' )->nullable()->comment( 'In minutes' );
            $table->enum( 'outcome', [ 'profit', 'loss', 'open' ] )->default( 'open' );
            $table->date( 'trade_date' );

            // Run & grouping — no ->index() here, defined explicitly below
            $table->string( 'backtest_run_id', 36 )->nullable();
            $table->string( 'day_group_id', 36 )->nullable()
                  ->comment( 'UUID shared by all 4 legs of the same day' );

            // Day-level combined values
            $table->decimal( 'day_total_pnl', 12, 2 )->nullable();
            $table->decimal( 'day_max_profit', 12, 2 )->nullable()->default( 0 )
                  ->comment( 'Highest combined P&L reached during the day before exit' );
            $table->decimal( 'day_max_loss', 12, 2 )->nullable()->default( 0 )
                  ->comment( 'Lowest combined P&L reached during the day before exit (negative value)' );
            $table->dateTime( 'day_max_profit_time' )->nullable()->default( null )
                  ->comment( 'Timestamp when combined P&L hit its highest point' );
            $table->dateTime( 'day_max_loss_time' )->nullable()->default( null )
                  ->comment( 'Timestamp when combined P&L hit its lowest point' );
            $table->enum( 'day_outcome', [ 'profit', 'loss', 'open' ] )->default( 'open' );

            // Context
            $table->decimal( 'index_price_at_entry', 12, 2 )->nullable();
            $table->decimal( 'target', 10, 2 )->nullable();
            $table->decimal( 'stoploss', 10, 2 )->nullable();
            $table->integer( 'lot_size' )->default( 65 );
            $table->integer( 'strike_offset' )->default( 300 );


            $table->timestamps();

            // ── Indexes — defined once, here only ──────────────────────────
            $table->index( [ 'underlying_symbol', 'trade_date' ] );
            $table->index( [ 'backtest_run_id', 'trade_date' ] );
            $table->index( 'day_group_id' );
        } );
    }

    public function down(): void {
        Schema::dropIfExists( 'backtest_trades' );
    }
};
