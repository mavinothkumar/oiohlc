<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('daily_ohlc_quotes', function (Blueprint $table) {
            $table->id();
            $table->string('symbol_name')->index();    // NIFTY, BANKNIFTY, SENSEX
            $table->string('instrument_key')->index();
            $table->string('expiry')->nullable()->index();
            $table->string('strike')->nullable()->index();
            $table->string('option_type')->nullable()->index();
            $table->date('quote_date')->index();       // The date for which OHLC applies
            $table->decimal('open', 15, 4)->nullable();
            $table->decimal('high', 15, 4)->nullable();
            $table->decimal('low', 15, 4)->nullable();
            $table->decimal('close', 15, 4)->nullable();
            $table->bigInteger('volume')->nullable();
            $table->bigInteger('open_interest')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_ohlc_quotes');
    }
};

