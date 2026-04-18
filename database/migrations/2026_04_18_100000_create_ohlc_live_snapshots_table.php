<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ohlc_live_snapshots', function (Blueprint $table) {
            $table->id();

            // Instrument identity
            $table->string('instrument_key')->index();
            $table->string('underlying_symbol')->nullable()->index();
            $table->date('expiry_date')->nullable()->index();
            $table->decimal('strike', 12, 2)->nullable();
            $table->string('instrument_type', 10)->nullable()->comment('FUT, PE, CE, INDEX');

            // OHLC prices
            $table->decimal('open', 12, 2)->nullable();
            $table->decimal('high', 12, 2)->nullable();
            $table->decimal('low', 12, 2)->nullable();
            $table->decimal('close', 12, 2)->nullable();

            // Volume & OI (sourced from option chain API)
            $table->unsignedBigInteger('oi')->nullable();
            $table->unsignedBigInteger('volume')->nullable();

            // Meta
            $table->string('exchange', 10)->default('NSE')->comment('NSE or BSE');
            $table->string('interval', 10)->default('5m');
            $table->timestamp('timestamp')->nullable()->comment('Candle close timestamp (0 seconds)');

            // Build-up analysis
            $table->enum('build_up', ['Long Build', 'Long Unwind', 'Short Build', 'Short Cover'])->nullable();
            $table->bigInteger('diff_oi')->nullable()->comment('OI change vs previous snapshot');
            $table->bigInteger('diff_volume')->nullable()->comment('Volume change vs previous snapshot');
            $table->decimal('diff_ltp', 12, 2)->nullable()->comment('Close price change vs previous snapshot');

            $table->timestamps();

            // Composite unique: one row per instrument per candle timestamp
            $table->unique(['instrument_key', 'timestamp'], 'unique_instrument_timestamp');

            // Query indexes
            $table->index(['underlying_symbol', 'expiry_date', 'timestamp'], 'idx_ols_symbol_expiry_ts');
            $table->index(['build_up', 'timestamp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ohlc_live_snapshots');
    }
};
