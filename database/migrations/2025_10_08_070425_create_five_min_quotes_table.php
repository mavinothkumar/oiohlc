<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('five_min_quotes', function (Blueprint $table) {
            $table->id();
            $table->string('instrument_token');
            $table->string('symbol');
            $table->float('last_price')->nullable();
            $table->bigInteger('volume')->nullable();
            $table->float('average_price')->nullable();
            $table->bigInteger('oi')->nullable();
            $table->float('net_change')->nullable();
            $table->bigInteger('total_buy_quantity')->nullable();
            $table->bigInteger('total_sell_quantity')->nullable();
            $table->float('lower_circuit_limit')->nullable();
            $table->float('upper_circuit_limit')->nullable();
            $table->string('last_trade_time')->nullable();
            $table->bigInteger('oi_day_high')->nullable();
            $table->bigInteger('oi_day_low')->nullable();

            $table->float('open')->nullable();
            $table->float('high')->nullable();
            $table->float('low')->nullable();
            $table->float('close')->nullable();

            $table->timestamp('timestamp');

            // Diff columns
            $table->bigInteger('diff_oi')->nullable();
            $table->bigInteger('diff_volume')->nullable();
            $table->bigInteger('diff_buy_quantity')->nullable();
            $table->bigInteger('diff_sell_quantity')->nullable();
            $table->bigInteger('diff_quantity')->nullable();

            $table->timestamps();

            // Indexes for performance
            $table->index('instrument_token');
            $table->index('symbol');
            $table->index('timestamp');
            $table->index('volume');
            $table->index('oi');
            $table->index('total_buy_quantity');
            $table->index('total_sell_quantity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('five_min_quotes');
    }
};

