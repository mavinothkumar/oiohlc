<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ohlc_quotes', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('instrument_key', 100)->index();
            $table->string('instrument_type', 20)->index(); // FUT / OPT etc.
            $table->string('trading_symbol', 20)->index();
            $table->date('expiry_date')->index()->nullable();
            $table->decimal('strike_price', 10, 2)->index()->nullable();

            // Live OHLC data from live_ohlc
            $table->decimal('open', 15, 5)->nullable();
            $table->decimal('high', 15, 5)->nullable();
            $table->decimal('low', 15, 5)->nullable();
            $table->decimal('close', 15, 5)->nullable();
            $table->bigInteger('volume')->nullable();

            // Start time of OHLC candle (ms from API)
            $table->unsignedBigInteger('ts')->nullable();      // raw ms
            $table->timestamp('ts_at')->nullable();            // converted to datetime (optional but handy)

            // Current last traded price field if you want it
            $table->decimal('last_price', 15, 5)->nullable();

            $table->timestamps();

            // Optional: keep latest row per instrument_key + ts
            $table->unique(['instrument_key', 'ts'], 'uq_instrument_ts');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ohlc_quotes');
    }
};
