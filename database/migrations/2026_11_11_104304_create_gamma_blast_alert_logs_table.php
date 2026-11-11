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
        Schema::create('gamma_blast_alert_logs', function (Blueprint $table) {
            $table->id();
            $table->date('trade_date')->index();
            $table->string('alert_time', 12)->index()->comment('e.g. 10:37:21');
            $table->string('symbol', 30)->default('NIFTY')->index();
            $table->string('expiry', 30)->nullable();
            $table->decimal('spot_price', 10, 2)->default(0);
            $table->string('alert_type', 50)->index()->comment('BLAST_SQUEEZE, ZERO_FLIP_TEST, CALL_WALL_TEST, PUT_WALL_TEST, NEGATIVE_GEX_REGIME, MULTI_TRIGGER');
            $table->string('title', 255);
            $table->decimal('strike', 10, 2)->nullable();
            $table->string('option_type', 10)->nullable()->comment('CE, PE');
            $table->string('direction', 20)->nullable()->comment('BULLISH, BEARISH, NEUTRAL');
            $table->decimal('score', 5, 2)->nullable()->comment('Blast readiness score %');
            $table->decimal('distance_pts', 8, 2)->nullable()->comment('Distance to flip or wall pts');
            $table->string('trigger_condition', 255)->nullable();
            $table->decimal('zero_gamma_flip', 10, 2)->nullable();
            $table->decimal('call_wall', 10, 2)->nullable();
            $table->decimal('put_wall', 10, 2)->nullable();
            $table->decimal('net_gex_cr', 12, 2)->nullable();
            $table->string('regime', 50)->nullable()->comment('POSITIVE_GAMMA, NEGATIVE_GAMMA, NEUTRAL');
            $table->text('message');
            $table->json('settings_snapshot')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['trade_date', 'alert_type']);
            $table->index(['trade_date', 'symbol']);
            $table->index(['trade_date', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gamma_blast_alert_logs');
    }
};
