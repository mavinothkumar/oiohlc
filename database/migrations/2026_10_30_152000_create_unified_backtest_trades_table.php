<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('unified_backtest_trades')) {
            Schema::create('unified_backtest_trades', function (Blueprint $table) {
                $table->id();
                $table->date('date')->index();
                $table->string('mode', 20)->index(); // stocks, confluence
                $table->string('direction', 10)->index(); // BULL, BEAR
                $table->string('signal_type', 30); // STOCK_SURGE, CONFLUENCE
                $table->string('entry_time', 10)->index(); // HH:MM
                $table->decimal('entry_price', 10, 2);
                $table->decimal('candle_high', 10, 2);
                $table->decimal('candle_low', 10, 2);
                $table->decimal('sl_price', 10, 2);
                $table->decimal('risk_pts', 10, 2);
                $table->decimal('tp1_price', 10, 2);
                $table->decimal('tp2_price', 10, 2);
                $table->string('exit_time', 10)->nullable();
                $table->decimal('exit_price', 10, 2)->nullable();
                $table->string('outcome', 20)->index(); // TP1_HIT, TP2_HIT, SL_HIT, EOD_PROFIT, EOD_LOSS
                $table->decimal('net_pts', 10, 2);
                $table->decimal('pnl_pts_tp1', 10, 2)->nullable();
                $table->decimal('pnl_pts_tp2', 10, 2)->nullable();
                $table->decimal('rr_realized', 8, 2)->nullable();
                $table->decimal('mfe_pts', 10, 2)->default(0);
                $table->decimal('mae_pts', 10, 2)->default(0);
                $table->integer('candles_held')->default(0);
                $table->decimal('stock_adv_pct', 6, 2)->default(0);
                $table->decimal('stock_dec_pct', 6, 2)->default(0);
                $table->decimal('opt_bull_pct', 6, 2)->default(0);
                $table->decimal('opt_bear_pct', 6, 2)->default(0);
                $table->timestamps();

                $table->index(['date', 'entry_time']);
                $table->index(['direction', 'outcome']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unified_backtest_trades');
    }
};
