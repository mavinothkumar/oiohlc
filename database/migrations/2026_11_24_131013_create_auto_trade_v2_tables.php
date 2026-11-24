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
        // 1. Session Rollup per Trading Day
        Schema::create('auto_trade_v2_days', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique(); // Enforces 1 record per trading day
            $table->decimal('nifty_open', 10, 2)->nullable();
            $table->decimal('nifty_high', 10, 2)->nullable();
            $table->decimal('nifty_low', 10, 2)->nullable();
            $table->decimal('nifty_close', 10, 2)->nullable();
            $table->unsignedSmallInteger('total_trades')->default(0);
            $table->unsignedSmallInteger('win_trades')->default(0);
            $table->unsignedSmallInteger('loss_trades')->default(0);
            $table->decimal('net_points', 8, 2)->default(0);
            $table->decimal('net_pnl_rupees', 12, 2)->default(0);
            $table->decimal('mfe_points', 8, 2)->default(0);
            $table->decimal('mae_points', 8, 2)->default(0);
            $table->unsignedSmallInteger('stagnation_exits_count')->default(0);
            $table->enum('day_status', ['WIN', 'LOSS', 'SCRATCH', 'NO_TRADE'])->default('NO_TRADE');
            $table->text('summary_reason')->nullable();
            $table->timestamps();

            $table->index('date');
            $table->index('day_status');
        });

        // 2. Individual Executed Trades Forensics
        Schema::create('auto_trade_v2_trades', function (Blueprint $table) {
            $table->id();
            $table->string('trade_uid', 64)->unique(); // e.g. T_2026-11-20_0945_BULL
            $table->date('date');
            $table->enum('direction', ['BULL', 'BEAR']);
            $table->string('strategy_mode', 32)->default('auto');
            $table->string('action', 16)->default('SELL'); // BUY, SELL, COMBO
            $table->string('instrument', 64);              // NIFTY 24200 CE
            $table->unsignedInteger('strike');
            $table->string('opt_type', 32);                // CE, PE, COMBO
            $table->string('entry_time', 10);              // 09:45
            $table->decimal('entry_spot', 10, 2);
            $table->decimal('entry_opt_price', 10, 2);
            $table->unsignedSmallInteger('entry_lots')->default(3);
            $table->unsignedSmallInteger('lot_size')->default(65);
            $table->decimal('base_sl', 10, 2);
            $table->decimal('current_sl', 10, 2);
            $table->decimal('t1_price', 10, 2);
            $table->decimal('t2_price', 10, 2);
            $table->decimal('t3_price', 10, 2);
            $table->boolean('t1_hit')->default(false);
            $table->boolean('t2_hit')->default(false);
            $table->boolean('t3_hit')->default(false);
            $table->string('exit_time', 10)->nullable();
            $table->decimal('exit_spot', 10, 2)->nullable();
            $table->decimal('exit_opt_price', 10, 2)->nullable();
            $table->string('exit_reason', 255)->nullable();
            $table->decimal('pnl_points', 8, 2)->default(0);
            $table->decimal('pnl_rupees', 12, 2)->default(0);
            $table->decimal('mfe_points', 8, 2)->default(0);
            $table->decimal('mae_points', 8, 2)->default(0);
            $table->string('setup_type', 32)->default('STANDARD'); // STANDARD, SQUEEZE_BREAKOUT, GRADE_A_CONFLUENCE
            $table->text('entry_reason')->nullable();
            $table->boolean('is_stagnation_trailed')->default(false);
            $table->longText('telemetry_json')->nullable(); // 5M decision trail for the session
            $table->timestamps();

            $table->index('date');
            $table->index('direction');
            $table->index('setup_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_trade_v2_trades');
        Schema::dropIfExists('auto_trade_v2_days');
    }
};
