<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('index_gap', function (Blueprint $table) {
            $table->id();
            $table->string('symbol_name', 20);
            $table->date('trading_date');
            $table->date('previous_trading_date');
            $table->decimal('previous_close', 12, 2)->nullable();
            $table->decimal('previous_high', 12, 2)->nullable();
            $table->decimal('previous_low', 12, 2)->nullable();
            $table->decimal('previous_day_range', 12, 2)->nullable();
            $table->decimal('current_open', 12, 2)->nullable();
            $table->decimal('gap_value', 12, 2)->nullable();
            $table->decimal('gap_abs', 12, 2)->nullable();
            $table->decimal('gap_pct_prev_close', 10, 4)->nullable();
            $table->decimal('gap_pct_prev_range', 10, 4)->nullable();
            $table->enum('gap_type', ['Gap Up', 'Gap Down', 'Flat'])->nullable();
            $table->timestamps();

            $table->unique(['symbol_name', 'trading_date']);
            $table->index('trading_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('index_gap');
    }
};
