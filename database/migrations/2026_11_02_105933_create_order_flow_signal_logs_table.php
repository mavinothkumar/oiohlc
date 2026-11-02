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
        Schema::create('order_flow_signal_logs', function (Blueprint $table) {
            $table->id();
            $table->string('mode', 20)->default('live')->comment('live or sim');
            $table->date('trade_date')->index();
            $table->string('signal_time', 12)->index()->comment('e.g. 10:14:32');
            $table->string('symbol', 50)->default('NIFTY');
            $table->string('signal_type', 30)->comment('BUY_CE, BUY_PE, LONG_FUT, SHORT_FUT');
            $table->string('action', 30)->comment('BUY CALL, BUY PUT');
            $table->string('instrument', 100);
            
            // Futures & Spot levels at entry
            $table->decimal('spot_price', 10, 2)->default(0);
            $table->decimal('future_price', 10, 2)->default(0);
            $table->decimal('entry_price', 10, 2)->default(0);
            $table->decimal('stop_loss', 10, 2)->default(0);
            $table->decimal('target_price', 10, 2)->default(0);
            
            // Recommended Option specifics
            $table->integer('opt_strike')->nullable();
            $table->string('opt_type', 10)->nullable()->comment('CE or PE');
            $table->decimal('opt_entry', 10, 2)->nullable();
            $table->decimal('opt_sl', 10, 2)->nullable();
            $table->decimal('opt_target', 10, 2)->nullable();

            // Microstructure metrics at trigger
            $table->integer('confluence_score')->default(50);
            $table->decimal('obi_pct', 6, 2)->default(0);
            $table->decimal('micro_drift', 8, 2)->default(0);
            $table->string('trigger_reason', 255)->nullable();

            // Real-Time Outcome & P&L
            $table->string('status', 30)->default('ACTIVE')->comment('ACTIVE, TARGET_HIT, STOPPED_OUT, REVERSED, CLOSED');
            $table->string('exit_time', 12)->nullable();
            $table->decimal('exit_price', 10, 2)->nullable();
            $table->decimal('pnl_points', 10, 2)->default(0);
            $table->decimal('max_favorable_pts', 10, 2)->default(0)->comment('MFE - max profit reached');
            $table->decimal('max_adverse_pts', 10, 2)->default(0)->comment('MAE - max drawdown reached');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['trade_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_flow_signal_logs');
    }
};
