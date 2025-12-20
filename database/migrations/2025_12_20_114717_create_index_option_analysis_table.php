<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('index_option_analysis', function (Blueprint $table) {
            $table->id();
            $table->string('underlying_symbol');
            $table->string('exchange');
            $table->date('trade_date');

            // Previous day index OHLC
            $table->decimal('prev_index_open', 12, 2)->nullable();
            $table->decimal('prev_index_high', 12, 2)->nullable();
            $table->decimal('prev_index_low', 12, 2)->nullable();
            $table->decimal('prev_index_close', 12, 2)->nullable();

            // ATM strike & prev day CE/PE
            $table->integer('atm_strike')->nullable();
            $table->decimal('prev_ce_open', 12, 2)->nullable();
            $table->decimal('prev_ce_high', 12, 2)->nullable();
            $table->decimal('prev_ce_low', 12, 2)->nullable();
            $table->decimal('prev_ce_close', 12, 2)->nullable();
            $table->decimal('prev_pe_open', 12, 2)->nullable();
            $table->decimal('prev_pe_high', 12, 2)->nullable();
            $table->decimal('prev_pe_low', 12, 2)->nullable();
            $table->decimal('prev_pe_close', 12, 2)->nullable();

            // Current day CE/PE OHLC
            $table->decimal('cur_ce_open', 12, 2)->nullable();
            $table->decimal('cur_ce_high', 12, 2)->nullable();
            $table->decimal('cur_ce_low', 12, 2)->nullable();
            $table->decimal('cur_ce_close', 12, 2)->nullable();
            $table->decimal('cur_pe_open', 12, 2)->nullable();
            $table->decimal('cur_pe_high', 12, 2)->nullable();
            $table->decimal('cur_pe_low', 12, 2)->nullable();
            $table->decimal('cur_pe_close', 12, 2)->nullable();

            // Current day index OHLC
            $table->decimal('cur_index_open', 12, 2)->nullable();
            $table->decimal('cur_index_high', 12, 2)->nullable();
            $table->decimal('cur_index_low', 12, 2)->nullable();
            $table->decimal('cur_index_close', 12, 2)->nullable();

            // Derived levels
            $table->decimal('range_ce_low_plus', 12, 2)->nullable();
            $table->decimal('range_ce_low_minus', 12, 2)->nullable();
            $table->decimal('avg_low', 12, 2)->nullable();
            $table->decimal('range_avg_low_plus', 12, 2)->nullable();
            $table->decimal('range_avg_low_minus', 12, 2)->nullable();
            $table->decimal('avg_high', 12, 2)->nullable();
            $table->decimal('range_avg_high_plus', 12, 2)->nullable();
            $table->decimal('range_avg_high_minus', 12, 2)->nullable();

            $table->timestamps();

            $table->index(['underlying_symbol', 'trade_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('index_option_analysis');
    }
};
