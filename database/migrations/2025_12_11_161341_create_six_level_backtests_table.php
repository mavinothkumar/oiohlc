<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('six_level_backtests', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('underlying_symbol', 50);
            $table->date('expiry');
            $table->date('trade_date');
            $table->date('prev_trade_date');

            $table->integer('atm_strike');
            $table->string('ce_instrument_key', 255);
            $table->string('pe_instrument_key', 255);

            $table->decimal('ce_prev_low', 12, 2);
            $table->decimal('pe_prev_low', 12, 2);
            $table->decimal('lowest_prev_low', 12, 2);

            $table->enum('lowest_prev_low_side', ['CE', 'PE', 'BOTH']);
            $table->enum('six_level_broken_side', ['CE', 'PE', 'BOTH', 'NONE'])->default('NONE');

            $table->dateTime('ce_break_time')->nullable();
            $table->dateTime('pe_break_time')->nullable();

            // CE 5-min candle at break
            $table->decimal('ce_break_open', 12, 2)->nullable();
            $table->decimal('ce_break_high', 12, 2)->nullable();
            $table->decimal('ce_break_low', 12, 2)->nullable();
            $table->decimal('ce_break_close', 12, 2)->nullable();
            $table->bigInteger('ce_break_volume')->nullable();
            $table->bigInteger('ce_break_oi')->nullable();

            // PE 5-min candle at break
            $table->decimal('pe_break_open', 12, 2)->nullable();
            $table->decimal('pe_break_high', 12, 2)->nullable();
            $table->decimal('pe_break_low', 12, 2)->nullable();
            $table->decimal('pe_break_close', 12, 2)->nullable();
            $table->bigInteger('pe_break_volume')->nullable();
            $table->bigInteger('pe_break_oi')->nullable();

            $table->timestamps();

            $table->index(
                ['underlying_symbol', 'expiry', 'trade_date'],
                'six_lvl_underlying_expiry_trade_date_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('six_level_backtests');
    }
};
