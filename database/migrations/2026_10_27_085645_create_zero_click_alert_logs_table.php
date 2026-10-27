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
        Schema::create('zero_click_alert_logs', function (Blueprint $table) {
            $table->id();
            $table->dateTime('triggered_at')->index();
            $table->date('trade_date')->index();
            $table->string('candle_time', 10)->index();
            
            // 0Click Trade Execution Contract Fields
            $table->string('action', 10)->comment('BUY (Bullish) or SELL (Bearish)');
            $table->string('symbol', 30)->default('NIFTY');
            $table->decimal('spot_price', 10, 2);
            $table->decimal('sl_price', 10, 2)->nullable();
            $table->decimal('tp_price', 10, 2)->nullable();
            $table->integer('sl_offset_pts')->nullable();
            $table->integer('tp_offset_pts')->nullable();
            $table->unsignedInteger('lots')->default(1);
            $table->string('mode', 20)->default('SIMULATION')->comment('SIMULATION or LIVE');
            $table->string('product', 20)->default('MARGIN')->comment('MARGIN or INTRADAY');
            $table->string('category', 50)->default('LOCAL_SERVER');

            // Breadth Extreme Trigger Metrics
            $table->decimal('breadth_pct', 5, 2)->nullable();
            $table->decimal('breadth_weight_pct', 5, 2)->nullable();
            $table->unsignedInteger('breadth_count')->nullable();
            $table->decimal('net_points', 8, 2)->nullable();
            $table->string('top_contributor', 100)->nullable();

            // Full 0Click-compatible payload
            $table->json('payload');

            // Dispatch / Webhook state
            $table->string('status', 30)->default('LOGGED')->comment('LOGGED, SENT, FAILED, SIMULATED');
            $table->string('endpoint_url', 255)->nullable();
            $table->integer('api_status_code')->nullable();
            $table->json('api_response')->nullable();
            $table->decimal('latency_ms', 8, 2)->nullable();
            $table->dateTime('dispatched_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zero_click_alert_logs');
    }
};
