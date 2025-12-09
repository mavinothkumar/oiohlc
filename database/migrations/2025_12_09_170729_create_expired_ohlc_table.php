<?php

// database/migrations/xxxx_xx_xx_create_expired_ohlc_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expired_ohlc', function (Blueprint $table) {
            $table->id();

            // Instrument / meta
            $table->string('underlying_symbol');          // NIFTY / BANKNIFTY / etc.
            $table->string('exchange');                   // NSE
            $table->date('expiry')->nullable();                       // contract expiry date
            $table->string('instrument_key');             // NSE_FO|47983|17-04-2025
            $table->string('instrument_type');            // CE / PE
            $table->string('interval');                   // 5minute, day, etc.

            // OHLCV + OI
            $table->decimal('open', 12, 2);
            $table->decimal('high', 12, 2);
            $table->decimal('low', 12, 2);
            $table->decimal('close', 12, 2);
            $table->bigInteger('volume')->nullable();
            $table->bigInteger('open_interest')->nullable();

            // Candle timestamp from API (epoch ms or ISO -> store as datetime)
            $table->dateTime('timestamp');

            $table->timestamps();

            // Indexes for fast queries
            $table->index(['instrument_key', 'interval', 'timestamp']);
            $table->index(['underlying_symbol', 'expiry', 'instrument_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expired_ohlc');
    }
};
